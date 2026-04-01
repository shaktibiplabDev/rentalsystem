<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Exception;

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
                            'updated_at' => $user->updated_at
                        ];
                    } catch (QueryException $e) {
                        Log::error('Failed to load user statistics', [
                            'user_id' => $user->id,
                            'error' => $e->getMessage()
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
                            'error' => 'Failed to load statistics'
                        ];
                    } catch (Exception $e) {
                        Log::error('Unexpected error loading user statistics', [
                            'user_id' => $user->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
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
                            'error' => 'Failed to load statistics'
                        ];
                    }
                });

            if ($users->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'total' => 0,
                    'message' => 'No users found'
                ], 200);
            }

            return response()->json([
                'success' => true,
                'data' => $users,
                'total' => $users->count()
            ], 200);

        } catch (QueryException $e) {
            Log::error('Database error fetching users', [
                'error' => $e->getMessage(),
                'sql' => method_exists($e, 'getSql') ? $e->getSql() : null,
                'bindings' => method_exists($e, 'getBindings') ? $e->getBindings() : null
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred while fetching users',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        } catch (Exception $e) {
            Log::error('Failed to fetch users', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch users',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
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
                                    'error' => $e->getMessage()
                                ]);
                            }
                        }
                        
                        return [
                            'id' => $rental->id,
                            'user' => [
                                'id' => $rental->user->id ?? null,
                                'name' => $rental->user->name ?? 'N/A',
                                'email' => $rental->user->email ?? 'N/A'
                            ],
                            'vehicle' => [
                                'id' => $rental->vehicle->id ?? null,
                                'name' => $rental->vehicle->name ?? 'N/A',
                                'number_plate' => $rental->vehicle->number_plate ?? 'N/A',
                                'type' => $rental->vehicle->type ?? 'N/A'
                            ],
                            'customer' => [
                                'id' => $rental->customer->id ?? null,
                                'name' => $rental->customer->name ?? 'N/A',
                                'phone' => $rental->customer->phone ?? 'N/A',
                                'address' => $rental->customer->address ?? 'N/A'
                            ],
                            'start_time' => $rental->start_time,
                            'end_time' => $rental->end_time,
                            'duration_hours' => $durationHours,
                            'status' => $rental->status,
                            'total_price' => (float) ($rental->total_price ?? 0),
                            'created_at' => $rental->created_at,
                            'updated_at' => $rental->updated_at
                        ];
                    } catch (QueryException $e) {
                        Log::error('Database error loading rental details', [
                            'rental_id' => $rental->id,
                            'error' => $e->getMessage()
                        ]);
                        
                        return [
                            'id' => $rental->id,
                            'error' => 'Failed to load rental details: Database error'
                        ];
                    } catch (Exception $e) {
                        Log::error('Unexpected error loading rental details', [
                            'rental_id' => $rental->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                        
                        return [
                            'id' => $rental->id,
                            'error' => 'Failed to load rental details: ' . $e->getMessage()
                        ];
                    }
                });

            if ($rentals->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'total' => 0,
                    'message' => 'No rentals found'
                ], 200);
            }

            return response()->json([
                'success' => true,
                'data' => $rentals,
                'total' => $rentals->count()
            ], 200);

        } catch (QueryException $e) {
            Log::error('Database error fetching rentals', [
                'error' => $e->getMessage(),
                'sql' => method_exists($e, 'getSql') ? $e->getSql() : null,
                'bindings' => method_exists($e, 'getBindings') ? $e->getBindings() : null
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred while fetching rentals',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        } catch (Exception $e) {
            Log::error('Failed to fetch rentals', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch rentals',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
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
                'price' => 'required|numeric|min:0|max:999999.99'
            ]);

            try {
                $setting = Setting::updateOrCreate(
                    ['key' => 'verification_price'],
                    ['value' => (string) $validated['price']]
                );
            } catch (QueryException $e) {
                Log::error('Database error updating verification price', [
                    'error' => $e->getMessage(),
                    'price' => $validated['price']
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Database error occurred while updating verification price',
                    'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Verification price updated successfully',
                'data' => [
                    'key' => $setting->key,
                    'value' => (float) $setting->value
                ]
            ], 200);

        } catch (ValidationException $e) {
            Log::warning('Validation failed for verification price', [
                'errors' => $e->errors(),
                'input' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Failed to update verification price', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update verification price',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
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
                'minutes' => 'required|integer|min:1|max:120'
            ]);

            try {
                $setting = Setting::updateOrCreate(
                    ['key' => 'lease_threshold_minutes'],
                    ['value' => (string) $validated['minutes']]
                );
            } catch (QueryException $e) {
                Log::error('Database error updating lease threshold', [
                    'error' => $e->getMessage(),
                    'minutes' => $validated['minutes']
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Database error occurred while updating lease threshold',
                    'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Lease threshold updated successfully',
                'data' => [
                    'key' => $setting->key,
                    'value' => (int) $setting->value
                ]
            ], 200);

        } catch (ValidationException $e) {
            Log::warning('Validation failed for lease threshold', [
                'errors' => $e->errors(),
                'input' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Failed to update lease threshold', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update lease threshold',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
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
                        'error' => $e->getMessage()
                    ]);
                    return [$setting->key => $setting->value];
                }
            });

            if ($settings->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'No settings found'
                ], 200);
            }

            return response()->json([
                'success' => true,
                'data' => $settings
            ], 200);

        } catch (QueryException $e) {
            Log::error('Database error fetching settings', [
                'error' => $e->getMessage(),
                'sql' => method_exists($e, 'getSql') ? $e->getSql() : null
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred while fetching settings',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        } catch (Exception $e) {
            Log::error('Failed to fetch settings', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch settings',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
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
                'role' => 'required|in:user,admin'
            ]);

            try {
                $user = User::findOrFail($id);
            } catch (ModelNotFoundException $e) {
                Log::warning('User not found for role update', [
                    'user_id' => $id,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Prevent changing role of the last admin
            if ($user->role === 'admin' && $validated['role'] !== 'admin') {
                $adminCount = User::where('role', 'admin')->count();
                if ($adminCount <= 1) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot remove the last admin user'
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
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Database error occurred while updating user role',
                    'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'User role updated successfully',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role
                ]
            ], 200);

        } catch (ValidationException $e) {
            Log::warning('Validation failed for user role update', [
                'errors' => $e->errors(),
                'input' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Failed to update user role', [
                'user_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user role',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
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
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }
            
            // Prevent admin from deleting themselves
            if ($user->id === Auth::id()) {
                Log::warning('User attempted to delete own account', [
                    'user_id' => $id
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot delete your own account'
                ], 403);
            }
            
            // Prevent deletion of the last admin
            if ($user->role === 'admin') {
                $adminCount = User::where('role', 'admin')->count();
                if ($adminCount <= 1) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot delete the last admin user'
                    ], 403);
                }
            }
            
            // Check if user has associated rentals before deletion
            $rentalsCount = $user->rentals()->count();
            if ($rentalsCount > 0) {
                Log::info('Deleting user with rentals', [
                    'user_id' => $id,
                    'rentals_count' => $rentalsCount
                ]);
            }
            
            try {
                $user->delete();
            } catch (QueryException $e) {
                Log::error('Database error deleting user', [
                    'user_id' => $id,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Database error occurred while deleting user',
                    'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully'
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to delete user', [
                'user_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
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
                'error' => $e->getMessage()
            ]);
            return $value;
        }
    }
}