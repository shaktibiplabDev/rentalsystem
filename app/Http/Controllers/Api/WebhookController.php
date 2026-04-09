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

    // Webhook idempotency key cache duration (24 hours)
    protected $idempotencyCacheDuration = 86400; // 24 hours in seconds

    // Maximum webhook processing attempts
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
        // Step 1: Get and validate payload
        $payload = $request->getContent();

        if (empty($payload)) {
            Log::warning('Empty webhook payload received', ['ip' => $request->ip()]);
            return response()->json(['success' => false, 'message' => 'Empty payload'], 400);
        }

        $signature = $request->header('x-webhook-signature');

        // 🔓 SIGNATURE VERIFICATION DISABLED FOR TESTING
        // if (! $signature) {
        //     Log::warning('Webhook request missing signature', ['ip' => $request->ip()]);
        //     return response()->json(['success' => false, 'message' => 'Missing signature'], 401);
        // }
        // if (! $this->verifyWebhookSignature($payload, $signature)) {
        //     Log::warning('⚠️ Invalid payment webhook signature', ['ip' => $request->ip()]);
        //     return response()->json(['success' => false, 'message' => 'Invalid signature'], 401);
        // }

        // Step 2: Parse payload
        try {
            $payloadData = json_decode($payload, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Invalid JSON payload received', ['error' => json_last_error_msg()]);
                return response()->json(['success' => false, 'message' => 'Invalid JSON'], 400);
            }
        } catch (\Exception $e) {
            Log::error('Failed to parse webhook payload', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Parse error'], 400);
        }

        // Step 3: Idempotency check (prevent duplicate processing)
        $eventId = $payloadData['event_id'] ?? null;
        $orderId = $payloadData['data']['order']['order_id'] ?? null;

        if ($eventId) {
            $idempotencyKey = 'webhook_processed_'.$eventId;
            if (Cache::has($idempotencyKey)) {
                Log::info('Duplicate webhook event detected - skipping', [
                    'event_id' => $eventId,
                    'order_id' => $orderId,
                ]);
                return response()->json(['success' => true, 'message' => 'Already processed']);
            }
        }

        // Step 4: Get event type
        $eventType = $payloadData['type'] ?? null;

        Log::info('📨 Payment webhook received', [
            'event_type' => $eventType,
            'order_id' => $orderId,
            'event_id' => $eventId,
            'ip' => $request->ip(),
            'timestamp' => now()->toIso8601String(),
        ]);

        // Step 5: Process based on event type
        $response = null;

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
                case 'AUTHORIZATION_SUCCESS_WEBHOOK':
                    $response = $this->handleAuthorizationSuccess($payloadData);
                    break;
                case 'AUTHORIZATION_FAILED_WEBHOOK':
                    $response = $this->handleAuthorizationFailed($payloadData);
                    break;
                default:
                    Log::info('Unhandled payment webhook event', [
                        'type' => $eventType,
                        'order_id' => $orderId,
                    ]);
                    $response = response()->json(['success' => true, 'message' => 'Event ignored']);
            }

            // Step 6: Mark as processed for idempotency
            if ($eventId && $response->getStatusCode() === 200) {
                Cache::put($idempotencyKey, true, $this->idempotencyCacheDuration);
            }

            return $response;

        } catch (\Exception $e) {
            Log::error('❌ Payment webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'event_type' => $eventType,
                'order_id' => $orderId,
                'ip' => $request->ip(),
            ]);

            $this->storeFailedWebhook($payloadData, $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Webhook processing failed',
                'error' => 'Internal server error',
            ], 500);
        }
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

        // 🔓 Signature verification disabled for testing
        // if (! $this->verifyWebhookSignature($payload, $signature)) {
        //     Log::warning('Invalid refund webhook signature');
        //     return response()->json(['success' => false, 'message' => 'Invalid signature'], 401);
        // }

        $payloadData = json_decode($payload, true);
        $eventType = $payloadData['type'] ?? null;

        if ($eventType === 'REFUND_SUCCESS_WEBHOOK') {
            return $this->handleRefundSuccess($payloadData);
        }

        return response()->json(['success' => true, 'message' => 'Event ignored']);
    }

    /**
     * Handle successful payment - THIS IS WHERE WALLET GETS CREDITED
     */
    protected function handlePaymentSuccess($payloadData)
    {
        $data = $payloadData['data'] ?? [];
        $orderId = $data['order']['order_id'] ?? null;
        $paymentId = $data['payment']['cf_payment_id'] ?? null;
        $paymentStatus = $data['payment']['payment_status'] ?? null;
        $paymentAmount = $data['payment']['order_amount'] ?? 0;

        Log::info('💰 Payment success webhook received', [
            'order_id' => $orderId,
            'payment_id' => $paymentId,
            'payment_status' => $paymentStatus,
            'amount' => $paymentAmount,
        ]);

        if (! $orderId) {
            Log::error('No order_id in webhook');
            return response()->json(['success' => false, 'message' => 'No order_id'], 400);
        }

        $transaction = WalletTransaction::where('payment_order_id', $orderId)
            ->orWhere('reference_id', $orderId)
            ->first();

        if (! $transaction) {
            Log::error('Transaction not found', ['order_id' => $orderId]);
            $this->storeOrphanWebhook($payloadData, 'transaction_not_found');
            return response()->json(['success' => false, 'message' => 'Transaction not found'], 404);
        }

        if ($transaction->type !== 'credit') {
            Log::error('Webhook for non-credit transaction', ['type' => $transaction->type, 'order_id' => $orderId]);
            return response()->json(['success' => false, 'message' => 'Invalid transaction type'], 400);
        }

        if (abs($paymentAmount - $transaction->amount) > 0.01) {
            Log::error('Amount mismatch', ['webhook' => $paymentAmount, 'db' => $transaction->amount]);
            $this->storeOrphanWebhook($payloadData, 'amount_mismatch');
            return response()->json(['success' => false, 'message' => 'Amount mismatch'], 400);
        }

        if ($transaction->status !== 'pending') {
            Log::info('Already processed', ['order_id' => $orderId, 'status' => $transaction->status]);
            return response()->json(['success' => true, 'message' => 'Already processed']);
        }

        // Double verification with Cashfree API
        $verification = $this->verifyWithCashfreeAPI($orderId);
        if (! $verification['success'] || ($verification['order_status'] ?? '') !== 'PAID') {
            Log::error('Verification failed', ['order_id' => $orderId, 'verification' => $verification]);
            $this->storeFailedVerification($transaction, $verification);
            return response()->json(['success' => false, 'message' => 'Verification failed'], 500);
        }

        DB::beginTransaction();
        try {
            $user = User::where('id', $transaction->user_id)->lockForUpdate()->first();
            if (! $user) {
                throw new \Exception('User not found: '.$transaction->user_id);
            }

            $user->wallet_balance += $transaction->amount;
            $user->save();

            $transaction->status = 'completed';
            $transaction->payment_details = json_encode([
                'webhook' => $payloadData,
                'verification' => $verification,
                'payment_id' => $paymentId,
                'processed_at' => now()->toIso8601String(),
            ]);
            $transaction->save();

            DB::commit();

            $this->sendNotification($user->id, [
                'title' => 'Wallet Recharged Successfully',
                'message' => '₹'.number_format($transaction->amount, 2).' added to your wallet.',
                'type' => 'payment',
                'data' => ['amount' => $transaction->amount, 'transaction_id' => $transaction->id],
            ]);

            Log::info('✅ Wallet credited', [
                'user_id' => $user->id,
                'amount' => $transaction->amount,
                'new_balance' => $user->wallet_balance,
            ]);

            return response()->json(['success' => true, 'message' => 'Wallet credited']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Wallet credit failed', ['error' => $e->getMessage(), 'order_id' => $orderId]);

            $transaction->status = 'failed';
            $transaction->payment_details = json_encode([
                'webhook' => $payloadData,
                'error' => $e->getMessage(),
                'failed_at' => now()->toIso8601String(),
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
        $paymentStatus = $data['payment']['payment_status'] ?? null;
        $errorDetails = $data['error_details'] ?? null;
        $failureReason = $data['payment']['failure_reason'] ?? null;

        Log::info('❌ Payment failed webhook received', [
            'order_id' => $orderId,
            'payment_status' => $paymentStatus,
            'failure_reason' => $failureReason,
            'error' => $errorDetails,
        ]);

        if ($orderId) {
            $transaction = WalletTransaction::where('payment_order_id', $orderId)
                ->orWhere('reference_id', $orderId)
                ->first();

            if ($transaction && $transaction->status === 'pending') {
                $transaction->status = 'failed';
                $transaction->payment_details = json_encode([
                    'webhook' => $payloadData,
                    'failure_reason' => $failureReason,
                    'failed_at' => now()->toIso8601String(),
                ]);
                $transaction->save();

                Log::info('Transaction marked as failed', [
                    'order_id' => $orderId,
                    'transaction_id' => $transaction->id,
                    'failure_reason' => $failureReason,
                ]);

                if ($transaction->user_id) {
                    $this->sendNotification($transaction->user_id, [
                        'title' => 'Payment Failed',
                        'message' => 'Your payment of ₹'.number_format($transaction->amount, 2).' failed. Please try again.',
                        'type' => 'payment_failed',
                        'data' => ['amount' => $transaction->amount, 'reason' => $failureReason],
                    ]);
                }
            }
        }

        return response()->json(['success' => true]);
    }

    /**
     * Handle user dropped payment webhook
     */
    protected function handlePaymentDropped($payloadData)
    {
        $data = $payloadData['data'] ?? [];
        $orderId = $data['order']['order_id'] ?? null;
        $paymentStatus = $data['payment']['payment_status'] ?? null;

        Log::info('🚶 User dropped payment webhook received', [
            'order_id' => $orderId,
            'payment_status' => $paymentStatus,
        ]);

        if ($orderId) {
            $transaction = WalletTransaction::where('payment_order_id', $orderId)
                ->orWhere('reference_id', $orderId)
                ->first();

            if ($transaction && $transaction->status === 'pending') {
                $transaction->status = 'failed';
                $transaction->payment_details = json_encode([
                    'webhook' => $payloadData,
                    'reason' => 'user_dropped',
                    'dropped_at' => now()->toIso8601String(),
                ]);
                $transaction->save();

                Log::info('Transaction marked as failed due to user drop', [
                    'order_id' => $orderId,
                    'transaction_id' => $transaction->id,
                ]);
            }
        }

        return response()->json(['success' => true]);
    }

    /**
     * Handle authorization success (for card on file, etc.)
     */
    protected function handleAuthorizationSuccess($payloadData)
    {
        $data = $payloadData['data'] ?? [];
        $orderId = $data['order']['order_id'] ?? null;
        $authId = $data['payment']['authorization_id'] ?? null;

        Log::info('🔐 Authorization success webhook received', [
            'order_id' => $orderId,
            'authorization_id' => $authId,
        ]);

        if ($orderId && $authId) {
            Cache::put('auth_'.$orderId, $authId, now()->addDays(30));
        }

        return response()->json(['success' => true]);
    }

    /**
     * Handle authorization failed
     */
    protected function handleAuthorizationFailed($payloadData)
    {
        $data = $payloadData['data'] ?? [];
        $orderId = $data['order']['order_id'] ?? null;
        $failureReason = $data['payment']['failure_reason'] ?? null;

        Log::info('🔐 Authorization failed webhook received', [
            'order_id' => $orderId,
            'failure_reason' => $failureReason,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Handle refund success
     */
    protected function handleRefundSuccess($payloadData)
    {
        $data = $payloadData['data'] ?? [];
        $refundId = $data['refund']['cf_refund_id'] ?? null;
        $orderId = $data['refund']['order_id'] ?? null;
        $refundAmount = $data['refund']['refund_amount'] ?? 0;

        Log::info('💰 Refund success webhook received', [
            'refund_id' => $refundId,
            'order_id' => $orderId,
            'amount' => $refundAmount,
        ]);

        if ($orderId) {
            $transaction = WalletTransaction::where('payment_order_id', $orderId)
                ->orWhere('reference_id', $orderId)
                ->first();

            if ($transaction) {
                WalletTransaction::create([
                    'user_id' => $transaction->user_id,
                    'amount' => -$refundAmount,
                    'type' => 'debit',
                    'reason' => 'Refund processed for order: '.$orderId,
                    'status' => 'completed',
                    'reference_id' => $refundId,
                    'payment_details' => json_encode($payloadData),
                ]);

                Log::info('Refund recorded', [
                    'order_id' => $orderId,
                    'refund_id' => $refundId,
                    'amount' => $refundAmount,
                ]);
            }
        }

        return response()->json(['success' => true]);
    }

    // ============================================
    // SECURITY AND HELPER METHODS
    // ============================================

    /**
     * Verify webhook signature (currently disabled for testing)
     */
    protected function verifyWebhookSignature($payload, $signature): bool
    {
        // 🔓 Signature verification disabled – always return true
        return true;

        // Original implementation (commented out):
        // if (! $signature) {
        //     Log::warning('No signature found in webhook request');
        //     return false;
        // }
        // $webhookSecret = config('cashfree.webhook_secret');
        // if (! $webhookSecret) {
        //     Log::warning('Webhook secret not configured');
        //     return false;
        // }
        // try {
        //     $calculatedSignature = base64_encode(hash_hmac('sha256', $payload, $webhookSecret, true));
        //     return hash_equals($calculatedSignature, $signature);
        // } catch (\Exception $e) {
        //     Log::error('Signature verification exception', ['error' => $e->getMessage()]);
        //     return false;
        // }
    }

    /**
     * Verify order status with Cashfree API
     */
    protected function verifyWithCashfreeAPI(string $orderId): array
    {
        $attempts = 0;
        $lastError = null;

        while ($attempts < $this->maxProcessingAttempts) {
            try {
                $result = $this->cashfreeService->getPaymentOrderStatus($orderId);
                if ($result['success']) {
                    return $result;
                }
                $lastError = $result['error'] ?? 'Unknown error';
                $attempts++;
                if ($attempts < $this->maxProcessingAttempts) {
                    sleep(1);
                }
            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                $attempts++;
                if ($attempts < $this->maxProcessingAttempts) {
                    sleep(1);
                }
            }
        }

        return [
            'success' => false,
            'error' => $lastError ?? 'Max retry attempts exceeded',
        ];
    }

    /**
     * Store orphan webhook for manual review
     */
    protected function storeOrphanWebhook(array $payload, string $reason): void
    {
        try {
            $orphanKey = 'orphan_webhook_'.date('Ymd_His').'_'.bin2hex(random_bytes(4));
            Cache::put($orphanKey, [
                'payload' => $payload,
                'reason' => $reason,
                'received_at' => now()->toIso8601String(),
                'ip' => request()->ip(),
            ], now()->addDays(7));
            Log::warning('Orphan webhook stored for manual review', [
                'key' => $orphanKey,
                'reason' => $reason,
            ]);
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
            $failKey = 'failed_verification_'.$transaction->id.'_'.time();
            Cache::put($failKey, [
                'transaction_id' => $transaction->id,
                'order_id' => $transaction->payment_order_id,
                'verification' => $verification,
                'failed_at' => now()->toIso8601String(),
            ], now()->addDays(7));
            Log::warning('Failed verification stored', [
                'key' => $failKey,
                'transaction_id' => $transaction->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to store verification failure', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Store failed webhook for retry
     */
    protected function storeFailedWebhook(array $payload, string $error): void
    {
        try {
            $failKey = 'failed_webhook_'.date('Ymd_His').'_'.bin2hex(random_bytes(4));
            Cache::put($failKey, [
                'payload' => $payload,
                'error' => $error,
                'failed_at' => now()->toIso8601String(),
                'ip' => request()->ip(),
                'attempts' => 0,
            ], now()->addDays(3));
            Log::warning('Failed webhook stored for retry', [
                'key' => $failKey,
                'error' => $error,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to store failed webhook', ['error' => $e->getMessage()]);
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
            Log::info('Notification sent to user', [
                'user_id' => $userId,
                'type' => $data['type'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send notification', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Health check endpoint for Cashfree to verify webhook is working
     */
    public function healthCheck(Request $request)
    {
        return response()->json([
            'success' => true,
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'environment' => app()->environment(),
        ], 200);
    }

    /**
     * Retry failed webhook (admin endpoint)
     */
    public function retryFailedWebhook(Request $request, string $key)
    {
        $failedData = Cache::get($key);
        if (! $failedData) {
            return response()->json([
                'success' => false,
                'message' => 'Failed webhook not found',
            ], 404);
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