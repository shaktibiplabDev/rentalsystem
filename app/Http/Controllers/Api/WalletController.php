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
use Illuminate\Support\Facades\Validator;
use Exception;
use Carbon\Carbon;

class WalletController extends Controller
{
    protected $cashfreeService;
    
    // Security Constants
    protected $maxWalletBalance = 1000000; // 10 Lakhs INR
    protected $minAddAmount = 1;
    protected $maxAddAmount = 100000;
    protected $minDeductAmount = 1;
    protected $maxDeductAmount = 100000;
    protected $minTransferAmount = 1;
    protected $maxTransferAmount = 50000;
    protected $maxDailyTransfer = 100000; // Maximum transfer per day
    protected $maxDailyTransactions = 20; // Maximum transactions per day
    protected $transferCooldownMinutes = 1; // Cooldown between transfers
    protected $cacheDuration = 300; // 5 minutes for cached balance
    
    public function __construct(CashfreeService $cashfreeService)
    {
        $this->cashfreeService = $cashfreeService;
    }

    /**
     * Get current wallet balance with caching
     */
    public function balance()
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                Log::warning('Unauthenticated access to wallet balance', [
                    'ip' => request()->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Get from cache to reduce database load
            $cacheKey = 'wallet_balance_' . $user->id;
            $balance = Cache::remember($cacheKey, now()->addMinutes($this->cacheDuration), function () use ($user) {
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
            ], 200);

        } catch (QueryException $e) {
            Log::error('Database error fetching wallet balance', [
                'user_id' => auth()->id(),
                'error_code' => $e->getCode()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch wallet balance',
                'error' => 'Database error occurred'
            ], 500);
            
        } catch (Exception $e) {
            Log::error('Unexpected error fetching wallet balance', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch wallet balance',
                'error' => 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Get wallet transactions with pagination and filtering
     */
    public function transactions(Request $request)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                Log::warning('Unauthenticated access to wallet transactions', [
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $validated = $request->validate([
                'type' => 'nullable|in:credit,debit',
                'status' => 'nullable|in:pending,completed,failed',
                'start_date' => 'nullable|date|before_or_equal:end_date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'per_page' => 'nullable|integer|min:1|max:100',
                'page' => 'nullable|integer|min:1'
            ]);

            try {
                $query = $user->walletTransactions();
                
                // Filter by type
                if (!empty($validated['type'])) {
                    $query->where('type', $validated['type']);
                }
                
                // Filter by status
                if (!empty($validated['status'])) {
                    $query->where('status', $validated['status']);
                }
                
                // Filter by date range
                if (!empty($validated['start_date']) && !empty($validated['end_date'])) {
                    $startDate = Carbon::parse($validated['start_date'])->startOfDay();
                    $endDate = Carbon::parse($validated['end_date'])->endOfDay();
                    $query->whereBetween('created_at', [$startDate, $endDate]);
                }
                
                $perPage = $validated['per_page'] ?? 20;
                
                $transactions = $query->orderBy('created_at', 'desc')
                    ->paginate($perPage)
                    ->through(function ($transaction) {
                        return $this->formatTransaction($transaction);
                    });
                
                $totalCredits = (float) $user->walletTransactions()
                    ->where('type', 'credit')
                    ->where('status', 'completed')
                    ->sum('amount');
                    
                $totalDebits = (float) $user->walletTransactions()
                    ->where('type', 'debit')
                    ->where('status', 'completed')
                    ->sum('amount');
                    
                $pendingTransactions = $user->walletTransactions()
                    ->where('status', 'pending')
                    ->count();
                    
                $failedTransactions = $user->walletTransactions()
                    ->where('status', 'failed')
                    ->count();
                
                $stats = [
                    'total_credits' => $totalCredits,
                    'total_debits' => $totalDebits,
                    'current_balance' => (float) $user->wallet_balance,
                    'transaction_count' => $transactions->total(),
                    'pending_transactions' => $pendingTransactions,
                    'failed_transactions' => $failedTransactions,
                    'average_credit' => $transactions->where('type', 'credit')->count() > 0 
                        ? round($totalCredits / $transactions->where('type', 'credit')->count(), 2) 
                        : 0,
                    'average_debit' => $transactions->where('type', 'debit')->count() > 0 
                        ? round($totalDebits / $transactions->where('type', 'debit')->count(), 2) 
                        : 0
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
                            'from' => $transactions->firstItem(),
                            'to' => $transactions->lastItem()
                        ]
                    ]
                ], 200);

            } catch (QueryException $e) {
                Log::error('Database error fetching wallet transactions', [
                    'user_id' => $user->id,
                    'error_code' => $e->getCode()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch wallet transactions',
                    'error' => 'Database error occurred'
                ], 500);
            }

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Unexpected error fetching wallet transactions', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch wallet transactions',
                'error' => 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Add money to wallet with atomic operation and rate limiting
     */
    public function addMoney(Request $request)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                Log::warning('Unauthenticated add money attempt', [
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $validated = $request->validate([
                'amount' => 'required|numeric|min:' . $this->minAddAmount . '|max:' . $this->maxAddAmount,
                'reason' => 'nullable|string|max:255',
                'payment_method' => 'nullable|string|in:credit_card,debit_card,upi,net_banking,cash'
            ]);

            $amount = (float) $validated['amount'];
            $reason = $this->sanitizeInput($validated['reason'] ?? 'Wallet recharge');
            $paymentMethod = $validated['payment_method'] ?? 'cash';
            
            // Rate limiting for add money requests
            $rateLimitKey = 'add_money_' . $user->id;
            $recentRequests = (int) Cache::get($rateLimitKey, 0);
            
            if ($recentRequests >= 10) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many add money requests. Please try again later.',
                    'errors' => ['amount' => ['Please wait before making another request']]
                ], 429);
            }
            
            // Check daily limit
            $dailyTotal = $this->getDailyTotal($user->id, 'credit');
            if ($dailyTotal + $amount > $this->maxDailyTransfer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Daily add money limit exceeded',
                    'errors' => [
                        'amount' => ['Daily limit is ₹' . number_format($this->maxDailyTransfer, 2) . 
                                    '. Current daily total: ₹' . number_format($dailyTotal, 2)]
                    ]
                ], 422);
            }

            DB::beginTransaction();
            
            try {
                // Atomic update with balance check
                $updated = User::where('id', $user->id)
                    ->where(DB::raw('wallet_balance + ' . $amount), '<=', $this->maxWalletBalance)
                    ->update(['wallet_balance' => DB::raw('wallet_balance + ' . $amount)]);
                
                if (!$updated) {
                    DB::rollBack();
                    
                    $currentBalance = User::where('id', $user->id)->value('wallet_balance');
                    
                    if ($currentBalance + $amount > $this->maxWalletBalance) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Amount would exceed maximum wallet limit',
                            'errors' => [
                                'amount' => ['Adding this amount would exceed the maximum wallet balance limit of ₹' . number_format($this->maxWalletBalance, 2)]
                            ]
                        ], 422);
                    }
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to add money due to concurrent operation. Please try again.',
                    ], 409);
                }
                
                // Get updated user
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
                
                // Clear cache
                Cache::forget('wallet_balance_' . $user->id);
                Cache::put($rateLimitKey, $recentRequests + 1, now()->addMinutes(1));
                
                // Send notification
                $this->sendNotification($user->id, [
                    'title' => 'Money Added to Wallet',
                    'message' => '₹' . number_format($amount, 2) . ' has been added to your wallet.',
                    'type' => 'wallet_credit',
                    'data' => ['amount' => $amount, 'transaction_id' => $transaction->id]
                ]);
                
                Log::info('Money added to wallet', [
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'new_balance' => $user->wallet_balance,
                    'payment_method' => $paymentMethod,
                    'transaction_id' => $transaction->id,
                    'ip' => $request->ip()
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Money added successfully',
                    'data' => [
                        'transaction' => $this->formatTransaction($transaction),
                        'new_balance' => (float) $user->wallet_balance,
                        'formatted_balance' => '₹' . number_format($user->wallet_balance, 2)
                    ]
                ], 200);

            } catch (QueryException $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (ValidationException $e) {
            Log::warning('Add money validation failed', [
                'errors' => array_keys($e->errors()),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
            
        } catch (QueryException $e) {
            Log::error('Database error adding money', [
                'user_id' => auth()->id(),
                'amount' => $validated['amount'] ?? null,
                'error_code' => $e->getCode()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to add money',
                'error' => 'Database error occurred'
            ], 500);
            
        } catch (Exception $e) {
            Log::error('Unexpected error adding money', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to add money',
                'error' => 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Deduct money from wallet with atomic operation
     */
    public function deductMoney(Request $request)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
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
                // Atomic update with balance check
                $updated = User::where('id', $user->id)
                    ->where('wallet_balance', '>=', $amount)
                    ->update(['wallet_balance' => DB::raw('wallet_balance - ' . $amount)]);
                
                if (!$updated) {
                    DB::rollBack();
                    
                    $currentBalance = User::where('id', $user->id)->value('wallet_balance');
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient balance',
                        'errors' => [
                            'amount' => ['Insufficient wallet balance. Current balance: ₹' . number_format($currentBalance, 2)]
                        ]
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
                
                // Clear cache
                Cache::forget('wallet_balance_' . $user->id);
                
                Log::info('Money deducted from wallet', [
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'reason' => $reason,
                    'transaction_id' => $transaction->id,
                    'ip' => $request->ip()
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Money deducted successfully',
                    'data' => [
                        'transaction' => $this->formatTransaction($transaction),
                        'new_balance' => (float) $user->wallet_balance,
                        'formatted_balance' => '₹' . number_format($user->wallet_balance, 2)
                    ]
                ], 200);

            } catch (QueryException $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
            
        } catch (QueryException $e) {
            Log::error('Database error deducting money', [
                'user_id' => auth()->id(),
                'amount' => $validated['amount'] ?? null,
                'error_code' => $e->getCode()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to deduct money',
                'error' => 'Database error occurred'
            ], 500);
            
        } catch (Exception $e) {
            Log::error('Unexpected error deducting money', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to deduct money',
                'error' => 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Transfer money to another user with comprehensive security checks
     */
    public function transfer(Request $request)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                Log::warning('Unauthenticated transfer attempt', [
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $validated = $request->validate([
                'recipient_phone' => 'required|string|max:20|regex:/^[0-9]{10,15}$/',
                'amount' => 'required|numeric|min:' . $this->minTransferAmount . '|max:' . $this->maxTransferAmount,
                'reason' => 'nullable|string|max:255',
                'notes' => 'nullable|string|max:500'
            ]);

            // Cannot transfer to self
            if ($validated['recipient_phone'] === $user->phone) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot transfer to yourself',
                    'errors' => [
                        'recipient_phone' => ['You cannot transfer money to yourself.']
                    ]
                ], 422);
            }

            $amount = (float) $validated['amount'];
            $reason = $this->sanitizeInput($validated['reason'] ?? 'Wallet transfer');
            $notes = $this->sanitizeInput($validated['notes'] ?? null);
            
            // Rate limiting for transfers
            $rateLimitKey = 'transfer_' . $user->id;
            $lastTransfer = Cache::get($rateLimitKey);
            
            if ($lastTransfer && $lastTransfer > now()->subMinutes($this->transferCooldownMinutes)->timestamp) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please wait before making another transfer',
                    'errors' => [
                        'amount' => ['Please wait ' . $this->transferCooldownMinutes . ' minute(s) between transfers']
                    ]
                ], 429);
            }
            
            // Check daily transfer limit
            $dailyTotal = $this->getDailyTotal($user->id, 'debit');
            if ($dailyTotal + $amount > $this->maxDailyTransfer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Daily transfer limit exceeded',
                    'errors' => [
                        'amount' => ['Daily transfer limit is ₹' . number_format($this->maxDailyTransfer, 2) . 
                                    '. Current daily total: ₹' . number_format($dailyTotal, 2)]
                    ]
                ], 422);
            }
            
            // Check daily transaction count
            $dailyCount = $this->getDailyTransactionCount($user->id);
            if ($dailyCount >= $this->maxDailyTransactions) {
                return response()->json([
                    'success' => false,
                    'message' => 'Daily transaction limit exceeded',
                    'errors' => [
                        'amount' => ['Maximum ' . $this->maxDailyTransactions . ' transactions per day allowed']
                    ]
                ], 422);
            }

            DB::beginTransaction();
            
            try {
                // Lock both user records
                $sender = User::where('id', $user->id)->lockForUpdate()->first();
                $recipient = User::where('phone', $validated['recipient_phone'])->lockForUpdate()->first();
                
                if (!$recipient) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Recipient not found',
                        'errors' => [
                            'recipient_phone' => ['User with this phone number does not exist.']
                        ]
                    ], 404);
                }
                
                // Check sender balance
                if ($sender->wallet_balance < $amount) {
                    DB::rollBack();
                    
                    Log::warning('Insufficient balance for transfer', [
                        'sender_id' => $sender->id,
                        'sender_balance' => $sender->wallet_balance,
                        'amount' => $amount,
                        'recipient_phone' => $validated['recipient_phone']
                    ]);
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient balance',
                        'errors' => [
                            'amount' => ['Insufficient wallet balance. Current balance: ₹' . number_format($sender->wallet_balance, 2)]
                        ]
                    ], 422);
                }
                
                // Check recipient balance limit
                if ($recipient->wallet_balance + $amount > $this->maxWalletBalance) {
                    DB::rollBack();
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'Recipient wallet limit would be exceeded',
                        'errors' => [
                            'amount' => ['Transfer would exceed recipient\'s maximum wallet balance limit.']
                        ]
                    ], 422);
                }
                
                $referenceId = $this->generateReferenceId();
                
                // Atomic deduction from sender
                $senderUpdated = User::where('id', $sender->id)
                    ->where('wallet_balance', '>=', $amount)
                    ->update(['wallet_balance' => DB::raw('wallet_balance - ' . $amount)]);
                
                if (!$senderUpdated) {
                    DB::rollBack();
                    throw new \Exception('Failed to deduct from sender');
                }
                
                // Atomic addition to recipient
                $recipientUpdated = User::where('id', $recipient->id)
                    ->update(['wallet_balance' => DB::raw('wallet_balance + ' . $amount)]);
                
                if (!$recipientUpdated) {
                    DB::rollBack();
                    throw new \Exception('Failed to credit recipient');
                }
                
                $sender->refresh();
                $recipient->refresh();
                
                $debitTransaction = WalletTransaction::create([
                    'user_id' => $sender->id,
                    'amount' => $amount,
                    'type' => 'debit',
                    'reason' => 'Transfer to ' . $recipient->phone . ': ' . $reason,
                    'reference_id' => $referenceId,
                    'status' => 'completed',
                    'notes' => $notes
                ]);
                
                $creditTransaction = WalletTransaction::create([
                    'user_id' => $recipient->id,
                    'amount' => $amount,
                    'type' => 'credit',
                    'reason' => 'Transfer from ' . $sender->phone . ': ' . $reason,
                    'reference_id' => $referenceId,
                    'status' => 'completed',
                    'notes' => $notes
                ]);
                
                DB::commit();
                
                // Clear caches
                Cache::forget('wallet_balance_' . $sender->id);
                Cache::forget('wallet_balance_' . $recipient->id);
                Cache::put($rateLimitKey, now()->timestamp, now()->addMinutes($this->transferCooldownMinutes));
                
                // Send notifications
                $this->sendNotification($sender->id, [
                    'title' => 'Money Sent',
                    'message' => '₹' . number_format($amount, 2) . ' has been sent to ' . $recipient->phone,
                    'type' => 'transfer_debit',
                    'data' => ['amount' => $amount, 'recipient' => $recipient->phone, 'transaction_id' => $debitTransaction->id]
                ]);
                
                $this->sendNotification($recipient->id, [
                    'title' => 'Money Received',
                    'message' => '₹' . number_format($amount, 2) . ' has been received from ' . $sender->phone,
                    'type' => 'transfer_credit',
                    'data' => ['amount' => $amount, 'sender' => $sender->phone, 'transaction_id' => $creditTransaction->id]
                ]);

                Log::info('Money transferred successfully', [
                    'sender_id' => $sender->id,
                    'recipient_id' => $recipient->id,
                    'amount' => $amount,
                    'reference_id' => $referenceId,
                    'ip' => $request->ip()
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Money transferred successfully',
                    'data' => [
                        'amount' => $amount,
                        'formatted_amount' => '₹' . number_format($amount, 2),
                        'recipient_phone' => $recipient->phone,
                        'recipient_name' => $recipient->name,
                        'reference_id' => $referenceId,
                        'new_balance' => (float) $sender->wallet_balance,
                        'formatted_balance' => '₹' . number_format($sender->wallet_balance, 2)
                    ]
                ], 200);

            } catch (QueryException $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (ValidationException $e) {
            Log::warning('Transfer validation failed', [
                'errors' => array_keys($e->errors()),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
            
        } catch (QueryException $e) {
            Log::error('Database error transferring money', [
                'user_id' => auth()->id(),
                'error_code' => $e->getCode()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to transfer money',
                'error' => 'Database error occurred'
            ], 500);
            
        } catch (Exception $e) {
            Log::error('Unexpected error transferring money', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to transfer money',
                'error' => 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Initialize a payment order for wallet recharge via Cashfree
     */
    public function initiateRecharge(Request $request)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $validated = $request->validate([
                'amount' => 'required|numeric|min:' . $this->minAddAmount . '|max:' . $this->maxAddAmount,
                'payment_method' => 'nullable|string|in:card,upi,nb'
            ]);

            $amount = (float) $validated['amount'];
            
            // Check wallet limit
            if ($user->wallet_balance + $amount > $this->maxWalletBalance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Amount exceeds maximum wallet limit',
                    'errors' => [
                        'amount' => ['Maximum wallet balance is ₹' . number_format($this->maxWalletBalance, 2)]
                    ]
                ], 422);
            }
            
            // Rate limiting for recharge initiation
            $rateLimitKey = 'recharge_initiate_' . $user->id;
            $recentRequests = (int) Cache::get($rateLimitKey, 0);
            
            if ($recentRequests >= 5) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many recharge requests. Please try again later.',
                ], 429);
            }

            // Generate unique order ID
            $orderId = 'WALLET_' . strtoupper(uniqid()) . '_' . $user->id . '_' . time();

            // Prepare order data for Cashfree
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
                    'return_url' => url('/wallet?payment_status=completed'),
                    'notify_url' => url('/api/webhooks/cashfree/payment'),
                    'if_required' => true
                ]
            ];

            // Create order via Cashfree
            $order = $this->cashfreeService->createPaymentOrder($orderData);
            
            if (!$order['success']) {
                Log::error('Cashfree order creation failed', [
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'error' => $order['error'] ?? 'Unknown error'
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => $order['error'] ?? 'Failed to create payment order',
                    'error' => 'Payment gateway error'
                ], 500);
            }

            // Store pending transaction
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
            
            // Increment rate limit
            Cache::put($rateLimitKey, $recentRequests + 1, now()->addMinutes(1));

            Log::info('Wallet recharge initiated', [
                'user_id' => $user->id,
                'amount' => $amount,
                'order_id' => $orderId,
                'payment_session_id' => $order['payment_session_id'] ?? null,
                'transaction_id' => $pendingTransaction->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment order created',
                'data' => [
                    'order_id' => $orderId,
                    'amount' => $amount,
                    'formatted_amount' => '₹' . number_format($amount, 2),
                    'payment_session_id' => $order['payment_session_id'],
                    'transaction_id' => $pendingTransaction->id,
                    'expires_in' => 30 // minutes
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Failed to initiate recharge', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate payment',
                'error' => 'Payment service error'
            ], 500);
        }
    }

    /**
     * Check payment status (polling endpoint for mobile app)
     */
    public function checkPaymentStatus(Request $request)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $validated = $request->validate([
                'order_id' => 'required|string'
            ]);

            $transaction = WalletTransaction::where(function($query) use ($validated) {
                    $query->where('reference_id', $validated['order_id'])
                          ->orWhere('payment_order_id', $validated['order_id']);
                })
                ->where('user_id', $user->id)
                ->first();
                
            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found'
                ], 404);
            }

            // Rate limiting for status checks
            $rateLimitKey = 'payment_status_' . $user->id . '_' . $transaction->id;
            $checkCount = (int) Cache::get($rateLimitKey, 0);
            
            if ($checkCount > 30) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many status checks. Please wait.',
                ], 429);
            }
            
            Cache::put($rateLimitKey, $checkCount + 1, now()->addMinutes(1));

            // If transaction is still pending, fetch latest status from Cashfree
            if ($transaction->status === 'pending' && $transaction->payment_order_id) {
                $orderStatus = $this->cashfreeService->getPaymentOrderStatus($transaction->payment_order_id);
                
                if ($orderStatus['success']) {
                    $paymentStatus = $orderStatus['order_status'];
                    
                    if ($paymentStatus === 'PAID') {
                        // Process successful payment
                        DB::beginTransaction();
                        try {
                            // Atomic wallet update
                            $updated = User::where('id', $transaction->user_id)
                                ->where(DB::raw('wallet_balance + ' . $transaction->amount), '<=', $this->maxWalletBalance)
                                ->update(['wallet_balance' => DB::raw('wallet_balance + ' . $transaction->amount)]);
                            
                            if (!$updated) {
                                throw new \Exception('Failed to update wallet balance');
                            }
                            
                            $transaction->status = 'completed';
                            $transaction->payment_details = json_encode($orderStatus);
                            $transaction->save();
                            
                            DB::commit();
                            
                            Cache::forget('wallet_balance_' . $user->id);
                            
                            // Send notification
                            $this->sendNotification($user->id, [
                                'title' => 'Wallet Recharged Successfully',
                                'message' => '₹' . number_format($transaction->amount, 2) . ' has been added to your wallet.',
                                'type' => 'payment_success',
                                'data' => ['amount' => $transaction->amount, 'transaction_id' => $transaction->id]
                            ]);
                            
                            Log::info('Payment completed via status check', [
                                'user_id' => $user->id,
                                'transaction_id' => $transaction->id,
                                'amount' => $transaction->amount
                            ]);
                        } catch (Exception $e) {
                            DB::rollBack();
                            Log::error('Failed to process payment via status check', [
                                'error' => $e->getMessage(),
                                'transaction_id' => $transaction->id
                            ]);
                        }
                    } elseif (in_array($paymentStatus, ['FAILED', 'CANCELLED', 'EXPIRED'])) {
                        $transaction->status = 'failed';
                        $transaction->payment_details = json_encode($orderStatus);
                        $transaction->save();
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'status' => $transaction->status,
                    'amount' => (float) $transaction->amount,
                    'formatted_amount' => '₹' . number_format($transaction->amount, 2),
                    'order_id' => $transaction->reference_id,
                    'created_at' => $transaction->created_at,
                    'updated_at' => $transaction->updated_at
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Failed to check payment status', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to check payment status'
            ], 500);
        }
    }

    /**
     * Get transaction details
     */
    public function transactionDetails($id)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            if (!is_numeric($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid transaction ID'
                ], 400);
            }

            try {
                $transaction = WalletTransaction::where('id', $id)
                    ->where('user_id', $user->id)
                    ->first();
                
                if (!$transaction) {
                    Log::warning('Transaction not found', [
                        'transaction_id' => $id,
                        'user_id' => $user->id
                    ]);
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'Transaction not found'
                    ], 404);
                }
            } catch (QueryException $e) {
                Log::error('Database error fetching transaction', [
                    'transaction_id' => $id,
                    'user_id' => $user->id,
                    'error_code' => $e->getCode()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch transaction',
                    'error' => 'Database error occurred'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatTransaction($transaction)
            ], 200);
            
        } catch (Exception $e) {
            Log::error('Unexpected error fetching transaction details', [
                'transaction_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch transaction',
                'error' => 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Get wallet statement for date range
     */
    public function statement(Request $request)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $validated = $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'format' => 'sometimes|in:json,csv'
            ]);

            $startDate = Carbon::parse($validated['start_date'])->startOfDay();
            $endDate = Carbon::parse($validated['end_date'])->endOfDay();
            
            // Limit date range to 1 year max
            if ($startDate->diffInDays($endDate) > 365) {
                return response()->json([
                    'success' => false,
                    'message' => 'Date range cannot exceed 365 days'
                ], 422);
            }

            try {
                $transactions = $user->walletTransactions()
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->where('status', 'completed')
                    ->orderBy('created_at', 'desc')
                    ->get();
                
                $statement = [
                    'user' => [
                        'name' => $user->name,
                        'phone' => $user->phone,
                        'email' => $user->email
                    ],
                    'period' => [
                        'start_date' => $startDate->format('Y-m-d'),
                        'end_date' => $endDate->format('Y-m-d')
                    ],
                    'opening_balance' => $this->getOpeningBalance($user->id, $startDate),
                    'closing_balance' => (float) $user->wallet_balance,
                    'transactions' => $transactions->map(function ($transaction) {
                        return $this->formatTransaction($transaction);
                    }),
                    'summary' => [
                        'total_credits' => (float) $transactions->where('type', 'credit')->sum('amount'),
                        'total_debits' => (float) $transactions->where('type', 'debit')->sum('amount'),
                        'net_change' => (float) ($transactions->where('type', 'credit')->sum('amount') - 
                                                  $transactions->where('type', 'debit')->sum('amount')),
                        'transaction_count' => $transactions->count()
                    ]
                ];
                
                // Return CSV if requested
                if (isset($validated['format']) && $validated['format'] === 'csv') {
                    return $this->exportStatementCSV($statement);
                }
                
            } catch (QueryException $e) {
                Log::error('Database error generating statement', [
                    'user_id' => $user->id,
                    'error_code' => $e->getCode()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to generate statement',
                    'error' => 'Database error occurred'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => $statement
            ], 200);
            
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Unexpected error generating statement', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate statement',
                'error' => 'An unexpected error occurred'
            ], 500);
        }
    }

    // ============================================
    // PRIVATE HELPER METHODS
    // ============================================

    /**
     * Format transaction for response
     */
    private function formatTransaction($transaction): array
    {
        return [
            'id' => $transaction->id,
            'amount' => (float) $transaction->amount,
            'formatted_amount' => '₹' . number_format($transaction->amount, 2),
            'type' => $transaction->type,
            'type_label' => $transaction->type === 'credit' ? 'Credit' : 'Debit',
            'type_icon' => $transaction->type === 'credit' ? 'arrow-up' : 'arrow-down',
            'type_color' => $transaction->type === 'credit' ? 'green' : 'red',
            'reason' => $transaction->reason,
            'status' => $transaction->status,
            'status_label' => ucfirst($transaction->status),
            'status_color' => $this->getStatusColor($transaction->status),
            'reference_id' => $transaction->reference_id,
            'payment_method' => $transaction->payment_method,
            'notes' => $transaction->notes,
            'created_at' => $transaction->created_at,
            'created_at_formatted' => $transaction->created_at->format('d M Y, h:i A'),
            'created_at_human' => $transaction->created_at->diffForHumans(),
            'updated_at' => $transaction->updated_at
        ];
    }

    /**
     * Get status color for display
     */
    private function getStatusColor(string $status): string
    {
        return match ($status) {
            'completed' => 'green',
            'pending' => 'yellow',
            'failed' => 'red',
            default => 'gray'
        };
    }

    /**
     * Get daily total for user (credits or debits)
     */
    private function getDailyTotal(int $userId, string $type): float
    {
        return (float) WalletTransaction::where('user_id', $userId)
            ->where('type', $type)
            ->where('status', 'completed')
            ->whereDate('created_at', Carbon::today())
            ->sum('amount');
    }

    /**
     * Get daily transaction count for user
     */
    private function getDailyTransactionCount(int $userId): int
    {
        return WalletTransaction::where('user_id', $userId)
            ->where('status', 'completed')
            ->whereDate('created_at', Carbon::today())
            ->count();
    }

    /**
     * Generate opening balance before a date
     */
    private function getOpeningBalance(int $userId, Carbon $startDate): float
    {
        try {
            $credits = (float) WalletTransaction::where('user_id', $userId)
                ->where('type', 'credit')
                ->where('status', 'completed')
                ->where('created_at', '<', $startDate)
                ->sum('amount');
                
            $debits = (float) WalletTransaction::where('user_id', $userId)
                ->where('type', 'debit')
                ->where('status', 'completed')
                ->where('created_at', '<', $startDate)
                ->sum('amount');
                
            return $credits - $debits;
        } catch (Exception $e) {
            Log::error('Error calculating opening balance', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Export statement as CSV
     */
    private function exportStatementCSV(array $statement): \Illuminate\Http\Response
    {
        $headers = ['Date', 'Type', 'Amount', 'Reason', 'Reference ID', 'Status'];
        
        $rows = [];
        foreach ($statement['transactions'] as $transaction) {
            $rows[] = [
                $transaction['created_at_formatted'],
                $transaction['type_label'],
                $transaction['formatted_amount'],
                $transaction['reason'],
                $transaction['reference_id'],
                $transaction['status_label']
            ];
        }
        
        $csvContent = "\xEF\xBB\xBF" . implode(',', $headers) . "\n";
        foreach ($rows as $row) {
            $escapedRow = array_map(function ($field) {
                if (is_string($field)) {
                    $field = str_replace('"', '""', $field);
                    if (strpos($field, ',') !== false || strpos($field, '"') !== false || strpos($field, "\n") !== false) {
                        return '"' . $field . '"';
                    }
                }
                return $field;
            }, $row);
            $csvContent .= implode(',', $escapedRow) . "\n";
        }
        
        $filename = "wallet_statement_{$statement['period']['start_date']}_{$statement['period']['end_date']}.csv";
        
        return response($csvContent)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Cache-Control', 'private, max-age=0, must-revalidate')
            ->header('Pragma', 'public');
    }

    /**
     * Sanitize user input
     */
    private function sanitizeInput(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }
        
        $cleaned = strip_tags($input);
        $cleaned = htmlspecialchars($cleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $cleaned = preg_replace('/[\x00-\x1F\x7F]/', '', $cleaned);
        
        return mb_substr($cleaned, 0, 500);
    }

    /**
     * Generate unique reference ID
     */
    private function generateReferenceId(): string
    {
        return 'TXN_' . strtoupper(uniqid()) . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));
    }

    /**
     * Send notification to user
     */
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
        } catch (Exception $e) {
            Log::error('Failed to send wallet notification', [
                'user_id' => $userId,
                'type' => $data['type'],
                'error' => $e->getMessage()
            ]);
        }
    }
}