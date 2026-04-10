<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\CashfreeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    protected $cashfreeService;
    protected $idempotencyCacheDuration = 86400; // 24 hours
    protected $maxProcessingAttempts = 3;

    public function __construct(CashfreeService $cashfreeService)
    {
        $this->cashfreeService = $cashfreeService;
    }

    /**
     * ============================================
     * PAYMENT WEBHOOK (FOR WALLET RECHARGE)
     * ============================================
     */
    public function handlePayment(Request $request)
    {
        // Step 1: Get raw payload (DO NOT MODIFY)
        $payload = $request->getContent();

        if (empty($payload)) {
            Log::warning('Empty webhook payload', ['ip' => $request->ip()]);
            return response()->json(['success' => false, 'message' => 'Empty payload'], 400);
        }

        // Step 2: Get headers
        $signature = $request->header('x-webhook-signature');
        $timestamp = $request->header('x-webhook-timestamp');

        if (!$signature || !$timestamp) {
            Log::warning('Missing webhook signature/timestamp', ['ip' => $request->ip()]);
            return response()->json(['success' => false, 'message' => 'Missing headers'], 401);
        }

        // Step 3: Verify signature (timestamp + raw payload)
        if (!$this->verifySignature($payload, $signature, $timestamp)) {
            Log::warning('Invalid webhook signature', ['ip' => $request->ip()]);
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 401);
        }

        // Step 4: Parse JSON
        try {
            $payloadData = json_decode($payload, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Invalid JSON payload', ['error' => json_last_error_msg()]);
                return response()->json(['success' => false, 'message' => 'Invalid JSON'], 400);
            }
        } catch (\Exception $e) {
            Log::error('Failed to parse webhook payload', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Parse error'], 400);
        }

        // Step 5: Idempotency (use event_id if available)
        $eventId = $payloadData['event_id'] ?? null;
        $orderId = $payloadData['data']['order']['order_id'] ?? null;

        if ($eventId) {
            $idempotencyKey = 'webhook_processed_' . $eventId;
            if (Cache::has($idempotencyKey)) {
                Log::info('Duplicate webhook event – skipping', ['event_id' => $eventId]);
                return response()->json(['success' => true, 'message' => 'Already processed']);
            }
        }

        // Step 6: Get event type
        $eventType = $payloadData['type'] ?? null;

        Log::info('📨 Payment webhook received', [
            'event_type' => $eventType,
            'order_id' => $orderId,
            'event_id' => $eventId,
            'ip' => $request->ip(),
        ]);

        // Step 7: Process based on event type
        try {
            switch ($eventType) {
                case 'PAYMENT_SUCCESS_WEBHOOK':
                    $response = $this->handlePaymentSuccess($payloadData);
                    break;
                case 'PAYMENT_FAILED_WEBHOOK':
                    $response = $this->handlePaymentFailed($payloadData);
                    break;
                case 'PAYMENT_USER_DROPPED_WEBHOOK':
                    $response = $this->handlePaymentDropped($payloadData);
                    break;
                default:
                    Log::info('Unhandled webhook event', ['type' => $eventType]);
                    $response = response()->json(['success' => true, 'message' => 'Event ignored']);
            }

            // Mark idempotency on success
            if ($eventId && $response->getStatusCode() === 200) {
                Cache::put($idempotencyKey, true, $this->idempotencyCacheDuration);
            }

            return $response;
        } catch (\Exception $e) {
            Log::error('Webhook processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'event_type' => $eventType,
                'order_id' => $orderId,
            ]);
            return response()->json(['success' => false, 'message' => 'Internal server error'], 500);
        }
    }

    /**
     * Handle successful payment – credit wallet (atomic)
     */
    protected function handlePaymentSuccess($payloadData)
    {
        $data = $payloadData['data'] ?? [];
        $orderId = $data['order']['order_id'] ?? null;
        $paymentId = $data['payment']['cf_payment_id'] ?? null;
        $paymentAmount = (float) ($data['payment']['payment_amount'] ?? $data['payment']['order_amount'] ?? 0);

        Log::info('💰 Payment success webhook', [
            'order_id' => $orderId,
            'payment_id' => $paymentId,
            'amount' => $paymentAmount,
        ]);

        if (!$orderId) {
            return response()->json(['success' => false, 'message' => 'Missing order_id'], 400);
        }

        // Find transaction
        $transaction = WalletTransaction::where('payment_order_id', $orderId)
            ->orWhere('reference_id', $orderId)
            ->first();

        if (!$transaction) {
            Log::error('Transaction not found for webhook', ['order_id' => $orderId]);
            $this->storeOrphanWebhook($payloadData, 'transaction_not_found');
            // Return 200 to stop Cashfree retries
            return response()->json(['success' => true, 'message' => 'Transaction not found, acknowledged']);
        }

        // Idempotency check
        if ($transaction->status !== 'pending') {
            Log::info('Transaction already processed', ['order_id' => $orderId, 'status' => $transaction->status]);
            return response()->json(['success' => true, 'message' => 'Already processed']);
        }

        // Validate transaction type
        if ($transaction->type !== 'credit') {
            Log::error('Webhook for non-credit transaction', ['type' => $transaction->type]);
            return response()->json(['success' => false, 'message' => 'Invalid transaction type'], 400);
        }

        // Optional amount match check
        if (abs($paymentAmount - $transaction->amount) > 0.01) {
            Log::error('Amount mismatch', ['webhook' => $paymentAmount, 'db' => $transaction->amount]);
            $this->storeOrphanWebhook($payloadData, 'amount_mismatch');
            return response()->json(['success' => true, 'message' => 'Amount mismatch, acknowledged']);
        }

        // 🔧 FIX: Double verification – Cashfree API returns 'order_status' with value 'PAID'
        $verification = $this->verifyWithCashfreeAPI($orderId);
        if (!$verification['success'] || ($verification['order_status'] ?? '') !== 'PAID') {
            Log::error('Double verification failed', ['order_id' => $orderId, 'verification' => $verification]);
            $this->storeFailedVerification($transaction, $verification);
            return response()->json(['success' => false, 'message' => 'Verification failed'], 500);
        }

        // Atomic wallet update
        DB::beginTransaction();
        try {
            $user = User::where('id', $transaction->user_id)->lockForUpdate()->first();
            if (!$user) {
                throw new \Exception('User not found: ' . $transaction->user_id);
            }

            // Credit wallet
            $user->wallet_balance += $transaction->amount;
            $user->save();

            // Update transaction
            $transaction->status = 'completed';
            $transaction->payment_details = json_encode([
                'webhook' => $payloadData,
                'verification' => $verification,
                'payment_id' => $paymentId,
                'processed_at' => now()->toIso8601String(),
            ]);
            $transaction->save();

            DB::commit();

            // Clear balance cache
            Cache::forget('wallet_balance_' . $user->id);

            // Send notification
            $this->sendNotification($user->id, [
                'title' => 'Wallet Recharged Successfully',
                'message' => '₹' . number_format($transaction->amount, 2) . ' added to your wallet.',
                'type' => 'payment_success',
                'data' => ['amount' => $transaction->amount, 'transaction_id' => $transaction->id],
            ]);

            Log::info('✅ Wallet credited via webhook', [
                'user_id' => $user->id,
                'amount' => $transaction->amount,
                'order_id' => $orderId,
            ]);

            return response()->json(['success' => true, 'message' => 'Wallet credited']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Wallet credit failed', ['error' => $e->getMessage(), 'order_id' => $orderId]);

            $transaction->status = 'failed';
            $transaction->payment_details = json_encode([
                'webhook' => $payloadData,
                'error' => $e->getMessage(),
                'failed_at' => now(),
            ]);
            $transaction->save();

            return response()->json(['success' => false, 'message' => 'Failed to credit wallet'], 500);
        }
    }

    /**
     * Handle failed payment webhook
     */
    protected function handlePaymentFailed($payloadData)
    {
        $data = $payloadData['data'] ?? [];
        $orderId = $data['order']['order_id'] ?? null;
        $failureReason = $data['payment']['failure_reason'] ?? null;

        Log::info('❌ Payment failed webhook', ['order_id' => $orderId, 'failure_reason' => $failureReason]);

        if ($orderId) {
            $transaction = WalletTransaction::where('payment_order_id', $orderId)
                ->orWhere('reference_id', $orderId)
                ->first();

            if ($transaction && $transaction->status === 'pending') {
                $transaction->status = 'failed';
                $transaction->payment_details = json_encode([
                    'webhook' => $payloadData,
                    'failure_reason' => $failureReason,
                    'failed_at' => now(),
                ]);
                $transaction->save();

                Log::info('Transaction marked as failed', ['order_id' => $orderId, 'transaction_id' => $transaction->id]);

                $this->sendNotification($transaction->user_id, [
                    'title' => 'Payment Failed',
                    'message' => 'Your payment of ₹' . number_format($transaction->amount, 2) . ' failed. Please try again.',
                    'type' => 'payment_failed',
                    'data' => ['amount' => $transaction->amount, 'reason' => $failureReason],
                ]);
            }
        }

        return response()->json(['success' => true]);
    }

    /**
     * Handle user dropped payment
     */
    protected function handlePaymentDropped($payloadData)
    {
        $data = $payloadData['data'] ?? [];
        $orderId = $data['order']['order_id'] ?? null;

        Log::info('🚶 User dropped payment', ['order_id' => $orderId]);

        if ($orderId) {
            $transaction = WalletTransaction::where('payment_order_id', $orderId)
                ->orWhere('reference_id', $orderId)
                ->first();

            if ($transaction && $transaction->status === 'pending') {
                $transaction->status = 'failed';
                $transaction->payment_details = json_encode([
                    'webhook' => $payloadData,
                    'reason' => 'user_dropped',
                    'dropped_at' => now(),
                ]);
                $transaction->save();
                Log::info('Transaction failed due to user drop', ['order_id' => $orderId]);
            }
        }

        return response()->json(['success' => true]);
    }

    /**
     * ============================================
     * REFUND WEBHOOK HANDLER
     * ============================================
     */
    public function handleRefund(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('x-webhook-signature');
        $timestamp = $request->header('x-webhook-timestamp');

        if (!$this->verifySignature($payload, $signature, $timestamp)) {
            Log::warning('Invalid refund webhook signature');
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 401);
        }

        $payloadData = json_decode($payload, true);
        $eventType = $payloadData['type'] ?? null;

        if ($eventType === 'REFUND_SUCCESS_WEBHOOK') {
            return $this->handleRefundSuccess($payloadData);
        }

        return response()->json(['success' => true, 'message' => 'Event ignored']);
    }

    protected function handleRefundSuccess($payloadData)
    {
        $data = $payloadData['data'] ?? [];
        $refundId = $data['refund']['cf_refund_id'] ?? null;
        $orderId = $data['refund']['order_id'] ?? null;
        $refundAmount = $data['refund']['refund_amount'] ?? 0;

        Log::info('💰 Refund success webhook', ['refund_id' => $refundId, 'order_id' => $orderId, 'amount' => $refundAmount]);

        if ($orderId) {
            $originalTx = WalletTransaction::where('payment_order_id', $orderId)
                ->orWhere('reference_id', $orderId)
                ->first();

            if ($originalTx) {
                WalletTransaction::create([
                    'user_id' => $originalTx->user_id,
                    'amount' => -$refundAmount,
                    'type' => 'debit',
                    'reason' => 'Refund for order ' . $orderId,
                    'status' => 'completed',
                    'reference_id' => $refundId,
                    'payment_details' => json_encode($payloadData),
                ]);

                Log::info('Refund recorded', ['order_id' => $orderId, 'refund_id' => $refundId]);
            }
        }

        return response()->json(['success' => true]);
    }

    // ============================================
    // SECURITY AND HELPER METHODS
    // ============================================

    /**
     * Verify webhook signature using Cashfree client secret.
     * Signature = base64(HMAC-SHA256(timestamp . raw payload, client_secret))
     */
    protected function verifySignature($payload, $signature, $timestamp): bool
    {
        if (!$signature || !$timestamp) {
            return false;
        }

        // Use the same secret key that you use for creating orders (payment client secret)
        $secret = config('cashfree.webhook_secret'); // Must be your payment client secret

        if (!$secret) {
            Log::error('Cashfree webhook secret not configured');
            return false;
        }

        try {
            $signedPayload = $timestamp . $payload;
            $expected = base64_encode(hash_hmac('sha256', $signedPayload, $secret, true));
            return hash_equals($expected, $signature);
        } catch (\Exception $e) {
            Log::error('Signature verification exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Double-verify order status with Cashfree API (returns order_status = PAID/ACTIVE/...)
     */
    protected function verifyWithCashfreeAPI(string $orderId): array
    {
        for ($i = 0; $i < $this->maxProcessingAttempts; $i++) {
            try {
                $result = $this->cashfreeService->getPaymentOrderStatus($orderId);
                if ($result['success']) {
                    return $result;
                }
                if ($i < $this->maxProcessingAttempts - 1) sleep(1);
            } catch (\Exception $e) {
                Log::error('Cashfree API call failed', ['order_id' => $orderId, 'error' => $e->getMessage()]);
                if ($i < $this->maxProcessingAttempts - 1) sleep(1);
            }
        }
        return ['success' => false, 'error' => 'Max retries exceeded'];
    }

    /**
     * Store orphan webhook for manual review
     */
    protected function storeOrphanWebhook(array $payload, string $reason): void
    {
        try {
            $key = 'orphan_webhook_' . now()->format('Ymd_His') . '_' . bin2hex(random_bytes(4));
            Cache::put($key, [
                'payload' => $payload,
                'reason' => $reason,
                'received_at' => now()->toIso8601String(),
                'ip' => request()->ip(),
            ], now()->addDays(7));
            Log::warning('Orphan webhook stored', ['key' => $key, 'reason' => $reason]);
        } catch (\Exception $e) {
            Log::error('Failed to store orphan webhook', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Store failed verification for manual review
     */
    protected function storeFailedVerification($transaction, array $verification): void
    {
        try {
            $key = 'failed_verification_' . $transaction->id . '_' . time();
            Cache::put($key, [
                'transaction_id' => $transaction->id,
                'order_id' => $transaction->payment_order_id,
                'verification' => $verification,
                'failed_at' => now()->toIso8601String(),
            ], now()->addDays(7));
            Log::warning('Failed verification stored', ['key' => $key]);
        } catch (\Exception $e) {
            Log::error('Failed to store verification failure', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Send notification to user
     */
    protected function sendNotification(int $userId, array $data): void
    {
        try {
            Notification::create([
                'user_id' => $userId,
                'title' => $data['title'],
                'message' => $data['message'],
                'type' => $data['type'],
                'data' => json_encode($data['data'] ?? []),
                'is_read' => false,
                'created_at' => now(),
            ]);
            Log::info('Notification sent', ['user_id' => $userId, 'type' => $data['type']]);
        } catch (\Exception $e) {
            Log::error('Failed to send notification', ['user_id' => $userId, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Health check endpoint (for Cashfree to verify webhook is active)
     */
    public function healthCheck(Request $request)
    {
        return response()->json([
            'success' => true,
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
        ], 200);
    }

    /**
     * Retry failed webhook (admin endpoint)
     */
    public function retryFailedWebhook(Request $request, string $key)
    {
        $failedData = Cache::get($key);
        if (!$failedData) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $attempts = ($failedData['attempts'] ?? 0) + 1;
        $failedData['attempts'] = $attempts;
        $failedData['retried_at'] = now()->toIso8601String();
        Cache::put($key, $failedData, now()->addDays(3));

        $fakeRequest = new Request([], [], [], [], [], [], json_encode($failedData['payload']));
        $fakeRequest->headers->set('Content-Type', 'application/json');

        $response = $this->handlePayment($fakeRequest);

        if ($response->getStatusCode() === 200) {
            Cache::forget($key);
            Log::info('Failed webhook retry successful', ['key' => $key]);
        }

        return $response;
    }
}