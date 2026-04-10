<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * MAIN PAYMENT WEBHOOK
     */
    public function handlePayment(Request $request)
    {
        // ✅ STEP 1: RAW PAYLOAD (DO NOT TOUCH)
        $payload = $request->getContent();

        if (empty($payload)) {
            return response()->json(['error' => 'Empty payload'], 400);
        }

        // ✅ STEP 2: HEADERS
        $signature = $request->header('x-webhook-signature');
        $timestamp = $request->header('x-webhook-timestamp');

        if (! $signature || ! $timestamp) {
            Log::warning('Missing webhook headers');
            return response()->json(['error' => 'Missing headers'], 401);
        }

        // ✅ STEP 3: VERIFY SIGNATURE
        if (! $this->verifySignature($payload, $signature, $timestamp)) {
            Log::warning('Invalid webhook signature');
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // ✅ STEP 4: PARSE JSON
        $data = json_decode($payload, true);

        if (! $data) {
            return response()->json(['error' => 'Invalid JSON'], 400);
        }

        $eventType = $data['type'] ?? null;

        Log::info('Webhook received', [
            'type' => $eventType
        ]);

        // ✅ STEP 5: HANDLE EVENTS
        switch ($eventType) {

            case 'PAYMENT_SUCCESS_WEBHOOK':
                return $this->handlePaymentSuccess($data);

            case 'PAYMENT_FAILED_WEBHOOK':
                return response()->json(['success' => true], 200);

            case 'PAYMENT_USER_DROPPED_WEBHOOK':
                return response()->json(['success' => true], 200);

            default:
                return response()->json(['success' => true], 200);
        }
    }

    /**
     * 🔐 VERIFY SIGNATURE (CORRECT IMPLEMENTATION)
     */
    protected function verifySignature($payload, $signature, $timestamp): bool
    {
        $secret = config('cashfree.webhook_secret'); // your CLIENT SECRET

        if (! $secret) {
            Log::error('Cashfree secret missing');
            return false;
        }

        try {
            // 🔥 IMPORTANT: timestamp + payload
            $signedPayload = $timestamp . $payload;

            $expectedSignature = base64_encode(
                hash_hmac('sha256', $signedPayload, $secret, true)
            );

            // 🔍 DEBUG (optional, remove later)
            Log::info('SIGNATURE CHECK', [
                'match' => hash_equals($expectedSignature, $signature)
            ]);

            return hash_equals($expectedSignature, $signature);

        } catch (\Exception $e) {
            Log::error('Signature verification failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 💰 HANDLE PAYMENT SUCCESS
     */
    protected function handlePaymentSuccess($payload)
    {
        $data = $payload['data'] ?? [];

        $orderId   = $data['order']['order_id'] ?? null;
        $paymentId = $data['payment']['cf_payment_id'] ?? null;
        $amount    = $data['payment']['payment_amount'] ?? 0;

        if (! $orderId) {
            return response()->json(['error' => 'Missing order_id'], 400);
        }

        // 🔍 FIND TRANSACTION
        $transaction = WalletTransaction::where('payment_order_id', $orderId)
            ->orWhere('reference_id', $orderId)
            ->first();

        if (! $transaction) {
            Log::error('Transaction not found', ['order_id' => $orderId]);

            // ⚠️ IMPORTANT: still return 200 to stop retries
            return response()->json(['success' => true], 200);
        }

        // 🛑 IDEMPOTENCY CHECK
        if ($transaction->status !== 'pending') {
            return response()->json(['success' => true], 200);
        }

        // 💥 OPTIONAL: AMOUNT CHECK
        if (abs($amount - $transaction->amount) > 0.01) {
            Log::error('Amount mismatch', [
                'webhook' => $amount,
                'db' => $transaction->amount
            ]);

            return response()->json(['success' => true], 200);
        }

        // 🔒 TRANSACTION
        DB::beginTransaction();

        try {
            $user = User::lockForUpdate()->find($transaction->user_id);

            if (! $user) {
                throw new \Exception('User not found');
            }

            // 💰 CREDIT WALLET
            $user->wallet_balance += $transaction->amount;
            $user->save();

            // 🧾 UPDATE TRANSACTION
            $transaction->status = 'completed';
            $transaction->payment_details = json_encode([
                'payment_id' => $paymentId,
                'webhook' => $payload,
                'processed_at' => now()->toIso8601String()
            ]);
            $transaction->save();

            DB::commit();

            Log::info('Wallet credited successfully', [
                'user_id' => $user->id,
                'amount' => $transaction->amount
            ]);

            return response()->json(['success' => true], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Wallet credit failed', [
                'error' => $e->getMessage(),
                'order_id' => $orderId
            ]);

            return response()->json(['success' => false], 500);
        }
    }
}