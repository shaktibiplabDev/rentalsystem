<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;
use Laravel\Sanctum\Exceptions\MissingAbilityException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'phone' => 'required|string|max:20|unique:users,phone',
                'email' => 'nullable|email|max:255|unique:users,email',
                'password' => 'required|string|min:6|confirmed'
            ]);

            DB::beginTransaction();
            
            try {
                $user = User::create([
                    'name' => $validated['name'],
                    'phone' => $validated['phone'],
                    'email' => $validated['email'] ?? null,
                    'password' => Hash::make($validated['password']),
                    'role' => 'user',
                    'wallet_balance' => 0
                ]);
                
                DB::commit();
            } catch (QueryException $e) {
                DB::rollBack();
                throw $e;
            }

            try {
                $token = $user->createToken('auth_token')->plainTextToken;
            } catch (Exception $e) {
                Log::error('Token creation failed during registration', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
                
                // User is created but token creation failed
                return response()->json([
                    'success' => true,
                    'message' => 'User registered successfully but token generation failed',
                    'data' => [
                        'user' => [
                            'id' => $user->id,
                            'name' => $user->name,
                            'phone' => $user->phone,
                            'email' => $user->email,
                            'role' => $user->role,
                            'wallet_balance' => (float) $user->wallet_balance
                        ],
                        'token' => null
                    ]
                ], 201);
            }

            return response()->json([
                'success' => true,
                'message' => 'User registered successfully',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'phone' => $user->phone,
                        'email' => $user->email,
                        'role' => $user->role,
                        'wallet_balance' => (float) $user->wallet_balance
                    ],
                    'token' => $token
                ]
            ], 201);

        } catch (ValidationException $e) {
            Log::warning('Registration validation failed', [
                'errors' => $e->errors(),
                'input' => $request->except('password', 'password_confirmation')
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
            
        } catch (QueryException $e) {
            // Check for duplicate entry error (MySQL error code 1062)
            if ($e->errorInfo[1] == 1062 || str_contains($e->getMessage(), 'Duplicate entry')) {
                $field = $this->getDuplicateField($e->getMessage());
                
                return response()->json([
                    'success' => false,
                    'message' => 'Registration failed',
                    'errors' => [
                        $field => ['This ' . $field . ' is already registered.']
                    ]
                ], 422);
            }
            
            Log::error('Registration database error', [
                'error' => $e->getMessage(),
                'sql' => method_exists($e, 'getSql') ? $e->getSql() : null,
                'bindings' => method_exists($e, 'getBindings') ? $e->getBindings() : null
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Registration failed due to database error',
                'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
            ], 500);
            
        } catch (Exception $e) {
            Log::error('Unexpected registration error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Login user
     */
    public function login(Request $request)
    {
        try {
            $validated = $request->validate([
                'phone' => 'required|string|max:20',
                'password' => 'required|string'
            ]);

            try {
                $user = User::where('phone', $validated['phone'])->first();
            } catch (QueryException $e) {
                Log::error('Database error during login', [
                    'phone' => $validated['phone'],
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Login failed due to database error',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
                ], 500);
            }

            if (!$user) {
                Log::warning('Failed login attempt - user not found', [
                    'phone' => $validated['phone'],
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication failed',
                    'errors' => [
                        'phone' => ['The provided credentials are incorrect.']
                    ]
                ], 401);
            }

            if (!Hash::check($validated['password'], $user->password)) {
                Log::warning('Failed login attempt - invalid password', [
                    'user_id' => $user->id,
                    'phone' => $validated['phone'],
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication failed',
                    'errors' => [
                        'phone' => ['The provided credentials are incorrect.']
                    ]
                ], 401);
            }

            // Revoke existing tokens (optional - uncomment if you want single session)
            // try {
            //     $user->tokens()->delete();
            // } catch (Exception $e) {
            //     Log::warning('Failed to revoke existing tokens', [
            //         'user_id' => $user->id,
            //         'error' => $e->getMessage()
            //     ]);
            // }

            try {
                $token = $user->createToken('auth_token')->plainTextToken;
            } catch (Exception $e) {
                Log::error('Token creation failed during login', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Login failed due to token generation error',
                    'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
                ], 500);
            }

            Log::info('User logged in successfully', [
                'user_id' => $user->id,
                'phone' => $user->phone,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'phone' => $user->phone,
                        'email' => $user->email,
                        'role' => $user->role,
                        'wallet_balance' => (float) $user->wallet_balance
                    ],
                    'token' => $token
                ]
            ], 200);

        } catch (ValidationException $e) {
            Log::warning('Login validation failed', [
                'errors' => $e->errors(),
                'input' => $request->except('password')
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
            
        } catch (Exception $e) {
            Log::error('Unexpected login error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Login failed',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Logout user (revoke current token)
     */
    public function logout(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                Log::warning('Logout attempt with no authenticated user', [
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            // Check if user has a current access token
            $currentToken = $request->user()->currentAccessToken();
            
            if (!$currentToken) {
                Log::warning('Logout attempt with no current token', [
                    'user_id' => $user->id
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'No active session found'
                ], 400);
            }
            
            try {
                $currentToken->delete();
            } catch (QueryException $e) {
                Log::error('Database error during token deletion', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Logout failed due to database error',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
                ], 500);
            }

            Log::info('User logged out successfully', [
                'user_id' => $user->id,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully'
            ], 200);
            
        } catch (UnauthorizedHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
            
        } catch (Exception $e) {
            Log::error('Unexpected logout error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Get authenticated user details
     */
    public function me(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                Log::warning('Unauthenticated access to user details', [
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Load additional relationships if needed
            try {
                $userStats = [
                    'total_rentals' => $user->rentals()->count(),
                    'active_rentals' => $user->rentals()->where('status', 'active')->count(),
                    'completed_rentals' => $user->rentals()->where('status', 'completed')->count(),
                    'total_spent' => (float) $user->rentals()->where('status', 'completed')->sum('total_price')
                ];
            } catch (QueryException $e) {
                Log::warning('Failed to load user statistics', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
                
                $userStats = [
                    'total_rentals' => 0,
                    'active_rentals' => 0,
                    'completed_rentals' => 0,
                    'total_spent' => 0
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'role' => $user->role,
                    'wallet_balance' => (float) $user->wallet_balance,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    'statistics' => $userStats
                ]
            ], 200);
            
        } catch (UnauthorizedHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
            
        } catch (Exception $e) {
            Log::error('Failed to fetch user details', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user details',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Change user password
     */
    public function changePassword(Request $request)
    {
        try {
            $validated = $request->validate([
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:6|confirmed'
            ]);

            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Verify current password
            if (!Hash::check($validated['current_password'], $user->password)) {
                Log::warning('Failed password change attempt - incorrect current password', [
                    'user_id' => $user->id,
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect',
                    'errors' => [
                        'current_password' => ['The current password is incorrect.']
                    ]
                ], 401);
            }

            // Prevent setting the same password
            if (Hash::check($validated['new_password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'New password must be different from current password',
                    'errors' => [
                        'new_password' => ['New password must be different from current password.']
                    ]
                ], 422);
            }

            try {
                $user->password = Hash::make($validated['new_password']);
                $user->save();
            } catch (QueryException $e) {
                Log::error('Database error during password change', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update password due to database error',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
                ], 500);
            }

            // Optional: Revoke all other tokens after password change
            try {
                $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();
            } catch (Exception $e) {
                Log::warning('Failed to revoke other tokens after password change', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }

            Log::info('User changed password successfully', [
                'user_id' => $user->id,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ], 200);

        } catch (ValidationException $e) {
            Log::warning('Password change validation failed', [
                'errors' => $e->errors(),
                'user_id' => $request->user()?->id
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
            
        } catch (UnauthorizedHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
            
        } catch (Exception $e) {
            Log::error('Unexpected password change error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to change password',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Refresh token (create new token, optionally revoke old one)
     */
    public function refreshToken(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Revoke current token
            try {
                $request->user()->currentAccessToken()->delete();
            } catch (Exception $e) {
                Log::warning('Failed to revoke current token during refresh', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }

            // Create new token
            try {
                $token = $user->createToken('auth_token')->plainTextToken;
            } catch (Exception $e) {
                Log::error('Token creation failed during refresh', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to refresh token',
                    'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully',
                'data' => [
                    'token' => $token
                ]
            ], 200);
            
        } catch (Exception $e) {
            Log::error('Token refresh error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh token',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Helper method to identify duplicate field from error message
     */
    protected function getDuplicateField(string $errorMessage): string
    {
        if (str_contains($errorMessage, 'users_phone_unique') || str_contains($errorMessage, "for key 'phone'")) {
            return 'phone';
        }
        
        if (str_contains($errorMessage, 'users_email_unique') || str_contains($errorMessage, "for key 'email'")) {
            return 'email';
        }
        
        return 'field';
    }
}