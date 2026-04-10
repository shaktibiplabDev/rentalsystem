<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\CashfreeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    protected $cashfreeService;

    public function __construct(CashfreeService $cashfreeService)
    {
        $this->cashfreeService = $cashfreeService;
    }

    /**
     * MAIN PAYMENT WEBHOOK
     */
    public function handlePayment(Request $request)
    {
        // 🔥 STEP 1: RAW BODY (DO NOT MODIFY)
        $payload = $request->getContent();

        if (empty($payload)) {
            return response()->json(['error' => 'Empty payload'], 400);
        }

        // 🔥 STEP 2: HEADERS
        $signature = $request->header('x-webhook-signature');
        $timestamp = $request->header('x-webhook-timestamp');

        if (! $signature || ! $timestamp) {
            return response()->json(['error' => 'Missing headers'], 401);
        }

        // 🔐 STEP 3: VERIFY SIGNATURE
        if (! $this->verifySignature($payload, $signature, $timestamp)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // 🔥 STEP 4: PARSE JSON
        $data = json_decode($payload, true);

        if (! $data) {
            return response()->json(['error' => 'Invalid JSON'], 400);
        }

        $eventType = $data['type'] ?? null;

        Log::info('Webhook received', [
            'type' => $eventType,
        ]);

        // 🔥 STEP 5: ROUTE EVENTS
        switch ($eventType) {
            case 'PAYMENT_SUCCESS_WEBHOOK':
                return $this->handlePaymentSuccess($data);

            case 'PAYMENT_FAILED_WEBHOOK':
                return response()->json(['success' => true]);

            default:
                return response()->json(['success' => true]);
        }
    }

    /**
     * 🔐 SIGNATURE VERIFICATION (FIXED)
     */
    protected function verifySignature($payload, $signature, $timestamp): bool
    {
        $secret = config('cashfree.webhook_secret'); // CLIENT SECRET

        if (! $secret) {
            Log::error('Cashfree secret missing');
            return false;
        }

        try {
            // 🔥 CRITICAL: timestamp + payload
            $signedPayload = $timestamp . $payload;

            $expectedSignature = base64_encode(
                hash_hmac('sha256', $signedPayload, $secret, true)
            );

            return hash_equals($expectedSignature, $signature);

        } catch (\Exception $e) {
            Log::error('Signature error', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 💰 PAYMENT SUCCESS HANDLER
     */
    protected function handlePaymentSuccess($payload)
    {
        $data = $payload['data'] ?? [];

        $orderId = $data['order']['order_id'] ?? null;
        $paymentId = $data['payment']['cf_payment_id'] ?? null;
        $amount = $data['payment']['payment_amount'] ?? 0; // ✅ FIXED

        if (! $orderId) {
            return response()->json(['error' => 'Missing order_id'], 400);
        }

        // 🔍 FIND TRANSACTION
        $transaction = WalletTransaction::where('payment_order_id', $orderId)->first();

        if (! $transaction) {
            Log::error('Transaction not found', ['order_id' => $orderId]);
            return response()->json(['error' => 'Transaction not found'], 404);
        }

        // 🛑 ALREADY PROCESSED
        if ($transaction->status !== 'pending') {
            return response()->json(['success' => true]);
        }

        // 🔒 DB LOCK
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
                'webhook' => $payload
            ]);
            $transaction->save();

            DB::commit();

            Log::info('Wallet credited', [
                'user_id' => $user->id,
                'amount' => $transaction->amount
            ]);

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Payment processing failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }
}