<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\CashfreeService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;
use Carbon\Carbon;

class WalletController extends Controller
{
    protected $cashfreeService;

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
                Log::warning('Unauthenticated access to wallet balance', [
                    'ip' => request()->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'balance' => (float) $user->wallet_balance,
                    'currency' => 'INR',
                    'formatted_balance' => '₹' . number_format($user->wallet_balance, 2)
                ]
            ], 200);

        } catch (QueryException $e) {
            Log::error('Database error fetching wallet balance', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch wallet balance',
                'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
            ], 500);
            
        } catch (Exception $e) {
            Log::error('Unexpected error fetching wallet balance', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch wallet balance',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
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

            try {
                $query = $user->walletTransactions();
                
                // Filter by type
                if ($request->has('type') && in_array($request->type, ['credit', 'debit'])) {
                    $query->where('type', $request->type);
                }
                
                // Filter by status
                if ($request->has('status') && in_array($request->status, ['pending', 'completed', 'failed'])) {
                    $query->where('status', $request->status);
                }
                
                // Filter by date range
                if ($request->has('start_date') && $request->has('end_date')) {
                    $request->validate([
                        'start_date' => 'required|date',
                        'end_date' => 'required|date|after_or_equal:start_date'
                    ]);
                    
                    $startDate = Carbon::parse($request->start_date)->startOfDay();
                    $endDate = Carbon::parse($request->end_date)->endOfDay();
                    $query->whereBetween('created_at', [$startDate, $endDate]);
                }
                
                // Pagination
                $perPage = $request->get('per_page', 20);
                $perPage = min(max($perPage, 1), 100);
                
                $transactions = $query->orderBy('created_at', 'desc')
                    ->paginate($perPage)
                    ->through(function ($transaction) {
                        try {
                            return [
                                'id' => $transaction->id,
                                'amount' => (float) $transaction->amount,
                                'formatted_amount' => '₹' . number_format($transaction->amount, 2),
                                'type' => $transaction->type,
                                'reason' => $transaction->reason,
                                'status' => $transaction->status ?? 'completed',
                                'reference_id' => $transaction->reference_id,
                                'created_at' => $transaction->created_at,
                                'created_at_human' => $transaction->created_at->diffForHumans(),
                                'created_at_formatted' => $transaction->created_at->format('d M Y, h:i A')
                            ];
                        } catch (Exception $e) {
                            Log::warning('Failed to format transaction', [
                                'transaction_id' => $transaction->id,
                                'error' => $e->getMessage()
                            ]);
                            
                            return [
                                'id' => $transaction->id,
                                'amount' => (float) $transaction->amount,
                                'type' => $transaction->type,
                                'reason' => $transaction->reason,
                                'created_at' => $transaction->created_at,
                                'error' => 'Failed to load full details'
                            ];
                        }
                    });
                
                $totalCredits = (float) $user->walletTransactions()
                    ->where('type', 'credit')
                    ->where('status', 'completed')
                    ->sum('amount');
                    
                $totalDebits = (float) $user->walletTransactions()
                    ->where('type', 'debit')
                    ->where('status', 'completed')
                    ->sum('amount');
                    
                // Get transaction statistics
                $stats = [
                    'total_credits' => $totalCredits,
                    'total_debits' => $totalDebits,
                    'current_balance' => (float) $user->wallet_balance,
                    'transaction_count' => $transactions->total(),
                    'pending_transactions' => $user->walletTransactions()
                        ->where('status', 'pending')
                        ->count(),
                    'average_credit' => $transactions->where('type', 'credit')->count() > 0 
                        ? round($totalCredits / $transactions->where('type', 'credit')->count(), 2) 
                        : 0,
                    'average_debit' => $transactions->where('type', 'debit')->count() > 0 
                        ? round($totalDebits / $transactions->where('type', 'debit')->count(), 2) 
                        : 0
                ];
                
            } catch (QueryException $e) {
                Log::error('Database error fetching wallet transactions', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch wallet transactions',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
                ], 500);
            }

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

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Unexpected error fetching wallet transactions', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch wallet transactions',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Add money to wallet (Direct method without payment gateway)
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
                'amount' => 'required|numeric|min:1|max:100000',
                'reason' => 'sometimes|string|max:255',
                'payment_method' => 'sometimes|string|in:credit_card,debit_card,upi,net_banking'
            ]);

            $amount = (float) $validated['amount'];
            $reason = $validated['reason'] ?? 'Wallet recharge';
            $paymentMethod = $validated['payment_method'] ?? 'other';

            // Check for maximum wallet balance limit
            $maxBalance = 1000000; // 10 Lakhs
            if ($user->wallet_balance + $amount > $maxBalance) {
                Log::warning('Wallet balance limit would be exceeded', [
                    'user_id' => $user->id,
                    'current_balance' => $user->wallet_balance,
                    'requested_amount' => $amount,
                    'max_limit' => $maxBalance
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Amount exceeds maximum wallet limit',
                    'errors' => [
                        'amount' => ['Adding this amount would exceed the maximum wallet balance limit of ₹' . number_format($maxBalance, 2)]
                    ]
                ], 422);
            }

            DB::beginTransaction();
            
            try {
                // Update wallet balance with locking to prevent race conditions
                $user = User::where('id', $user->id)->lockForUpdate()->first();
                
                if ($user->wallet_balance + $amount > $maxBalance) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Amount exceeds maximum wallet limit',
                        'errors' => [
                            'amount' => ['Adding this amount would exceed the maximum wallet balance limit.']
                        ]
                    ], 422);
                }
                
                $user->wallet_balance += $amount;
                $user->save();

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
                
            } catch (QueryException $e) {
                DB::rollBack();
                throw $e;
            }

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
                    'transaction' => [
                        'id' => $transaction->id,
                        'amount' => (float) $transaction->amount,
                        'formatted_amount' => '₹' . number_format($transaction->amount, 2),
                        'type' => $transaction->type,
                        'reason' => $transaction->reason,
                        'reference_id' => $transaction->reference_id,
                        'created_at' => $transaction->created_at
                    ],
                    'new_balance' => (float) $user->wallet_balance,
                    'formatted_balance' => '₹' . number_format($user->wallet_balance, 2)
                ]
            ], 200);

        } catch (ValidationException $e) {
            Log::warning('Add money validation failed', [
                'errors' => $e->errors(),
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
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to add money',
                'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
            ], 500);
            
        } catch (Exception $e) {
            Log::error('Unexpected error adding money', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to add money',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Deduct money from wallet
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
                'amount' => 'required|numeric|min:1|max:100000',
                'reason' => 'required|string|max:255',
                'reference_id' => 'sometimes|string|max:100'
            ]);

            $amount = (float) $validated['amount'];
            $reason = $validated['reason'];

            DB::beginTransaction();
            
            try {
                // Lock user record for update
                $user = User::where('id', $user->id)->lockForUpdate()->first();
                
                if ($user->wallet_balance < $amount) {
                    DB::rollBack();
                    
                    Log::warning('Insufficient balance for deduction', [
                        'user_id' => $user->id,
                        'current_balance' => $user->wallet_balance,
                        'requested_amount' => $amount,
                        'reason' => $reason
                    ]);
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient balance',
                        'errors' => [
                            'amount' => ['Insufficient wallet balance. Current balance: ₹' . number_format($user->wallet_balance, 2)]
                        ]
                    ], 422);
                }

                $user->wallet_balance -= $amount;
                $user->save();

                $transaction = WalletTransaction::create([
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'type' => 'debit',
                    'reason' => $reason,
                    'status' => 'completed',
                    'reference_id' => $validated['reference_id'] ?? $this->generateReferenceId()
                ]);
                
                DB::commit();
                
            } catch (QueryException $e) {
                DB::rollBack();
                throw $e;
            }

            Log::info('Money deducted from wallet', [
                'user_id' => $user->id,
                'amount' => $amount,
                'new_balance' => $user->wallet_balance,
                'reason' => $reason,
                'transaction_id' => $transaction->id,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Money deducted successfully',
                'data' => [
                    'transaction' => [
                        'id' => $transaction->id,
                        'amount' => (float) $transaction->amount,
                        'formatted_amount' => '₹' . number_format($transaction->amount, 2),
                        'type' => $transaction->type,
                        'reason' => $transaction->reason,
                        'reference_id' => $transaction->reference_id,
                        'created_at' => $transaction->created_at
                    ],
                    'new_balance' => (float) $user->wallet_balance,
                    'formatted_balance' => '₹' . number_format($user->wallet_balance, 2)
                ]
            ], 200);

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
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to deduct money',
                'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
            ], 500);
            
        } catch (Exception $e) {
            Log::error('Unexpected error deducting money', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to deduct money',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
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
                Log::warning('Unauthenticated transfer attempt', [
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $validated = $request->validate([
                'recipient_phone' => 'required|string|max:20|exists:users,phone',
                'amount' => 'required|numeric|min:1|max:100000',
                'reason' => 'sometimes|string|max:255',
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
            $reason = $validated['reason'] ?? 'Wallet transfer';
            $notes = $validated['notes'] ?? null;

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
                $maxBalance = 1000000;
                if ($recipient->wallet_balance + $amount > $maxBalance) {
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
                
                // Deduct from sender
                $sender->wallet_balance -= $amount;
                $sender->save();
                
                $debitTransaction = WalletTransaction::create([
                    'user_id' => $sender->id,
                    'amount' => $amount,
                    'type' => 'debit',
                    'reason' => 'Transfer to ' . $recipient->phone . ': ' . $reason,
                    'reference_id' => $referenceId,
                    'status' => 'completed',
                    'notes' => $notes
                ]);
                
                // Add to recipient
                $recipient->wallet_balance += $amount;
                $recipient->save();
                
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
                
            } catch (QueryException $e) {
                DB::rollBack();
                throw $e;
            }

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

        } catch (ValidationException $e) {
            Log::warning('Transfer validation failed', [
                'errors' => $e->errors(),
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
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to transfer money',
                'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
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
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Initialize a payment order for wallet recharge via Cashfree
     * Mobile app calls this to get payment_session_id
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
                'amount' => 'required|numeric|min:1|max:100000',
            ]);

            $amount = (float) $validated['amount'];
            
            // Check wallet limit
            $maxBalance = 1000000;
            if ($user->wallet_balance + $amount > $maxBalance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Amount exceeds maximum wallet limit of ₹' . number_format($maxBalance, 2)
                ], 422);
            }

            // Generate unique order ID
            $orderId = 'WALLET_' . strtoupper(uniqid()) . '_' . $user->id;

            // Prepare order data for Cashfree - NO return_url needed for mobile app!
            $orderData = [
                'order_id' => $orderId,
                'order_amount' => $amount,
                'order_currency' => 'INR',
                'order_note' => 'Wallet recharge for user: ' . $user->phone,
                'customer_details' => [
                    'customer_id' => 'USER_' . $user->id,
                    'customer_email' => $user->email,
                    'customer_phone' => $user->phone,
                    'customer_name' => $user->name
                ]
                // ⚠️ No return_url - mobile app uses webhook only!
            ];

            // Create order via Cashfree
            $order = $this->cashfreeService->createPaymentOrder($orderData);
            
            if (!$order['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $order['error'] ?? 'Failed to create payment order'
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
                'payment_session_id' => $order['payment_session_id'] ?? null
            ]);

            Log::info('Wallet recharge initiated', [
                'user_id' => $user->id,
                'amount' => $amount,
                'order_id' => $orderId,
                'payment_session_id' => $order['payment_session_id'] ?? null
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment order created',
                'data' => [
                    'order_id' => $orderId,
                    'amount' => $amount,
                    'formatted_amount' => '₹' . number_format($amount, 2),
                    'payment_session_id' => $order['payment_session_id'],
                    'transaction_id' => $pendingTransaction->id
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
                'error' => config('app.debug') ? $e->getMessage() : 'Payment service error'
            ], 500);
        }
    }

    /**
     * Check payment status (polling endpoint for mobile app)
     * Mobile app polls this every few seconds after initiating payment
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

            // If transaction is still pending, fetch latest status from Cashfree
            if ($transaction->status === 'pending' && $transaction->payment_order_id) {
                $orderStatus = $this->cashfreeService->getPaymentOrderStatus($transaction->payment_order_id);
                
                if ($orderStatus['success']) {
                    $paymentStatus = $orderStatus['order_status'];
                    
                    if ($paymentStatus === 'PAID') {
                        // Process successful payment
                        DB::beginTransaction();
                        try {
                            $user = User::where('id', $transaction->user_id)->lockForUpdate()->first();
                            $user->wallet_balance += $transaction->amount;
                            $user->save();
                            
                            $transaction->status = 'completed';
                            $transaction->payment_details = json_encode($orderStatus);
                            $transaction->save();
                            
                            DB::commit();
                            
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
                    'created_at' => $transaction->created_at
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
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch transaction',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $transaction->id,
                    'amount' => (float) $transaction->amount,
                    'formatted_amount' => '₹' . number_format($transaction->amount, 2),
                    'type' => $transaction->type,
                    'reason' => $transaction->reason,
                    'status' => $transaction->status,
                    'reference_id' => $transaction->reference_id,
                    'payment_method' => $transaction->payment_method,
                    'notes' => $transaction->notes,
                    'created_at' => $transaction->created_at,
                    'created_at_formatted' => $transaction->created_at->format('d M Y, h:i A'),
                    'created_at_human' => $transaction->created_at->diffForHumans()
                ]
            ], 200);
            
        } catch (Exception $e) {
            Log::error('Unexpected error fetching transaction details', [
                'transaction_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch transaction',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Get wallet statement
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
                        return [
                            'date' => $transaction->created_at->format('Y-m-d H:i:s'),
                            'type' => $transaction->type,
                            'amount' => (float) $transaction->amount,
                            'formatted_amount' => '₹' . number_format($transaction->amount, 2),
                            'reason' => $transaction->reason,
                            'reference_id' => $transaction->reference_id
                        ];
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
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to generate statement',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
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
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate statement',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Generate opening balance before a date
     */
    protected function getOpeningBalance($userId, $startDate)
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
    protected function exportStatementCSV($statement)
    {
        $headers = ['Date', 'Type', 'Amount', 'Reason', 'Reference ID'];
        
        $rows = [];
        foreach ($statement['transactions'] as $transaction) {
            $rows[] = [
                $transaction['date'],
                ucfirst($transaction['type']),
                $transaction['formatted_amount'],
                $transaction['reason'],
                $transaction['reference_id']
            ];
        }
        
        $csvContent = implode(',', $headers) . "\n";
        foreach ($rows as $row) {
            $csvContent .= implode(',', array_map(function ($field) {
                return '"' . str_replace('"', '""', $field) . '"';
            }, $row)) . "\n";
        }
        
        $filename = "wallet_statement_{$statement['period']['start_date']}_{$statement['period']['end_date']}.csv";
        
        return response($csvContent)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename={$filename}")
            ->header('Cache-Control', 'private, max-age=0, must-revalidate');
    }

    /**
     * Generate unique reference ID
     */
    protected function generateReferenceId()
    {
        return 'TXN_' . strtoupper(uniqid()) . '_' . date('YmdHis');
    }
}