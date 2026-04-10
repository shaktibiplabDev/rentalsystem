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

    public function handlePayment(Request $request)
    {
        $payload = $request->getContent();
        if (empty($payload)) {
            Log::warning('Empty webhook payload');
            return response()->json(['success' => false, 'message' => 'Empty payload'], 400);
        }

        $signature = $request->header('x-webhook-signature');
        $timestamp = $request->header('x-webhook-timestamp');
        if (!$signature || !$timestamp) {
            Log::warning('Missing signature/timestamp');
            return response()->json(['success' => false, 'message' => 'Missing headers'], 401);
        }

        if (!$this->verifySignature($payload, $signature, $timestamp)) {
            Log::warning('Invalid webhook signature');
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 401);
        }

        $payloadData = json_decode($payload, true);
        if (!$payloadData) {
            Log::error('Invalid JSON');
            return response()->json(['success' => false, 'message' => 'Invalid JSON'], 400);
        }

        $eventType = $payloadData['type'] ?? null;
        $orderId = $payloadData['data']['order']['order_id'] ?? null;

        if (!$orderId) {
            Log::error('No order_id in webhook');
            return response()->json(['success' => false, 'message' => 'Missing order_id'], 400);
        }

        // 🔒 ATOMIC IDEMPOTENCY LOCK (prevents concurrent processing of same order)
        $lockKey = 'webhook_lock_' . $orderId;
        if (!Cache::add($lockKey, true, 60)) { // lock for 60 seconds
            Log::info('Duplicate webhook (concurrent) – skipped', ['order_id' => $orderId]);
            return response()->json(['success' => true, 'message' => 'Already processing']);
        }

        try {
            // Now process with full idempotency check (already processed flag)
            $idempotencyKey = 'webhook_processed_' . $orderId;
            if (Cache::has($idempotencyKey)) {
                Log::info('Duplicate webhook (already processed) – skipped', ['order_id' => $orderId]);
                return response()->json(['success' => true, 'message' => 'Already processed']);
            }

            Log::info('📨 Payment webhook received', ['type' => $eventType, 'order_id' => $orderId]);

            $response = match ($eventType) {
                'PAYMENT_SUCCESS_WEBHOOK' => $this->handlePaymentSuccess($payloadData, $orderId, $idempotencyKey),
                'PAYMENT_FAILED_WEBHOOK'  => $this->handlePaymentFailed($payloadData, $orderId),
                'PAYMENT_USER_DROPPED_WEBHOOK' => $this->handlePaymentDropped($payloadData, $orderId),
                default => response()->json(['success' => true, 'message' => 'Event ignored']),
            };

            return $response;
        } catch (\Exception $e) {
            Log::error('Webhook processing error', ['error' => $e->getMessage(), 'order_id' => $orderId]);
            return response()->json(['success' => false, 'message' => 'Internal error'], 500);
        } finally {
            Cache::forget($lockKey); // release lock after processing (success or fail)
        }
    }

    /**
     * Handle successful payment – atomic wallet credit with row locking
     */
    protected function handlePaymentSuccess($payloadData, string $orderId, string $idempotencyKey)
    {
        $data = $payloadData['data'] ?? [];
        $paymentId = $data['payment']['cf_payment_id'] ?? null;
        $paymentAmount = (float) ($data['payment']['payment_amount'] ?? $data['payment']['order_amount'] ?? 0);

        Log::info('💰 Payment success webhook', ['order_id' => $orderId, 'payment_id' => $paymentId, 'amount' => $paymentAmount]);

        // Find transaction (no lock yet, just get ID)
        $transaction = WalletTransaction::where('payment_order_id', $orderId)
            ->orWhere('reference_id', $orderId)
            ->first();

        if (!$transaction) {
            Log::error('Transaction not found', ['order_id' => $orderId]);
            $this->storeOrphanWebhook($payloadData, 'transaction_not_found');
            // Mark as processed to avoid retries
            Cache::put($idempotencyKey, true, $this->idempotencyCacheDuration);
            return response()->json(['success' => true, 'message' => 'Transaction not found, acknowledged']);
        }

        // Amount mismatch check (optional)
        if (abs($paymentAmount - $transaction->amount) > 0.01) {
            Log::error('Amount mismatch', ['webhook' => $paymentAmount, 'db' => $transaction->amount]);
            $this->storeOrphanWebhook($payloadData, 'amount_mismatch');
            Cache::put($idempotencyKey, true, $this->idempotencyCacheDuration);
            return response()->json(['success' => true, 'message' => 'Amount mismatch, acknowledged']);
        }

        // 🔒 DOUBLE-CHECK WITH ROW LOCK (prevents race condition)
        DB::beginTransaction();
        try {
            // Lock the transaction row for update
            $lockedTransaction = WalletTransaction::where('id', $transaction->id)
                ->lockForUpdate()
                ->first();

            if ($lockedTransaction->status !== 'pending') {
                DB::commit();
                Log::info('Transaction already processed (concurrent check)', ['order_id' => $orderId, 'status' => $lockedTransaction->status]);
                Cache::put($idempotencyKey, true, $this->idempotencyCacheDuration);
                return response()->json(['success' => true, 'message' => 'Already processed']);
            }

            // Optional: Double verification with Cashfree API
            $verification = $this->verifyWithCashfreeAPI($orderId);
            if (!$verification['success'] || ($verification['order_status'] ?? '') !== 'PAID') {
                Log::error('Double verification failed', ['order_id' => $orderId]);
                $this->storeFailedVerification($lockedTransaction, $verification);
                DB::commit();
                // Do NOT mark as idempotent – maybe later retry will succeed
                return response()->json(['success' => false, 'message' => 'Verification failed'], 500);
            }

            // Lock the user row and credit wallet
            $user = User::where('id', $lockedTransaction->user_id)->lockForUpdate()->first();
            if (!$user) {
                throw new \Exception('User not found: ' . $lockedTransaction->user_id);
            }

            $user->wallet_balance += $lockedTransaction->amount;
            $user->save();

            // Update transaction
            $lockedTransaction->status = 'completed';
            $lockedTransaction->payment_details = json_encode([
                'webhook' => $payloadData,
                'verification' => $verification,
                'payment_id' => $paymentId,
                'processed_at' => now()->toIso8601String(),
            ]);
            $lockedTransaction->save();

            DB::commit();

            // Clear balance cache
            Cache::forget('wallet_balance_' . $user->id);
            // Mark as idempotent
            Cache::put($idempotencyKey, true, $this->idempotencyCacheDuration);

            $this->sendNotification($user->id, [
                'title' => 'Wallet Recharged Successfully',
                'message' => '₹' . number_format($lockedTransaction->amount, 2) . ' added to your wallet.',
                'type' => 'payment_success',
            ]);

            Log::info('✅ Wallet credited via webhook', [
                'user_id' => $user->id,
                'amount' => $lockedTransaction->amount,
                'order_id' => $orderId,
            ]);

            return response()->json(['success' => true, 'message' => 'Wallet credited']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Wallet credit failed', ['error' => $e->getMessage(), 'order_id' => $orderId]);

            // Mark transaction as failed
            WalletTransaction::where('id', $transaction->id)
                ->update([
                    'status' => 'failed',
                    'payment_details' => json_encode(['error' => $e->getMessage(), 'failed_at' => now()]),
                ]);

            // Do NOT mark idempotent – allow retry
            return response()->json(['success' => false, 'message' => 'Failed to credit wallet'], 500);
        }
    }

    protected function handlePaymentFailed($payloadData, string $orderId)
    {
        $data = $payloadData['data'] ?? [];
        $failureReason = $data['payment']['failure_reason'] ?? null;
        Log::info('❌ Payment failed webhook', ['order_id' => $orderId, 'failure_reason' => $failureReason]);

        $transaction = WalletTransaction::where('payment_order_id', $orderId)
            ->orWhere('reference_id', $orderId)
            ->first();

        if ($transaction && $transaction->status === 'pending') {
            $transaction->status = 'failed';
            $transaction->payment_details = json_encode(['webhook' => $payloadData, 'failure_reason' => $failureReason]);
            $transaction->save();

            $this->sendNotification($transaction->user_id, [
                'title' => 'Payment Failed',
                'message' => 'Your payment of ₹' . number_format($transaction->amount, 2) . ' failed. Please try again.',
                'type' => 'payment_failed',
            ]);
        }

        // Mark as idempotent to avoid reprocessing
        Cache::put('webhook_processed_' . $orderId, true, $this->idempotencyCacheDuration);
        return response()->json(['success' => true]);
    }

    protected function handlePaymentDropped($payloadData, string $orderId)
    {
        Log::info('🚶 User dropped payment', ['order_id' => $orderId]);

        $transaction = WalletTransaction::where('payment_order_id', $orderId)
            ->orWhere('reference_id', $orderId)
            ->first();

        if ($transaction && $transaction->status === 'pending') {
            $transaction->status = 'failed';
            $transaction->payment_details = json_encode(['webhook' => $payloadData, 'reason' => 'user_dropped']);
            $transaction->save();
        }

        Cache::put('webhook_processed_' . $orderId, true, $this->idempotencyCacheDuration);
        return response()->json(['success' => true]);
    }

    // ==================== REFUND HANDLER (same as before) ====================
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
        return response()->json(['success' => true]);
    }

    protected function handleRefundSuccess($payloadData)
    {
        $data = $payloadData['data'] ?? [];
        $refundId = $data['refund']['cf_refund_id'] ?? null;
        $orderId = $data['refund']['order_id'] ?? null;
        $refundAmount = $data['refund']['refund_amount'] ?? 0;
        Log::info('💰 Refund success', ['refund_id' => $refundId, 'order_id' => $orderId]);

        if ($orderId) {
            $originalTx = WalletTransaction::where('payment_order_id', $orderId)->orWhere('reference_id', $orderId)->first();
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
            }
        }
        return response()->json(['success' => true]);
    }

    // ==================== SECURITY & HELPERS ====================
    protected function verifySignature($payload, $signature, $timestamp): bool
    {
        if (!$signature || !$timestamp) return false;
        $secret = config('cashfree.webhook_secret'); // must be your payment client secret
        if (!$secret) return false;
        try {
            $signed = $timestamp . $payload;
            $expected = base64_encode(hash_hmac('sha256', $signed, $secret, true));
            return hash_equals($expected, $signature);
        } catch (\Exception $e) {
            Log::error('Signature exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    protected function verifyWithCashfreeAPI(string $orderId): array
    {
        for ($i = 0; $i < $this->maxProcessingAttempts; $i++) {
            try {
                $result = $this->cashfreeService->getPaymentOrderStatus($orderId);
                if ($result['success']) return $result;
                if ($i < $this->maxProcessingAttempts - 1) sleep(1);
            } catch (\Exception $e) {
                Log::error('Cashfree API error', ['order_id' => $orderId, 'error' => $e->getMessage()]);
                if ($i < $this->maxProcessingAttempts - 1) sleep(1);
            }
        }
        return ['success' => false, 'error' => 'Max retries'];
    }

    protected function storeOrphanWebhook(array $payload, string $reason)
    {
        $key = 'orphan_' . now()->format('Ymd_His') . '_' . bin2hex(random_bytes(4));
        Cache::put($key, ['payload' => $payload, 'reason' => $reason], now()->addDays(7));
        Log::warning('Orphan webhook', ['key' => $key, 'reason' => $reason]);
    }

    protected function storeFailedVerification($transaction, array $verification)
    {
        $key = 'failed_verify_' . $transaction->id . '_' . time();
        Cache::put($key, ['transaction_id' => $transaction->id, 'order_id' => $transaction->payment_order_id, 'verification' => $verification], now()->addDays(7));
        Log::warning('Failed verification stored', ['key' => $key]);
    }

    protected function sendNotification(int $userId, array $data)
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
        } catch (\Exception $e) {
            Log::error('Notification failed', ['error' => $e->getMessage()]);
        }
    }

    public function healthCheck(Request $request)
    {
        return response()->json(['success' => true, 'status' => 'healthy']);
    }
}