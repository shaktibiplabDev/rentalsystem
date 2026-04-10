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
     * Handle Cashfree payment webhook
     */
    public function handlePayment(Request $request)
    {
        $rawPayload = $request->getContent();
        $signature = $request->header('x-webhook-signature');
        $timestamp = $request->header('x-webhook-timestamp');

        // 1. Verify signature (production critical)
        if (!$this->verifyWebhookSignature($rawPayload, $signature, $timestamp)) {
            Log::warning('Webhook signature verification failed', ['ip' => $request->ip()]);
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 401);
        }

        // 2. Parse JSON
        $payload = json_decode($rawPayload, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Invalid JSON payload', ['error' => json_last_error_msg()]);
            return response()->json(['success' => false, 'message' => 'Invalid JSON'], 400);
        }

        // 3. Idempotency
        $eventId = $payload['event_id'] ?? null;
        if ($eventId) {
            $idempotentKey = 'webhook_processed_' . $eventId;
            if (Cache::has($idempotentKey)) {
                Log::info('Duplicate webhook ignored', ['event_id' => $eventId]);
                return response()->json(['success' => true, 'message' => 'Already processed']);
            }
        }

        $orderId = $payload['data']['order']['order_id'] ?? null;
        $eventType = $payload['type'] ?? null;

        Log::info('Webhook received', ['type' => $eventType, 'order_id' => $orderId, 'event_id' => $eventId]);

        try {
            $response = match ($eventType) {
                'PAYMENT_SUCCESS_WEBHOOK' => $this->handlePaymentSuccess($payload),
                'PAYMENT_FAILED_WEBHOOK'  => $this->handlePaymentFailed($payload),
                'PAYMENT_USER_DROPPED_WEBHOOK' => $this->handlePaymentDropped($payload),
                default => response()->json(['success' => true, 'message' => 'Event ignored'])
            };

            if ($eventId && $response->getStatusCode() === 200) {
                Cache::put($idempotentKey, true, $this->idempotencyCacheDuration);
            }

            return $response;
        } catch (\Exception $e) {
            Log::error('Webhook processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'order_id' => $orderId
            ]);
            return response()->json(['success' => false, 'message' => 'Internal server error'], 500);
        }
    }

    /**
     * Handle successful payment – credit wallet
     */
    protected function handlePaymentSuccess(array $payload)
    {
        $data = $payload['data'] ?? [];
        $orderId = $data['order']['order_id'] ?? null;
        $paymentId = $data['payment']['cf_payment_id'] ?? null;
        $paymentStatus = $data['payment']['payment_status'] ?? null;
        $paymentAmount = (float) ($data['payment']['order_amount'] ?? 0);

        Log::info('Processing PAYMENT_SUCCESS_WEBHOOK', ['order_id' => $orderId, 'payment_id' => $paymentId]);

        if (!$orderId) {
            return response()->json(['success' => false, 'message' => 'Missing order_id'], 400);
        }

        $transaction = WalletTransaction::where('payment_order_id', $orderId)
            ->orWhere('reference_id', $orderId)
            ->first();

        if (!$transaction) {
            Log::error('Transaction not found for webhook', ['order_id' => $orderId]);
            $this->storeOrphanWebhook($payload, 'transaction_not_found');
            return response()->json(['success' => false, 'message' => 'Transaction not found'], 404);
        }

        // Validate
        if ($transaction->type !== 'credit') {
            return response()->json(['success' => false, 'message' => 'Invalid transaction type'], 400);
        }
        if (abs($paymentAmount - $transaction->amount) > 0.01) {
            Log::error('Amount mismatch', ['webhook' => $paymentAmount, 'db' => $transaction->amount]);
            $this->storeOrphanWebhook($payload, 'amount_mismatch');
            return response()->json(['success' => false, 'message' => 'Amount mismatch'], 400);
        }
        if ($transaction->status !== 'pending') {
            Log::info('Already processed', ['order_id' => $orderId, 'status' => $transaction->status]);
            return response()->json(['success' => true, 'message' => 'Already processed']);
        }

        // Double‑verify with Cashfree API
        $verification = $this->verifyWithCashfreeAPI($orderId);
        if (!$verification['success'] || ($verification['payment_status'] ?? '') !== 'SUCCESS') {
            Log::error('Double verification failed', ['order_id' => $orderId, 'verification' => $verification]);
            $this->storeFailedVerification($transaction, $verification);
            return response()->json(['success' => false, 'message' => 'Verification failed'], 500);
        }

        DB::beginTransaction();
        try {
            $user = User::where('id', $transaction->user_id)->lockForUpdate()->first();
            if (!$user) throw new \Exception('User not found');

            $user->wallet_balance += $transaction->amount;
            $user->save();

            $transaction->status = 'completed';
            $transaction->payment_details = json_encode([
                'webhook' => $payload,
                'verification' => $verification,
                'payment_id' => $paymentId,
                'processed_at' => now()->toIso8601String()
            ]);
            $transaction->save();

            DB::commit();
            Cache::forget('wallet_balance_' . $user->id);

            $this->sendNotification($user->id, [
                'title' => 'Wallet Recharged Successfully',
                'message' => '₹' . number_format($transaction->amount, 2) . ' added to your wallet.',
                'type' => 'payment_success'
            ]);

            Log::info('Wallet credited via webhook', ['user_id' => $user->id, 'order_id' => $orderId]);
            return response()->json(['success' => true, 'message' => 'Wallet credited']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Wallet credit failed', ['error' => $e->getMessage(), 'order_id' => $orderId]);
            $transaction->status = 'failed';
            $transaction->payment_details = json_encode(['error' => $e->getMessage(), 'failed_at' => now()]);
            $transaction->save();
            return response()->json(['success' => false, 'message' => 'Failed to credit wallet'], 500);
        }
    }

    protected function handlePaymentFailed(array $payload)
    {
        $data = $payload['data'] ?? [];
        $orderId = $data['order']['order_id'] ?? null;
        $failureReason = $data['payment']['failure_reason'] ?? null;

        if ($orderId) {
            $transaction = WalletTransaction::where('payment_order_id', $orderId)
                ->orWhere('reference_id', $orderId)
                ->first();

            if ($transaction && $transaction->status === 'pending') {
                $transaction->status = 'failed';
                $transaction->payment_details = json_encode([
                    'webhook' => $payload,
                    'failure_reason' => $failureReason,
                    'failed_at' => now()
                ]);
                $transaction->save();

                Log::info('Transaction marked as failed', ['order_id' => $orderId, 'transaction_id' => $transaction->id]);

                $this->sendNotification($transaction->user_id, [
                    'title' => 'Payment Failed',
                    'message' => 'Your payment of ₹' . number_format($transaction->amount, 2) . ' failed. Please try again.',
                    'type' => 'payment_failed'
                ]);
            }
        }

        return response()->json(['success' => true]);
    }

    protected function handlePaymentDropped(array $payload)
    {
        $data = $payload['data'] ?? [];
        $orderId = $data['order']['order_id'] ?? null;

        if ($orderId) {
            $transaction = WalletTransaction::where('payment_order_id', $orderId)
                ->orWhere('reference_id', $orderId)
                ->first();

            if ($transaction && $transaction->status === 'pending') {
                $transaction->status = 'failed';
                $transaction->payment_details = json_encode(['webhook' => $payload, 'reason' => 'user_dropped']);
                $transaction->save();
                Log::info('Transaction failed due to user drop', ['order_id' => $orderId]);
            }
        }

        return response()->json(['success' => true]);
    }

    /**
     * Handle refund webhook
     */
    public function handleRefund(Request $request)
    {
        $rawPayload = $request->getContent();
        $signature = $request->header('x-webhook-signature');
        $timestamp = $request->header('x-webhook-timestamp');

        if (!$this->verifyWebhookSignature($rawPayload, $signature, $timestamp)) {
            Log::warning('Refund webhook signature invalid');
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 401);
        }

        $payload = json_decode($rawPayload, true);
        if (($payload['type'] ?? '') === 'REFUND_SUCCESS_WEBHOOK') {
            return $this->handleRefundSuccess($payload);
        }

        return response()->json(['success' => true]);
    }

    protected function handleRefundSuccess(array $payload)
    {
        $data = $payload['data'] ?? [];
        $refundId = $data['refund']['cf_refund_id'] ?? null;
        $orderId = $data['refund']['order_id'] ?? null;
        $refundAmount = $data['refund']['refund_amount'] ?? 0;

        Log::info('Refund success webhook', ['refund_id' => $refundId, 'order_id' => $orderId]);

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
                    'payment_details' => json_encode($payload)
                ]);
                Log::info('Refund recorded', ['order_id' => $orderId, 'refund_id' => $refundId]);
            }
        }

        return response()->json(['success' => true]);
    }

    // ================== Security & Helpers ==================

    /**
     * Verify webhook signature using Cashfree client secret.
     */
    protected function verifyWebhookSignature(string $rawBody, ?string $signature, ?string $timestamp): bool
    {
        if (!$signature || !$timestamp) {
            Log::warning('Missing signature or timestamp');
            return false;
        }

        $secret = config('cashfree.secret_key'); // Your Cashfree client secret
        if (!$secret) {
            Log::error('Cashfree client secret not configured');
            return false;
        }

        $payload = $timestamp . $rawBody;
        $expected = base64_encode(hash_hmac('sha256', $payload, $secret, true));

        return hash_equals($expected, $signature);
    }

    /**
     * Double‑verify order status with Cashfree API
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

    protected function storeOrphanWebhook(array $payload, string $reason): void
    {
        $key = 'orphan_webhook_' . now()->format('Ymd_His') . '_' . bin2hex(random_bytes(4));
        Cache::put($key, ['payload' => $payload, 'reason' => $reason, 'received_at' => now()], now()->addDays(7));
        Log::warning('Orphan webhook stored', ['key' => $key, 'reason' => $reason]);
    }

    protected function storeFailedVerification($transaction, array $verification): void
    {
        $key = 'failed_verification_' . $transaction->id . '_' . time();
        Cache::put($key, [
            'transaction_id' => $transaction->id,
            'order_id' => $transaction->payment_order_id,
            'verification' => $verification
        ], now()->addDays(7));
    }

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
                'created_at' => now()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send notification', ['user_id' => $userId, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Health check endpoint for Cashfree to verify webhook is active
     */
    public function healthCheck(Request $request)
    {
        return response()->json(['success' => true, 'status' => 'healthy', 'timestamp' => now()->toIso8601String()], 200);
    }
}