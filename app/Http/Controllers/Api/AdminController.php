<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerAccessLog;
use App\Models\Rental;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    /**
     * Get all users with their statistics
     */
    public function users()
    {
        try {
            $users = User::select('id', 'name', 'email', 'phone', 'role', 'wallet_balance', 'created_at', 'updated_at')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($user) {
                    try {
                        return [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                            'phone' => $user->phone,
                            'role' => $user->role,
                            'wallet_balance' => (float) $user->wallet_balance,
                            'total_vehicles' => $user->vehicles()->count(),
                            'total_rentals' => $user->rentals()->count(),
                            'total_earnings' => (float) $user->rentals()->where('status', 'completed')->sum('total_price'),
                            'created_at' => $user->created_at,
                            'updated_at' => $user->updated_at,
                        ];
                    } catch (QueryException $e) {
                        Log::error('Failed to load user statistics', [
                            'user_id' => $user->id,
                            'error' => $e->getMessage(),
                        ]);

                        return [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                            'phone' => $user->phone,
                            'role' => $user->role,
                            'wallet_balance' => (float) $user->wallet_balance,
                            'total_vehicles' => 0,
                            'total_rentals' => 0,
                            'total_earnings' => 0,
                            'created_at' => $user->created_at,
                            'updated_at' => $user->updated_at,
                            'error' => 'Failed to load statistics',
                        ];
                    } catch (Exception $e) {
                        Log::error('Unexpected error loading user statistics', [
                            'user_id' => $user->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);

                        return [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                            'phone' => $user->phone,
                            'role' => $user->role,
                            'wallet_balance' => (float) $user->wallet_balance,
                            'total_vehicles' => 0,
                            'total_rentals' => 0,
                            'total_earnings' => 0,
                            'created_at' => $user->created_at,
                            'updated_at' => $user->updated_at,
                            'error' => 'Failed to load statistics',
                        ];
                    }
                });

            if ($users->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'total' => 0,
                    'message' => 'No users found',
                ], 200);
            }

            return response()->json([
                'success' => true,
                'data' => $users,
                'total' => $users->count(),
            ], 200);

        } catch (QueryException $e) {
            Log::error('Database error fetching users', [
                'error' => $e->getMessage(),
                'sql' => method_exists($e, 'getSql') ? $e->getSql() : null,
                'bindings' => method_exists($e, 'getBindings') ? $e->getBindings() : null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Database error occurred while fetching users',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        } catch (Exception $e) {
            Log::error('Failed to fetch users', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch users',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get all rentals with their relationships
     */
    public function rentals()
    {
        try {
            $rentals = Rental::with(['user', 'vehicle', 'customer'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($rental) {
                    try {
                        $durationHours = null;
                        if ($rental->start_time && $rental->end_time) {
                            try {
                                $durationHours = ceil($rental->start_time->diffInHours($rental->end_time));
                            } catch (Exception $e) {
                                Log::warning('Failed to calculate rental duration', [
                                    'rental_id' => $rental->id,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }

                        return [
                            'id' => $rental->id,
                            'user' => [
                                'id' => $rental->user->id ?? null,
                                'name' => $rental->user->name ?? 'N/A',
                                'email' => $rental->user->email ?? 'N/A',
                            ],
                            'vehicle' => [
                                'id' => $rental->vehicle->id ?? null,
                                'name' => $rental->vehicle->name ?? 'N/A',
                                'number_plate' => $rental->vehicle->number_plate ?? 'N/A',
                                'type' => $rental->vehicle->type ?? 'N/A',
                            ],
                            'customer' => [
                                'id' => $rental->customer->id ?? null,
                                'name' => $rental->customer->name ?? 'N/A',
                                'phone' => $rental->customer->phone ?? 'N/A',
                                'address' => $rental->customer->address ?? 'N/A',
                            ],
                            'start_time' => $rental->start_time,
                            'end_time' => $rental->end_time,
                            'duration_hours' => $durationHours,
                            'status' => $rental->status,
                            'total_price' => (float) ($rental->total_price ?? 0),
                            'created_at' => $rental->created_at,
                            'updated_at' => $rental->updated_at,
                        ];
                    } catch (QueryException $e) {
                        Log::error('Database error loading rental details', [
                            'rental_id' => $rental->id,
                            'error' => $e->getMessage(),
                        ]);

                        return [
                            'id' => $rental->id,
                            'error' => 'Failed to load rental details: Database error',
                        ];
                    } catch (Exception $e) {
                        Log::error('Unexpected error loading rental details', [
                            'rental_id' => $rental->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);

                        return [
                            'id' => $rental->id,
                            'error' => 'Failed to load rental details: '.$e->getMessage(),
                        ];
                    }
                });

            if ($rentals->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'total' => 0,
                    'message' => 'No rentals found',
                ], 200);
            }

            return response()->json([
                'success' => true,
                'data' => $rentals,
                'total' => $rentals->count(),
            ], 200);

        } catch (QueryException $e) {
            Log::error('Database error fetching rentals', [
                'error' => $e->getMessage(),
                'sql' => method_exists($e, 'getSql') ? $e->getSql() : null,
                'bindings' => method_exists($e, 'getBindings') ? $e->getBindings() : null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Database error occurred while fetching rentals',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        } catch (Exception $e) {
            Log::error('Failed to fetch rentals', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch rentals',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Set verification price
     */
    public function setVerificationPrice(Request $request)
    {
        try {
            $validated = $request->validate([
                'price' => 'required|numeric|min:0|max:999999.99',
            ]);

            try {
                $setting = Setting::updateOrCreate(
                    ['key' => 'verification_price'],
                    ['value' => (string) $validated['price']]
                );
            } catch (QueryException $e) {
                Log::error('Database error updating verification price', [
                    'error' => $e->getMessage(),
                    'price' => $validated['price'],
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Database error occurred while updating verification price',
                    'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Verification price updated successfully',
                'data' => [
                    'key' => $setting->key,
                    'value' => (float) $setting->value,
                ],
            ], 200);

        } catch (ValidationException $e) {
            Log::warning('Validation failed for verification price', [
                'errors' => $e->errors(),
                'input' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            Log::error('Failed to update verification price', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update verification price',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Set lease threshold minutes
     */
    public function setLeaseThreshold(Request $request)
    {
        try {
            $validated = $request->validate([
                'minutes' => 'required|integer|min:1|max:120',
            ]);

            try {
                $setting = Setting::updateOrCreate(
                    ['key' => 'lease_threshold_minutes'],
                    ['value' => (string) $validated['minutes']]
                );
            } catch (QueryException $e) {
                Log::error('Database error updating lease threshold', [
                    'error' => $e->getMessage(),
                    'minutes' => $validated['minutes'],
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Database error occurred while updating lease threshold',
                    'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Lease threshold updated successfully',
                'data' => [
                    'key' => $setting->key,
                    'value' => (int) $setting->value,
                ],
            ], 200);

        } catch (ValidationException $e) {
            Log::warning('Validation failed for lease threshold', [
                'errors' => $e->errors(),
                'input' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            Log::error('Failed to update lease threshold', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update lease threshold',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get all settings
     */
    public function getSettings()
    {
        try {
            $settings = Setting::all()->mapWithKeys(function ($setting) {
                try {
                    return [$setting->key => $this->castSettingValue($setting->value, $setting->key)];
                } catch (Exception $e) {
                    Log::warning('Failed to cast setting value', [
                        'key' => $setting->key,
                        'value' => $setting->value,
                        'error' => $e->getMessage(),
                    ]);

                    return [$setting->key => $setting->value];
                }
            });

            if ($settings->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'No settings found',
                ], 200);
            }

            return response()->json([
                'success' => true,
                'data' => $settings,
            ], 200);

        } catch (QueryException $e) {
            Log::error('Database error fetching settings', [
                'error' => $e->getMessage(),
                'sql' => method_exists($e, 'getSql') ? $e->getSql() : null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Database error occurred while fetching settings',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        } catch (Exception $e) {
            Log::error('Failed to fetch settings', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch settings',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update user role
     */
    public function updateUserRole(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'role' => 'required|in:user,admin',
            ]);

            try {
                $user = User::findOrFail($id);
            } catch (ModelNotFoundException $e) {
                Log::warning('User not found for role update', [
                    'user_id' => $id,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                ], 404);
            }

            // Prevent changing role of the last admin
            if ($user->role === 'admin' && $validated['role'] !== 'admin') {
                $adminCount = User::where('role', 'admin')->count();
                if ($adminCount <= 1) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot remove the last admin user',
                    ], 403);
                }
            }

            try {
                $user->role = $validated['role'];
                $user->save();
            } catch (QueryException $e) {
                Log::error('Database error updating user role', [
                    'user_id' => $id,
                    'role' => $validated['role'],
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Database error occurred while updating user role',
                    'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'User role updated successfully',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role,
                ],
            ], 200);

        } catch (ValidationException $e) {
            Log::warning('Validation failed for user role update', [
                'errors' => $e->errors(),
                'input' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            Log::error('Failed to update user role', [
                'user_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update user role',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Delete user
     */
    public function deleteUser($id)
    {
        try {
            try {
                $user = User::findOrFail($id);
            } catch (ModelNotFoundException $e) {
                Log::warning('User not found for deletion', [
                    'user_id' => $id,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                ], 404);
            }

            // Prevent admin from deleting themselves
            if ($user->id === Auth::id()) {
                Log::warning('User attempted to delete own account', [
                    'user_id' => $id,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'You cannot delete your own account',
                ], 403);
            }

            // Prevent deletion of the last admin
            if ($user->role === 'admin') {
                $adminCount = User::where('role', 'admin')->count();
                if ($adminCount <= 1) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot delete the last admin user',
                    ], 403);
                }
            }

            // Check if user has associated rentals before deletion
            $rentalsCount = $user->rentals()->count();
            if ($rentalsCount > 0) {
                Log::info('Deleting user with rentals', [
                    'user_id' => $id,
                    'rentals_count' => $rentalsCount,
                ]);
            }

            try {
                $user->delete();
            } catch (QueryException $e) {
                Log::error('Database error deleting user', [
                    'user_id' => $id,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Database error occurred while deleting user',
                    'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully',
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to delete user', [
                'user_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Cast setting value based on key
     */
    protected function castSettingValue(string $value, string $key)
    {
        try {
            if (in_array($key, ['verification_price', 'lease_threshold_minutes'])) {
                return (float) $value;
            }

            return $value;
        } catch (Exception $e) {
            Log::error('Failed to cast setting value', [
                'key' => $key,
                'value' => $value,
                'error' => $e->getMessage(),
            ]);

            return $value;
        }
    }

    /**
     * Get user details by ID
     */
    public function userDetails($id)
    {
        try {
            // Validate ID is numeric
            if (!is_numeric($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid user ID',
                ], 400);
            }

            $user = User::with(['vehicles', 'rentals'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'wallet_balance' => (float) $user->wallet_balance,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    'vehicles_count' => $user->vehicles()->count(),
                    'rentals_count' => $user->rentals()->count(),
                    'total_earnings' => (float) $user->rentals()->where('status', 'completed')->sum('total_price'),
                ],
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        } catch (Exception $e) {
            Log::error('Failed to fetch user details', [
                'user_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user details',
            ], 500);
        }
    }

    /**
     * Get rental statistics
     */
    public function rentalStats(Request $request)
    {
        try {
            $query = Rental::query();

            // Apply date filters if provided with validation
            if ($request->has('start_date') && $request->has('end_date')) {
                $request->validate([
                    'start_date' => 'required|date',
                    'end_date' => 'required|date|after_or_equal:start_date',
                ]);

                $query->whereBetween('created_at', [
                    Carbon::parse($request->start_date)->startOfDay(),
                    Carbon::parse($request->end_date)->endOfDay(),
                ]);
            }

            $stats = [
                'total_rentals' => $query->count(),
                'active_rentals' => $query->where('status', 'active')->count(),
                'completed_rentals' => $query->where('status', 'completed')->count(),
                'cancelled_rentals' => $query->where('status', 'cancelled')->count(),
                'total_revenue' => (float) $query->where('status', 'completed')->sum('total_price'),
                'average_rental_value' => (float) $query->where('status', 'completed')->avg('total_price') ?? 0,
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            Log::error('Failed to fetch rental stats', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch rental stats',
            ], 500);
        }
    }

    /**
     * Get fraud alerts
     */
    public function fraudAlerts()
    {
        try {
            // Get rentals with potential fraud indicators
            $fraudAlerts = Rental::where(function ($query) {
                $query->where('damage_amount', '>', 5000) // High damage amount
                    ->orWhere('phase', 'cancelled')
                    ->orWhereHas('customer', function ($q) {
                        $q->whereNull('license_data'); // Unverified customers
                    });
            })
                ->with(['user', 'vehicle', 'customer'])
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get()
                ->map(function ($rental) {
                    return [
                        'id' => $rental->id,
                        'type' => $this->getFraudType($rental),
                        'severity' => $this->getFraudSeverity($rental),
                        'user' => $rental->user->name ?? 'N/A',
                        'vehicle' => $rental->vehicle->name ?? 'N/A',
                        'customer' => $rental->customer->name ?? 'N/A',
                        'created_at' => $rental->created_at,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $fraudAlerts,
            ], 200);
        } catch (Exception $e) {
            Log::error('Failed to fetch fraud alerts', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch fraud alerts',
            ], 500);
        }
    }

    /**
     * Force end a rental
     */
    public function forceEndRental($id)
    {
        try {
            if (!is_numeric($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid rental ID',
                ], 400);
            }

            $rental = Rental::findOrFail($id);

            if ($rental->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Rental is not active',
                ], 422);
            }

            $rental->update([
                'status' => 'completed',
                'phase' => 'completed',
                'end_time' => now(),
                'return_completed_at' => now(),
            ]);

            // Update vehicle status
            if ($rental->vehicle) {
                $rental->vehicle->update(['status' => 'available']);
            }

            Log::info('Rental force ended by admin', [
                'rental_id' => $id,
                'admin_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Rental force ended successfully',
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Rental not found',
            ], 404);
        } catch (Exception $e) {
            Log::error('Failed to force end rental', [
                'rental_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to force end rental',
            ], 500);
        }
    }

    /**
     * Get rental analytics
     */
    public function rentalAnalytics(Request $request)
    {
        try {
            $days = $request->get('days', 30);
            
            // Validate days range
            if ($days < 1 || $days > 365) {
                return response()->json([
                    'success' => false,
                    'message' => 'Days parameter must be between 1 and 365',
                ], 422);
            }
            
            $startDate = now()->subDays($days);

            $dailyStats = Rental::where('created_at', '>=', $startDate)
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('COUNT(*) as total'),
                    DB::raw('SUM(CASE WHEN status = "completed" THEN total_price ELSE 0 END) as revenue')
                )
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'daily_stats' => $dailyStats,
                    'period_days' => $days,
                    'total_rentals' => $dailyStats->sum('total'),
                    'total_revenue' => $dailyStats->sum('revenue'),
                ],
            ], 200);
        } catch (Exception $e) {
            Log::error('Failed to fetch rental analytics', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch rental analytics',
            ], 500);
        }
    }

    /**
     * Get user statistics
     */
    public function userStats()
    {
        try {
            $stats = [
                'total_users' => User::count(),
                'total_admins' => User::where('role', 'admin')->count(),
                'total_shop_owners' => User::where('role', 'user')->count(),
                'new_users_today' => User::whereDate('created_at', today())->count(),
                'new_users_this_week' => User::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                'new_users_this_month' => User::whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count(),
                'users_with_rentals' => User::has('rentals')->count(),
                'average_wallet_balance' => (float) User::avg('wallet_balance'),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ], 200);
        } catch (Exception $e) {
            Log::error('Failed to fetch user stats', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user stats',
            ], 500);
        }
    }

    /**
     * Get vehicle statistics
     */
    public function vehicleStats()
    {
        try {
            $stats = [
                'total_vehicles' => Vehicle::count(),
                'available_vehicles' => Vehicle::where('status', 'available')->count(),
                'on_rent_vehicles' => Vehicle::where('status', 'on_rent')->count(),
                'maintenance_vehicles' => Vehicle::where('status', 'unavailable')->count(),
                'vehicles_by_type' => Vehicle::select('type', DB::raw('count(*) as count'))->groupBy('type')->get(),
                'average_hourly_rate' => (float) Vehicle::avg('hourly_rate'),
                'average_daily_rate' => (float) Vehicle::avg('daily_rate'),
                'total_vehicles_value' => (float) Vehicle::sum(DB::raw('hourly_rate * 100')), // Estimated value
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ], 200);
        } catch (Exception $e) {
            Log::error('Failed to fetch vehicle stats', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch vehicle stats',
            ], 500);
        }
    }

    /**
     * Get earnings statistics
     */
    public function earningsStats(Request $request)
    {
        try {
            $period = $request->get('period', 'month');
            
            // Validate period
            $allowedPeriods = ['today', 'week', 'month', 'year'];
            if (!in_array($period, $allowedPeriods)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid period. Allowed: today, week, month, year',
                ], 422);
            }

            $query = Rental::where('status', 'completed');

            switch ($period) {
                case 'today':
                    $query->whereDate('created_at', today());
                    break;
                case 'week':
                    $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                    break;
                case 'month':
                    $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
                    break;
                case 'year':
                    $query->whereYear('created_at', now()->year);
                    break;
            }

            $stats = [
                'period' => $period,
                'total_earnings' => (float) $query->sum('total_price'),
                'total_rentals' => $query->count(),
                'average_earning_per_rental' => (float) $query->avg('total_price') ?? 0,
                'platform_fee_earned' => (float) $query->sum('verification_fee_deducted'),
                'estimated_tax' => (float) $query->sum('total_price') * 0.18, // 18% GST estimate
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ], 200);
        } catch (Exception $e) {
            Log::error('Failed to fetch earnings stats', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch earnings stats',
            ], 500);
        }
    }

    /**
     * Get dashboard statistics
     */
    public function dashboardStats()
    {
        try {
            $stats = [
                'total_users' => User::count(),
                'total_vehicles' => Vehicle::count(),
                'total_rentals' => Rental::count(),
                'active_rentals' => Rental::where('status', 'active')->count(),
                'total_revenue' => (float) Rental::where('status', 'completed')->sum('total_price'),
                'total_wallet_balance' => (float) User::sum('wallet_balance'),
                'pending_verifications' => Rental::where('phase', 'verification')->count(),
                'completed_rentals_today' => Rental::whereDate('created_at', today())->where('status', 'completed')->count(),
                'revenue_today' => (float) Rental::whereDate('created_at', today())->where('status', 'completed')->sum('total_price'),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ], 200);
        } catch (Exception $e) {
            Log::error('Failed to fetch dashboard stats', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard stats',
            ], 500);
        }
    }

    /**
     * Get verification statistics
     */
    public function verificationStats()
    {
        try {
            $stats = [
                'total_verifications' => Rental::whereNotNull('verification_completed_at')->count(),
                'cached_verifications' => Rental::where('is_verification_cached', true)->count(),
                'fresh_verifications' => Rental::where('is_verification_cached', false)->count(),
                'total_verification_fees_collected' => (float) Rental::sum('verification_fee_deducted'),
                'average_verification_time' => $this->getAverageVerificationTime(),
                'verifications_today' => Rental::whereDate('verification_completed_at', today())->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ], 200);
        } catch (Exception $e) {
            Log::error('Failed to fetch verification stats', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch verification stats',
            ], 500);
        }
    }

    /**
     * Get fraud statistics
     */
    public function fraudStats()
    {
        try {
            $stats = [
                'total_fraud_alerts' => Rental::where('damage_amount', '>', 5000)->count(),
                'cancelled_rentals' => Rental::where('status', 'cancelled')->count(),
                'cancellation_rate' => $this->getCancellationRate(),
                'high_damage_rentals' => Rental::where('damage_amount', '>', 10000)->count(),
                'unverified_customer_rentals' => Rental::whereHas('customer', function ($q) {
                    $q->whereNull('license_data');
                })->count(),
                'suspicious_activity_score' => $this->calculateSuspiciousActivityScore(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ], 200);
        } catch (Exception $e) {
            Log::error('Failed to fetch fraud stats', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch fraud stats',
            ], 500);
        }
    }

    /**
     * System health check
     */
    public function systemHealth()
    {
        try {
            $health = [
                'status' => 'healthy',
                'timestamp' => now()->toIso8601String(),
                'environment' => app()->environment(),
                'checks' => [
                    'database' => $this->checkDatabaseConnection(),
                    'cache' => $this->checkCacheConnection(),
                    'storage' => $this->checkStorageConnection(),
                    'queue' => $this->checkQueueConnection(),
                ],
            ];

            // Overall status
            foreach ($health['checks'] as $check) {
                if ($check['status'] !== 'healthy') {
                    $health['status'] = 'degraded';
                    break;
                }
            }

            return response()->json([
                'success' => true,
                'data' => $health,
            ], 200);
        } catch (Exception $e) {
            Log::error('System health check failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'data' => [
                    'status' => 'unhealthy',
                    'error' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Get system logs
     */
    public function systemLogs(Request $request)
    {
        try {
            $lines = $request->get('lines', 100);
            
            // Validate lines parameter
            if ($lines < 1 || $lines > 1000) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lines parameter must be between 1 and 1000',
                ], 422);
            }
            
            $logFile = storage_path('logs/laravel.log');

            if (!file_exists($logFile)) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'No log file found',
                ], 200);
            }

            $logs = [];
            $file = new \SplFileObject($logFile);
            $file->seek(PHP_INT_MAX);
            $totalLines = $file->key();

            $startLine = max(0, $totalLines - $lines);

            $file->seek($startLine);
            while (!$file->eof()) {
                $line = $file->fgets();
                if (trim($line)) {
                    $logs[] = $line;
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'logs' => $logs,
                    'total_lines' => count($logs),
                    'file_size' => round(filesize($logFile) / 1024, 2) . ' KB',
                ],
            ], 200);
        } catch (Exception $e) {
            Log::error('Failed to fetch logs', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch logs',
            ], 500);
        }
    }

    /**
     * Clear application cache
     */
    public function clearCache()
    {
        try {
            Cache::flush();
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            
            // Clear optimized files
            Artisan::call('optimize:clear');

            Log::info('Cache cleared by admin', [
                'admin_id' => auth()->id(),
                'admin_email' => auth()->user()->email,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cache cleared successfully',
                'data' => [
                    'cleared_at' => now()->toIso8601String(),
                ],
            ], 200);
        } catch (Exception $e) {
            Log::error('Failed to clear cache', [
                'error' => $e->getMessage(),
                'admin_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache',
            ], 500);
        }
    }

    /**
     * Get Cashfree status
     */
    public function cashfreeStatus()
    {
        try {
            // This would typically check the Cashfree API status
            $lastWebhook = Cache::get('last_cashfree_webhook');
            $webhookMinutesAgo = $lastWebhook ? now()->diffInMinutes(Carbon::parse($lastWebhook)) : null;

            return response()->json([
                'success' => true,
                'data' => [
                    'status' => 'operational',
                    'environment' => config('cashfree.environment', 'sandbox'),
                    'last_webhook_received' => $lastWebhook,
                    'webhook_minutes_ago' => $webhookMinutesAgo,
                    'api_configured' => !empty(config('cashfree.api_key')),
                ],
            ], 200);
        } catch (Exception $e) {
            Log::error('Failed to check Cashfree status', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to check Cashfree status',
            ], 500);
        }
    }

    /**
     * Get customer access logs
     */
    public function customerAccessLogs(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 50);
            
            // Validate per page
            if ($perPage < 1 || $perPage > 200) {
                return response()->json([
                    'success' => false,
                    'message' => 'Per page parameter must be between 1 and 200',
                ], 422);
            }

            $logs = CustomerAccessLog::with(['user', 'customer'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $logs,
            ], 200);
        } catch (Exception $e) {
            Log::error('Failed to fetch access logs', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch access logs',
            ], 500);
        }
    }

    /**
     * Get rental activity logs
     */
    public function rentalActivityLogs(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 50);
            
            // Validate per page
            if ($perPage < 1 || $perPage > 200) {
                return response()->json([
                    'success' => false,
                    'message' => 'Per page parameter must be between 1 and 200',
                ], 422);
            }

            $logs = Rental::with(['user', 'vehicle', 'customer'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $logs,
            ], 200);
        } catch (Exception $e) {
            Log::error('Failed to fetch rental activity', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch rental activity',
            ], 500);
        }
    }

    /**
     * Get user activity logs
     */
    public function userActivityLogs(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 50);
            
            // Validate per page
            if ($perPage < 1 || $perPage > 200) {
                return response()->json([
                    'success' => false,
                    'message' => 'Per page parameter must be between 1 and 200',
                ], 422);
            }

            $logs = User::withCount(['rentals', 'vehicles'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $logs,
            ], 200);
        } catch (Exception $e) {
            Log::error('Failed to fetch user activity', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user activity',
            ], 500);
        }
    }

    /**
     * Export audit logs
     */
    public function exportAuditLogs(Request $request)
    {
        try {
            $type = $request->get('type', 'customer_access');
            $format = $request->get('format', 'csv');
            $limit = $request->get('limit', 10000);
            
            // Validate type
            $allowedTypes = ['customer_access', 'rental_activity', 'user_activity'];
            if (!in_array($type, $allowedTypes)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid export type. Allowed: customer_access, rental_activity, user_activity',
                ], 422);
            }
            
            // Validate format
            if (!in_array($format, ['csv', 'json'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid format. Allowed: csv, json',
                ], 422);
            }
            
            // Validate limit
            if ($limit < 1 || $limit > 50000) {
                return response()->json([
                    'success' => false,
                    'message' => 'Limit parameter must be between 1 and 50000',
                ], 422);
            }

            $data = [];

            if ($type === 'customer_access') {
                $data = CustomerAccessLog::with(['user', 'customer'])
                    ->limit($limit)
                    ->get()
                    ->map(function ($log) {
                        return [
                            'id' => $log->id,
                            'customer_id' => $log->customer_id,
                            'customer_name' => $log->customer->name ?? 'N/A',
                            'user_id' => $log->user_id,
                            'user_name' => $log->user->name ?? 'N/A',
                            'action' => $log->action,
                            'created_at' => $log->created_at,
                        ];
                    });
            } elseif ($type === 'rental_activity') {
                $data = Rental::with(['user', 'vehicle', 'customer'])
                    ->limit($limit)
                    ->get()
                    ->map(function ($rental) {
                        return [
                            'id' => $rental->id,
                            'user_name' => $rental->user->name ?? 'N/A',
                            'vehicle_name' => $rental->vehicle->name ?? 'N/A',
                            'customer_name' => $rental->customer->name ?? 'N/A',
                            'status' => $rental->status,
                            'total_price' => $rental->total_price,
                            'created_at' => $rental->created_at,
                        ];
                    });
            } elseif ($type === 'user_activity') {
                $data = User::withCount(['rentals', 'vehicles'])
                    ->limit($limit)
                    ->get()
                    ->map(function ($user) {
                        return [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                            'role' => $user->role,
                            'rentals_count' => $user->rentals_count,
                            'vehicles_count' => $user->vehicles_count,
                            'created_at' => $user->created_at,
                        ];
                    });
            }

            if ($format === 'csv') {
                $csv = $this->arrayToCsv($data->toArray());
                $filename = $type . '_logs_' . date('Y-m-d_His') . '.csv';

                return response($csv)
                    ->header('Content-Type', 'text/csv; charset=UTF-8')
                    ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                    ->header('Cache-Control', 'private, max-age=0, must-revalidate');
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'total' => $data->count(),
                'exported_at' => now()->toIso8601String(),
            ], 200);
        } catch (Exception $e) {
            Log::error('Failed to export logs', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to export logs',
            ], 500);
        }
    }

    // =============================================
    // PRIVATE HELPER METHODS
    // =============================================

    /**
     * Get fraud type for a rental
     */
    private function getFraudType($rental): string
    {
        if ($rental->damage_amount > 10000) {
            return 'high_damage';
        }
        if ($rental->damage_amount > 5000) {
            return 'medium_damage';
        }
        if ($rental->status === 'cancelled') {
            return 'cancelled_rental';
        }
        if (!$rental->customer || !$rental->customer->license_data) {
            return 'unverified_customer';
        }

        return 'unknown';
    }

    /**
     * Get fraud severity for a rental
     */
    private function getFraudSeverity($rental): string
    {
        if ($rental->damage_amount > 10000) {
            return 'critical';
        }
        if ($rental->damage_amount > 5000) {
            return 'high';
        }
        if ($rental->status === 'cancelled') {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Get average verification time in minutes
     */
    private function getAverageVerificationTime(): ?float
    {
        $verifications = Rental::whereNotNull('verification_completed_at')
            ->whereNotNull('created_at')
            ->get();

        if ($verifications->isEmpty()) {
            return null;
        }

        $totalMinutes = $verifications->sum(function ($rental) {
            return $rental->created_at->diffInMinutes($rental->verification_completed_at);
        });

        return round($totalMinutes / $verifications->count(), 2);
    }

    /**
     * Get cancellation rate percentage
     */
    private function getCancellationRate(): float
    {
        $total = Rental::count();
        $cancelled = Rental::where('status', 'cancelled')->count();

        if ($total === 0) {
            return 0;
        }

        return round(($cancelled / $total) * 100, 2);
    }

    /**
     * Calculate suspicious activity score
     */
    private function calculateSuspiciousActivityScore(): float
    {
        $totalRentals = Rental::count();
        
        if ($totalRentals === 0) {
            return 0;
        }
        
        $suspiciousFactors = [
            Rental::where('damage_amount', '>', 5000)->count(),
            Rental::where('status', 'cancelled')->count(),
            Rental::whereHas('customer', function ($q) {
                $q->whereNull('license_data');
            })->count(),
        ];
        
        $totalSuspicious = array_sum($suspiciousFactors);
        
        return min(100, round(($totalSuspicious / $totalRentals) * 100, 2));
    }

    /**
     * Check database connection
     */
    private function checkDatabaseConnection(): array
    {
        try {
            DB::connection()->getPdo();
            $databaseName = DB::connection()->getDatabaseName();

            return [
                'status' => 'healthy',
                'message' => 'Database connected',
                'database' => $databaseName,
            ];
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check cache connection
     */
    private function checkCacheConnection(): array
    {
        try {
            $testKey = 'health_check_' . uniqid();
            Cache::put($testKey, true, 1);
            $result = Cache::get($testKey);
            Cache::forget($testKey);

            return [
                'status' => 'healthy',
                'message' => 'Cache working',
                'driver' => config('cache.default'),
            ];
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check storage connection
     */
    private function checkStorageConnection(): array
    {
        try {
            $testFile = 'health_check_' . uniqid() . '.txt';
            Storage::disk('local')->put($testFile, 'ok');
            $exists = Storage::disk('local')->exists($testFile);
            Storage::disk('local')->delete($testFile);

            return [
                'status' => 'healthy',
                'message' => 'Storage working',
                'disk' => config('filesystems.default'),
            ];
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check queue connection
     */
    private function checkQueueConnection(): array
    {
        try {
            $driver = config('queue.default');
            
            return [
                'status' => 'healthy',
                'message' => 'Queue configured',
                'driver' => $driver,
            ];
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Convert array to CSV
     */
    private function arrayToCsv(array $data): string
    {
        if (empty($data)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');
        
        // Add UTF-8 BOM for Excel compatibility
        fwrite($output, "\xEF\xBB\xBF");
        
        // Write headers
        fputcsv($output, array_keys((array) $data[0]));

        // Write data rows
        foreach ($data as $row) {
            $sanitizedRow = array_map(function ($field) {
                if (is_string($field)) {
                    // Remove any problematic characters
                    return strip_tags($field);
                }
                return $field;
            }, (array) $row);
            fputcsv($output, $sanitizedRow);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}