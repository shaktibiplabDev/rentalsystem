<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\Notification;
use App\Services\CashfreeService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Exception;

class WalletController extends Controller
{
    protected $cashfreeService;

    // Security & Limits
    protected $maxWalletBalance = 1000000;      // 10 Lakhs INR
    protected $minAddAmount = 1;
    protected $maxAddAmount = 100000;
    protected $minDeductAmount = 1;
    protected $maxDeductAmount = 100000;
    protected $minTransferAmount = 1;
    protected $maxTransferAmount = 50000;
    protected $maxDailyTransfer = 100000;
    protected $maxDailyTransactions = 20;
    protected $transferCooldownMinutes = 1;
    protected $cacheDuration = 300; // 5 minutes

    public function __construct(CashfreeService $cashfreeService)
    {
        $this->cashfreeService = $cashfreeService;
    }

    /**
     * Get current wallet balance
     */
    public function balance()
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'User not authenticated'], 401);
            }

            $balance = Cache::remember('wallet_balance_' . $user->id, now()->addMinutes($this->cacheDuration), function () use ($user) {
                return (float) $user->wallet_balance;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'balance' => $balance,
                    'currency' => 'INR',
                    'formatted_balance' => '₹' . number_format($balance, 2),
                    'max_balance_limit' => $this->maxWalletBalance,
                    'formatted_max_limit' => '₹' . number_format($this->maxWalletBalance, 2)
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Balance fetch error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to fetch balance'], 500);
        }
    }

    /**
     * Get wallet transactions with pagination & filtering
     */
    public function transactions(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'User not authenticated'], 401);
            }

            $validated = $request->validate([
                'type' => 'nullable|in:credit,debit',
                'status' => 'nullable|in:pending,completed,failed',
                'start_date' => 'nullable|date|before_or_equal:end_date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'per_page' => 'nullable|integer|min:1|max:100'
            ]);

            $query = $user->walletTransactions();

            if (!empty($validated['type'])) $query->where('type', $validated['type']);
            if (!empty($validated['status'])) $query->where('status', $validated['status']);
            if (!empty($validated['start_date']) && !empty($validated['end_date'])) {
                $start = Carbon::parse($validated['start_date'])->startOfDay();
                $end = Carbon::parse($validated['end_date'])->endOfDay();
                $query->whereBetween('created_at', [$start, $end]);
            }

            $perPage = $validated['per_page'] ?? 20;
            $transactions = $query->orderBy('created_at', 'desc')->paginate($perPage)
                ->through(fn($t) => $this->formatTransaction($t));

            $stats = [
                'total_credits' => (float) $user->walletTransactions()->where('type', 'credit')->where('status', 'completed')->sum('amount'),
                'total_debits'  => (float) $user->walletTransactions()->where('type', 'debit')->where('status', 'completed')->sum('amount'),
                'current_balance' => (float) $user->wallet_balance,
                'pending_transactions' => $user->walletTransactions()->where('status', 'pending')->count(),
                'failed_transactions' => $user->walletTransactions()->where('status', 'failed')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'transactions' => $transactions->items(),
                    'summary' => $stats,
                    'pagination' => [
                        'current_page' => $transactions->currentPage(),
                        'last_page' => $transactions->lastPage(),
                        'per_page' => $transactions->perPage(),
                        'total' => $transactions->total(),
                    ]
                ]
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Transactions fetch error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to fetch transactions'], 500);
        }
    }

    /**
     * Add money to wallet (manual / admin)
     */
    public function addMoney(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'User not authenticated'], 401);
            }

            $validated = $request->validate([
                'amount' => 'required|numeric|min:' . $this->minAddAmount . '|max:' . $this->maxAddAmount,
                'reason' => 'nullable|string|max:255',
                'payment_method' => 'nullable|string|in:credit_card,debit_card,upi,net_banking,cash'
            ]);

            $amount = (float) $validated['amount'];
            $reason = $this->sanitizeInput($validated['reason'] ?? 'Wallet recharge');
            $paymentMethod = $validated['payment_method'] ?? 'cash';

            // Rate limiting
            $rateKey = 'add_money_' . $user->id;
            $recent = (int) Cache::get($rateKey, 0);
            if ($recent >= 10) {
                return response()->json(['success' => false, 'message' => 'Too many requests. Try later.'], 429);
            }

            // Daily limit
            $dailyTotal = $this->getDailyTotal($user->id, 'credit');
            if ($dailyTotal + $amount > $this->maxDailyTransfer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Daily add money limit exceeded',
                    'errors' => ['amount' => ['Daily limit ₹' . number_format($this->maxDailyTransfer, 2)]]
                ], 422);
            }

            DB::beginTransaction();
            try {
                $updated = User::where('id', $user->id)
                    ->where(DB::raw('wallet_balance + ' . $amount), '<=', $this->maxWalletBalance)
                    ->update(['wallet_balance' => DB::raw('wallet_balance + ' . $amount)]);

                if (!$updated) {
                    $currentBalance = User::where('id', $user->id)->value('wallet_balance');
                    if ($currentBalance + $amount > $this->maxWalletBalance) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Amount would exceed maximum wallet limit',
                            'errors' => ['amount' => ['Max wallet balance ₹' . number_format($this->maxWalletBalance, 2)]]
                        ], 422);
                    }
                    return response()->json(['success' => false, 'message' => 'Concurrent operation failed'], 409);
                }

                $user->refresh();
                $transaction = WalletTransaction::create([
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'type' => 'credit',
                    'reason' => $reason,
                    'status' => 'completed',
                    'reference_id' => $this->generateReferenceId(),
                    'payment_method' => $paymentMethod
                ]);

                DB::commit();
                Cache::forget('wallet_balance_' . $user->id);
                Cache::put($rateKey, $recent + 1, now()->addMinutes(1));

                $this->sendNotification($user->id, [
                    'title' => 'Money Added to Wallet',
                    'message' => '₹' . number_format($amount, 2) . ' added to your wallet.',
                    'type' => 'wallet_credit'
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Money added successfully',
                    'data' => [
                        'transaction' => $this->formatTransaction($transaction),
                        'new_balance' => (float) $user->wallet_balance,
                        'formatted_balance' => '₹' . number_format($user->wallet_balance, 2)
                    ]
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Add money error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to add money'], 500);
        }
    }

    /**
     * Deduct money from wallet (manual)
     */
    public function deductMoney(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'User not authenticated'], 401);
            }

            $validated = $request->validate([
                'amount' => 'required|numeric|min:' . $this->minDeductAmount . '|max:' . $this->maxDeductAmount,
                'reason' => 'required|string|max:255',
                'reference_id' => 'nullable|string|max:100'
            ]);

            $amount = (float) $validated['amount'];
            $reason = $this->sanitizeInput($validated['reason']);

            DB::beginTransaction();
            try {
                $updated = User::where('id', $user->id)
                    ->where('wallet_balance', '>=', $amount)
                    ->update(['wallet_balance' => DB::raw('wallet_balance - ' . $amount)]);

                if (!$updated) {
                    $currentBalance = User::where('id', $user->id)->value('wallet_balance');
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient balance',
                        'errors' => ['amount' => ['Current balance: ₹' . number_format($currentBalance, 2)]]
                    ], 422);
                }

                $user->refresh();
                $transaction = WalletTransaction::create([
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'type' => 'debit',
                    'reason' => $reason,
                    'status' => 'completed',
                    'reference_id' => $validated['reference_id'] ?? $this->generateReferenceId()
                ]);

                DB::commit();
                Cache::forget('wallet_balance_' . $user->id);

                return response()->json([
                    'success' => true,
                    'message' => 'Money deducted successfully',
                    'data' => [
                        'transaction' => $this->formatTransaction($transaction),
                        'new_balance' => (float) $user->wallet_balance,
                        'formatted_balance' => '₹' . number_format($user->wallet_balance, 2)
                    ]
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Deduct money error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to deduct money'], 500);
        }
    }

    /**
     * Transfer money to another user
     */
    public function transfer(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'User not authenticated'], 401);
            }

            $validated = $request->validate([
                'recipient_phone' => 'required|string|max:20|regex:/^[0-9]{10,15}$/',
                'amount' => 'required|numeric|min:' . $this->minTransferAmount . '|max:' . $this->maxTransferAmount,
                'reason' => 'nullable|string|max:255',
                'notes' => 'nullable|string|max:500'
            ]);

            if ($validated['recipient_phone'] === $user->phone) {
                return response()->json(['success' => false, 'message' => 'Cannot transfer to yourself'], 422);
            }

            $amount = (float) $validated['amount'];
            $reason = $this->sanitizeInput($validated['reason'] ?? 'Wallet transfer');
            $notes = $this->sanitizeInput($validated['notes'] ?? null);

            // Rate limiting
            $rateKey = 'transfer_' . $user->id;
            $lastTransfer = Cache::get($rateKey);
            if ($lastTransfer && $lastTransfer > now()->subMinutes($this->transferCooldownMinutes)->timestamp) {
                return response()->json(['success' => false, 'message' => 'Please wait before another transfer'], 429);
            }

            // Daily limits
            $dailyTotal = $this->getDailyTotal($user->id, 'debit');
            if ($dailyTotal + $amount > $this->maxDailyTransfer) {
                return response()->json(['success' => false, 'message' => 'Daily transfer limit exceeded'], 422);
            }
            $dailyCount = $this->getDailyTransactionCount($user->id);
            if ($dailyCount >= $this->maxDailyTransactions) {
                return response()->json(['success' => false, 'message' => 'Daily transaction limit exceeded'], 422);
            }

            DB::beginTransaction();
            try {
                $sender = User::where('id', $user->id)->lockForUpdate()->first();
                $recipient = User::where('phone', $validated['recipient_phone'])->lockForUpdate()->first();

                if (!$recipient) {
                    return response()->json(['success' => false, 'message' => 'Recipient not found'], 404);
                }
                if ($sender->wallet_balance < $amount) {
                    return response()->json(['success' => false, 'message' => 'Insufficient balance'], 422);
                }
                if ($recipient->wallet_balance + $amount > $this->maxWalletBalance) {
                    return response()->json(['success' => false, 'message' => 'Recipient wallet limit exceeded'], 422);
                }

                $refId = $this->generateReferenceId();

                // Deduct from sender
                User::where('id', $sender->id)->where('wallet_balance', '>=', $amount)
                    ->update(['wallet_balance' => DB::raw('wallet_balance - ' . $amount)]);
                // Add to recipient
                User::where('id', $recipient->id)
                    ->update(['wallet_balance' => DB::raw('wallet_balance + ' . $amount)]);

                $debitTx = WalletTransaction::create([
                    'user_id' => $sender->id,
                    'amount' => $amount,
                    'type' => 'debit',
                    'reason' => 'Transfer to ' . $recipient->phone . ': ' . $reason,
                    'reference_id' => $refId,
                    'status' => 'completed',
                    'notes' => $notes
                ]);

                $creditTx = WalletTransaction::create([
                    'user_id' => $recipient->id,
                    'amount' => $amount,
                    'type' => 'credit',
                    'reason' => 'Transfer from ' . $sender->phone . ': ' . $reason,
                    'reference_id' => $refId,
                    'status' => 'completed',
                    'notes' => $notes
                ]);

                DB::commit();

                Cache::forget('wallet_balance_' . $sender->id);
                Cache::forget('wallet_balance_' . $recipient->id);
                Cache::put($rateKey, now()->timestamp, now()->addMinutes($this->transferCooldownMinutes));

                $this->sendNotification($sender->id, [
                    'title' => 'Money Sent',
                    'message' => '₹' . number_format($amount, 2) . ' sent to ' . $recipient->phone,
                    'type' => 'transfer_debit'
                ]);
                $this->sendNotification($recipient->id, [
                    'title' => 'Money Received',
                    'message' => '₹' . number_format($amount, 2) . ' received from ' . $sender->phone,
                    'type' => 'transfer_credit'
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Transfer successful',
                    'data' => [
                        'amount' => $amount,
                        'formatted_amount' => '₹' . number_format($amount, 2),
                        'recipient_phone' => $recipient->phone,
                        'recipient_name' => $recipient->name,
                        'reference_id' => $refId,
                        'new_balance' => (float) $sender->wallet_balance,
                        'formatted_balance' => '₹' . number_format($sender->wallet_balance, 2)
                    ]
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Transfer error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Transfer failed'], 500);
        }
    }

    /**
     * Initiate wallet recharge via Cashfree
     */
    public function initiateRecharge(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'User not authenticated'], 401);
            }

            $validated = $request->validate([
                'amount' => 'required|numeric|min:' . $this->minAddAmount . '|max:' . $this->maxAddAmount,
                'payment_method' => 'nullable|string|in:card,upi,nb'
            ]);

            $amount = (float) $validated['amount'];

            if ($user->wallet_balance + $amount > $this->maxWalletBalance) {
                return response()->json(['success' => false, 'message' => 'Amount exceeds maximum wallet limit'], 422);
            }

            $rateKey = 'recharge_initiate_' . $user->id;
            $recent = (int) Cache::get($rateKey, 0);
            if ($recent >= 5) {
                return response()->json(['success' => false, 'message' => 'Too many requests. Try later.'], 429);
            }
            Cache::put($rateKey, $recent + 1, now()->addMinutes(1));

            $orderId = 'WALLET_' . strtoupper(uniqid()) . '_' . $user->id . '_' . time();

            $orderData = [
                'order_id' => $orderId,
                'order_amount' => $amount,
                'order_currency' => 'INR',
                'order_note' => 'Wallet recharge for user: ' . $user->phone,
                'customer_details' => [
                    'customer_id' => 'USER_' . $user->id,
                    'customer_email' => $user->email ?? $user->phone . '@temp.com',
                    'customer_phone' => $user->phone,
                    'customer_name' => $user->name
                ],
                'order_meta' => [
                    'return_url' => config('app.frontend_url') . '/wallet?payment_status=completed',
                    'notify_url' => url('/api/webhooks/cashfree/payment')
                ]
            ];

            $order = $this->cashfreeService->createPaymentOrder($orderData);

            if (!$order['success']) {
                Log::error('Cashfree order creation failed', ['user_id' => $user->id, 'error' => $order['error'] ?? 'Unknown']);
                return response()->json(['success' => false, 'message' => 'Payment order creation failed'], 500);
            }

            $pendingTransaction = WalletTransaction::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'type' => 'credit',
                'reason' => 'Wallet recharge via Cashfree',
                'status' => 'pending',
                'reference_id' => $orderId,
                'payment_order_id' => $order['order_id'],
                'payment_session_id' => $order['payment_session_id'] ?? null,
                'payment_method' => $validated['payment_method'] ?? null
            ]);

            Log::info('Wallet recharge initiated', ['user_id' => $user->id, 'order_id' => $orderId, 'amount' => $amount]);

            return response()->json([
                'success' => true,
                'message' => 'Payment order created',
                'data' => [
                    'order_id' => $orderId,
                    'amount' => $amount,
                    'formatted_amount' => '₹' . number_format($amount, 2),
                    'payment_session_id' => $order['payment_session_id'],
                    'transaction_id' => $pendingTransaction->id,
                    'expires_in' => 30
                ]
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Initiate recharge error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to initiate payment'], 500);
        }
    }

    /**
     * Poll payment status (mobile app)
     */
    public function checkPaymentStatus(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'User not authenticated'], 401);
            }

            $validated = $request->validate(['order_id' => 'required|string']);
            $orderId = $validated['order_id'];

            $transaction = WalletTransaction::where(function($q) use ($orderId) {
                    $q->where('reference_id', $orderId)->orWhere('payment_order_id', $orderId);
                })
                ->where('user_id', $user->id)
                ->first();

            if (!$transaction) {
                return response()->json(['success' => false, 'message' => 'Transaction not found'], 404);
            }

            if (in_array($transaction->status, ['completed', 'failed'])) {
                return response()->json(['success' => true, 'data' => ['status' => $transaction->status, 'amount' => $transaction->amount]]);
            }

            $orderStatus = $this->cashfreeService->getPaymentOrderStatus($transaction->payment_order_id);
            if (!$orderStatus['success']) {
                return response()->json(['success' => true, 'data' => ['status' => $transaction->status]]);
            }

            $paymentStatus = $orderStatus['payment_status'] ?? $orderStatus['order_status'] ?? null;

            if ($paymentStatus === 'SUCCESS' && $transaction->status === 'pending') {
                DB::beginTransaction();
                try {
                    $user = User::where('id', $transaction->user_id)->lockForUpdate()->first();
                    if (!$user) throw new \Exception('User not found');

                    $user->wallet_balance += $transaction->amount;
                    $user->save();

                    $transaction->status = 'completed';
                    $transaction->payment_details = json_encode($orderStatus);
                    $transaction->save();

                    DB::commit();
                    Cache::forget('wallet_balance_' . $user->id);

                    $this->sendNotification($user->id, [
                        'title' => 'Wallet Recharged',
                        'message' => '₹' . number_format($transaction->amount, 2) . ' added to your wallet.',
                        'type' => 'payment_success'
                    ]);

                    Log::info('Wallet credited via polling', ['user_id' => $user->id, 'order_id' => $orderId]);
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Polling wallet credit failed', ['error' => $e->getMessage()]);
                    $transaction->status = 'failed';
                    $transaction->save();
                }
            } elseif (in_array($paymentStatus, ['FAILED', 'CANCELLED', 'EXPIRED']) && $transaction->status === 'pending') {
                $transaction->status = 'failed';
                $transaction->payment_details = json_encode($orderStatus);
                $transaction->save();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'status' => $transaction->status,
                    'amount' => (float) $transaction->amount,
                    'formatted_amount' => '₹' . number_format($transaction->amount, 2),
                    'order_id' => $transaction->reference_id
                ]
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Status check error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to check status'], 500);
        }
    }

    /**
     * Get single transaction details
     */
    public function transactionDetails($id)
    {
        try {
            $user = auth()->user();
            if (!$user) return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);

            $transaction = WalletTransaction::where('id', $id)->where('user_id', $user->id)->first();
            if (!$transaction) return response()->json(['success' => false, 'message' => 'Transaction not found'], 404);

            return response()->json(['success' => true, 'data' => $this->formatTransaction($transaction)]);
        } catch (Exception $e) {
            Log::error('Transaction details error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to fetch transaction'], 500);
        }
    }

    /**
     * Generate wallet statement (JSON or CSV)
     */
    public function statement(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user) return response()->json(['success' => false, 'message' => 'User not authenticated'], 401);

            $validated = $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'format' => 'sometimes|in:json,csv'
            ]);

            $start = Carbon::parse($validated['start_date'])->startOfDay();
            $end = Carbon::parse($validated['end_date'])->endOfDay();
            if ($start->diffInDays($end) > 365) {
                return response()->json(['success' => false, 'message' => 'Date range cannot exceed 365 days'], 422);
            }

            $transactions = $user->walletTransactions()
                ->whereBetween('created_at', [$start, $end])
                ->where('status', 'completed')
                ->orderBy('created_at', 'desc')
                ->get();

            $statement = [
                'user' => ['name' => $user->name, 'phone' => $user->phone, 'email' => $user->email],
                'period' => ['start_date' => $start->format('Y-m-d'), 'end_date' => $end->format('Y-m-d')],
                'opening_balance' => $this->getOpeningBalance($user->id, $start),
                'closing_balance' => (float) $user->wallet_balance,
                'transactions' => $transactions->map(fn($t) => $this->formatTransaction($t)),
                'summary' => [
                    'total_credits' => (float) $transactions->where('type', 'credit')->sum('amount'),
                    'total_debits'  => (float) $transactions->where('type', 'debit')->sum('amount'),
                    'net_change'    => (float) ($transactions->where('type', 'credit')->sum('amount') - $transactions->where('type', 'debit')->sum('amount')),
                    'transaction_count' => $transactions->count()
                ]
            ];

            if (isset($validated['format']) && $validated['format'] === 'csv') {
                return $this->exportStatementCSV($statement);
            }

            return response()->json(['success' => true, 'data' => $statement]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Statement error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to generate statement'], 500);
        }
    }

    // ================== Helper Methods ==================

    private function formatTransaction($transaction): array
    {
        return [
            'id' => $transaction->id,
            'amount' => (float) $transaction->amount,
            'formatted_amount' => '₹' . number_format($transaction->amount, 2),
            'type' => $transaction->type,
            'type_label' => $transaction->type === 'credit' ? 'Credit' : 'Debit',
            'reason' => $transaction->reason,
            'status' => $transaction->status,
            'status_label' => ucfirst($transaction->status),
            'reference_id' => $transaction->reference_id,
            'payment_method' => $transaction->payment_method,
            'notes' => $transaction->notes,
            'created_at' => $transaction->created_at,
            'created_at_formatted' => $transaction->created_at->format('d M Y, h:i A'),
            'created_at_human' => $transaction->created_at->diffForHumans(),
        ];
    }

    private function getDailyTotal(int $userId, string $type): float
    {
        return (float) WalletTransaction::where('user_id', $userId)
            ->where('type', $type)
            ->where('status', 'completed')
            ->whereDate('created_at', Carbon::today())
            ->sum('amount');
    }

    private function getDailyTransactionCount(int $userId): int
    {
        return WalletTransaction::where('user_id', $userId)
            ->where('status', 'completed')
            ->whereDate('created_at', Carbon::today())
            ->count();
    }

    private function getOpeningBalance(int $userId, Carbon $startDate): float
    {
        $credits = (float) WalletTransaction::where('user_id', $userId)
            ->where('type', 'credit')->where('status', 'completed')->where('created_at', '<', $startDate)->sum('amount');
        $debits = (float) WalletTransaction::where('user_id', $userId)
            ->where('type', 'debit')->where('status', 'completed')->where('created_at', '<', $startDate)->sum('amount');
        return $credits - $debits;
    }

    private function exportStatementCSV(array $statement)
    {
        $headers = ['Date', 'Type', 'Amount', 'Reason', 'Reference ID', 'Status'];
        $rows = [];
        foreach ($statement['transactions'] as $tx) {
            $rows[] = [
                $tx['created_at_formatted'],
                $tx['type_label'],
                $tx['formatted_amount'],
                $tx['reason'],
                $tx['reference_id'],
                $tx['status_label']
            ];
        }
        $csv = "\xEF\xBB\xBF" . implode(',', $headers) . "\n";
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(function($field) {
                $field = str_replace('"', '""', $field);
                if (strpos($field, ',') !== false || strpos($field, '"') !== false) return '"' . $field . '"';
                return $field;
            }, $row)) . "\n";
        }
        $filename = "wallet_statement_{$statement['period']['start_date']}_{$statement['period']['end_date']}.csv";
        return response($csv)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    private function sanitizeInput(?string $input): ?string
    {
        if ($input === null) return null;
        $cleaned = strip_tags($input);
        $cleaned = htmlspecialchars($cleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $cleaned = preg_replace('/[\x00-\x1F\x7F]/', '', $cleaned);
        return mb_substr($cleaned, 0, 500);
    }

    private function generateReferenceId(): string
    {
        return 'TXN_' . strtoupper(uniqid()) . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));
    }

    private function sendNotification(int $userId, array $data): void
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
}