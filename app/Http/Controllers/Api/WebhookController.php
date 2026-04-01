<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Rental;
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
     * ============================================
     * PAYMENT WEBHOOK (FOR WALLET RECHARGE)
     * ============================================
     * This is the PRIMARY webhook for wallet recharge payments.
     * Configure in Cashfree Dashboard:
     * URL: https://your-domain.com/api/webhooks/cashfree/payment
     * Events: PAYMENT_SUCCESS_WEBHOOK, PAYMENT_FAILED_WEBHOOK, PAYMENT_USER_DROPPED_WEBHOOK
     * 
     * Mobile app flow:
     * 1. App calls /api/wallet/recharge/initiate → gets payment_session_id
     * 2. App opens Cashfree SDK
     * 3. User completes payment
     * 4. Cashfree sends webhook HERE → wallet is credited
     * 5. App polls /api/wallet/payment-status to see completion
     */
    public function handlePayment(Request $request)
    {
        try {
            // Get raw payload for signature verification
            $payload = $request->getContent();
            $signature = $request->header('x-webhook-signature');
            
            Log::info('📨 Payment webhook received', [
                'signature' => $signature ? substr($signature, 0, 20) . '...' : 'missing',
                'content_type' => $request->header('content-type'),
                'has_payload' => !empty($payload)
            ]);
            
            // 🔐 CRITICAL: Verify webhook signature
            if (!$this->verifyWebhookSignature($payload, $signature)) {
                Log::warning('⚠️ Invalid payment webhook signature - potential fraud attempt', [
                    'ip' => $request->ip(),
                    'signature_received' => $signature
                ]);
                return response()->json(['success' => false, 'message' => 'Invalid signature'], 401);
            }
            
            $payloadData = $request->all();
            
            // Get the event type from Cashfree
            $eventType = $payloadData['type'] ?? null;
            
            Log::info('Payment webhook event type', ['type' => $eventType]);
            
            // Handle different webhook events
            switch ($eventType) {
                case 'PAYMENT_SUCCESS_WEBHOOK':
                    return $this->handlePaymentSuccess($payloadData);
                    
                case 'PAYMENT_FAILED_WEBHOOK':
                    return $this->handlePaymentFailed($payloadData);
                    
                case 'PAYMENT_USER_DROPPED_WEBHOOK':
                    return $this->handlePaymentDropped($payloadData);
                    
                default:
                    Log::info('Unhandled payment webhook event', ['type' => $eventType]);
                    return response()->json(['success' => true, 'message' => 'Event ignored']);
            }
            
        } catch (\Exception $e) {
            Log::error('❌ Payment webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Webhook processing failed'
            ], 500);
        }
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
        
        Log::info('💰 Payment success webhook received', [
            'order_id' => $orderId,
            'payment_id' => $paymentId,
            'payment_status' => $paymentStatus
        ]);
        
        if (!$orderId) {
            Log::error('No order_id in payment webhook payload');
            return response()->json(['success' => false, 'message' => 'No order_id'], 400);
        }
        
        // Find the transaction (check both payment_order_id and reference_id)
        $transaction = WalletTransaction::where('payment_order_id', $orderId)
            ->orWhere('reference_id', $orderId)
            ->first();
        
        if (!$transaction) {
            Log::error('Transaction not found for webhook', ['order_id' => $orderId]);
            return response()->json(['success' => false, 'message' => 'Transaction not found'], 404);
        }
        
        // Only process if pending (idempotency)
        if ($transaction->status !== 'pending') {
            Log::info('Webhook received for already processed transaction', [
                'order_id' => $orderId,
                'current_status' => $transaction->status
            ]);
            return response()->json(['success' => true, 'message' => 'Already processed']);
        }
        
        // 🔒 DOUBLE VERIFICATION: Verify with Cashfree API before crediting
        $verification = $this->cashfreeService->getPaymentOrderStatus($orderId);
        
        if (!$verification['success']) {
            Log::error('Failed to verify order status with Cashfree API', [
                'order_id' => $orderId,
                'error' => $verification['error'] ?? 'Unknown error'
            ]);
            return response()->json(['success' => false, 'message' => 'Verification failed'], 500);
        }
        
        // Only credit if order is PAID
        if ($verification['order_status'] !== 'PAID') {
            Log::warning('Webhook says success but order is not PAID', [
                'order_id' => $orderId,
                'order_status' => $verification['order_status']
            ]);
            return response()->json(['success' => false, 'message' => 'Order not paid'], 400);
        }
        
        // ✅ VERIFIED - Now credit the wallet
        DB::beginTransaction();
        
        try {
            // Lock user record for update (prevent race conditions)
            $user = User::where('id', $transaction->user_id)->lockForUpdate()->first();
            
            if (!$user) {
                throw new \Exception('User not found');
            }
            
            // Update wallet balance
            $user->wallet_balance += $transaction->amount;
            $user->save();
            
            // Update transaction status
            $transaction->status = 'completed';
            $transaction->payment_details = json_encode([
                'webhook' => $payloadData,
                'verification' => $verification,
                'payment_id' => $paymentId
            ]);
            $transaction->save();
            
            DB::commit();
            
            Log::info('✅ Wallet credited via webhook', [
                'user_id' => $user->id,
                'amount' => $transaction->amount,
                'order_id' => $orderId,
                'payment_id' => $paymentId,
                'new_balance' => $user->wallet_balance
            ]);
            
            return response()->json(['success' => true, 'message' => 'Wallet credited successfully']);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to credit wallet', [
                'error' => $e->getMessage(),
                'order_id' => $orderId,
                'trace' => $e->getTraceAsString()
            ]);
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
        
        Log::info('❌ Payment failed webhook received', [
            'order_id' => $orderId,
            'payment_status' => $paymentStatus,
            'error' => $errorDetails
        ]);
        
        if ($orderId) {
            $transaction = WalletTransaction::where('payment_order_id', $orderId)
                ->orWhere('reference_id', $orderId)
                ->first();
            
            if ($transaction && $transaction->status === 'pending') {
                $transaction->status = 'failed';
                $transaction->payment_details = json_encode($payloadData);
                $transaction->save();
                
                Log::info('Transaction marked as failed', [
                    'order_id' => $orderId,
                    'transaction_id' => $transaction->id
                ]);
            }
        }
        
        return response()->json(['success' => true]);
    }
    
    /**
     * Handle user dropped webhook (user abandoned payment)
     */
    protected function handlePaymentDropped($payloadData)
    {
        $data = $payloadData['data'] ?? [];
        $orderId = $data['order']['order_id'] ?? null;
        
        Log::info('🚶 User dropped payment webhook received', [
            'order_id' => $orderId,
            'payment_status' => $data['payment']['payment_status'] ?? null
        ]);
        
        if ($orderId) {
            $transaction = WalletTransaction::where('payment_order_id', $orderId)
                ->orWhere('reference_id', $orderId)
                ->first();
            
            if ($transaction && $transaction->status === 'pending') {
                $transaction->status = 'failed';
                $transaction->payment_details = json_encode($payloadData);
                $transaction->save();
                
                Log::info('Transaction marked as failed due to user drop', [
                    'order_id' => $orderId,
                    'transaction_id' => $transaction->id
                ]);
            }
        }
        
        return response()->json(['success' => true]);
    }
    
    /**
     * ============================================
     * WEBHOOK SIGNATURE VERIFICATION
     * ============================================
     * Verifies that the webhook is actually from Cashfree
     */
    protected function verifyWebhookSignature($payload, $signature): bool
    {
        if (!$signature) {
            Log::warning('No signature found in webhook request');
            return false;
        }
        
        $webhookSecret = config('cashfree.webhook_secret');
        
        if (!$webhookSecret) {
            Log::warning('Webhook secret not configured');
            return false;
        }
        
        // Cashfree uses base64 encoded HMAC SHA256
        $calculatedSignature = base64_encode(hash_hmac('sha256', $payload, $webhookSecret, true));
        $isValid = hash_equals($calculatedSignature, $signature);
        
        if (!$isValid) {
            Log::warning('Webhook signature mismatch', [
                'received' => substr($signature, 0, 20) . '...',
                'calculated' => substr($calculatedSignature, 0, 20) . '...'
            ]);
        }
        
        return $isValid;
    }
    
    /**
     * Send notification to user
     */
    protected function sendNotification($userId, $data)
    {
        try {
            \App\Models\Notification::create([
                'user_id' => $userId,
                'title' => $data['title'],
                'message' => $data['message'],
                'type' => $data['type'],
                'data' => json_encode(['rental_id' => $data['rental_id'] ?? null]),
                'is_read' => false
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send notification', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }
}