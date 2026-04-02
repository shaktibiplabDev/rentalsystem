<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\EmailVerification;
use App\Models\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;
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

                // Send verification email if email is provided
                if ($user->email) {
                    $this->sendVerificationEmail($user);
                }

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

                return response()->json([
                    'success' => true,
                    'message' => $user->email ? 'User registered successfully but token generation failed. Please verify your email.' : 'User registered successfully but token generation failed.',
                    'data' => [
                        'user' => [
                            'id' => $user->id,
                            'name' => $user->name,
                            'phone' => $user->phone,
                            'email' => $user->email,
                            'role' => $user->role,
                            'wallet_balance' => (float) $user->wallet_balance,
                            'email_verified' => $user->hasVerifiedEmail()
                        ],
                        'token' => null,
                        'requires_verification' => $user->email ? true : false
                    ]
                ], 201);
            }

            return response()->json([
                'success' => true,
                'message' => $user->email ? 'User registered successfully. Please verify your email before logging in.' : 'User registered successfully',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'phone' => $user->phone,
                        'email' => $user->email,
                        'role' => $user->role,
                        'wallet_balance' => (float) $user->wallet_balance,
                        'email_verified' => $user->hasVerifiedEmail()
                    ],
                    'token' => $token,
                    'requires_verification' => $user->email ? !$user->hasVerifiedEmail() : false
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
     * Login user (supports both email and phone)
     */
    public function login(Request $request)
    {
        try {
            $validated = $request->validate([
                'login' => 'required|string', // Can be email or phone
                'password' => 'required|string'
            ]);

            // Determine if login is email or phone
            $field = filter_var($validated['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

            try {
                $user = User::where($field, $validated['login'])->first();
            } catch (QueryException $e) {
                Log::error('Database error during login', [
                    'login' => $validated['login'],
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
                    'login' => $validated['login'],
                    'ip' => $request->ip()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Authentication failed',
                    'errors' => [
                        'login' => ['The provided credentials are incorrect.']
                    ]
                ], 401);
            }

            // CHECK EMAIL VERIFICATION FOR BOTH EMAIL AND PHONE LOGIN
            // If user has an email address and it's not verified, block login
            if ($user->email && !$user->hasVerifiedEmail()) {
                Log::warning('Login attempt with unverified email', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'login_method' => $field
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Please verify your email address before logging in',
                    'errors' => [
                        'email' => ['Email not verified. Please check your inbox for verification link.']
                    ],
                    'data' => [
                        'requires_verification' => true,
                        'email' => $user->email,
                        'user_id' => $user->id
                    ]
                ], 403);
            }

            if (!Hash::check($validated['password'], $user->password)) {
                Log::warning('Failed login attempt - invalid password', [
                    'user_id' => $user->id,
                    'login' => $validated['login'],
                    'ip' => $request->ip()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Authentication failed',
                    'errors' => [
                        'login' => ['The provided credentials are incorrect.']
                    ]
                ], 401);
            }

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
                'login' => $validated['login'],
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
                        'wallet_balance' => (float) $user->wallet_balance,
                        'email_verified' => $user->hasVerifiedEmail()
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
     * Send password reset OTP
     */
    public function sendPasswordResetOtp(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|exists:users,email'
            ]);

            $user = User::where('email', $validated['email'])->first();

            // Generate OTP (6 digits)
            $otp = sprintf("%06d", mt_rand(1, 999999));
            $token = Str::random(60);
            $expiresAt = Carbon::now()->addMinutes(15);

            // Store or update password reset record
            PasswordReset::updateOrCreate(
                ['email' => $user->email],
                [
                    'token' => $token,
                    'otp' => $otp,
                    'expires_at' => $expiresAt,
                    'is_used' => false
                ]
            );

            // Send OTP via email
            try {
                $this->sendPasswordResetOtpEmail($user->email, $otp, $user->name);

                Log::info('Password reset OTP sent', [
                    'email' => $user->email,
                    'user_id' => $user->id
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'OTP sent to your email address',
                    'data' => [
                        'email' => $user->email,
                        'token' => $token,
                        'expires_in' => 15
                    ]
                ], 200);
            } catch (Exception $e) {
                Log::error('Failed to send password reset OTP email', [
                    'email' => $user->email,
                    'error' => $e->getMessage()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send OTP email. Please try again later.'
                ], 500);
            }
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Error sending password reset OTP', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Verify OTP and reset password
     */
    public function resetPasswordWithOtp(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|exists:users,email',
                'token' => 'required|string',
                'otp' => 'required|string|size:6',
                'password' => 'required|string|min:6|confirmed'
            ]);

            $passwordReset = PasswordReset::where('email', $validated['email'])
                ->where('token', $validated['token'])
                ->where('otp', $validated['otp'])
                ->where('is_used', false)
                ->first();

            if (!$passwordReset) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired OTP',
                    'errors' => [
                        'otp' => ['The OTP is invalid or has already been used.']
                    ]
                ], 422);
            }

            if (Carbon::now()->gt($passwordReset->expires_at)) {
                return response()->json([
                    'success' => false,
                    'message' => 'OTP has expired',
                    'errors' => [
                        'otp' => ['The OTP has expired. Please request a new one.']
                    ]
                ], 422);
            }

            DB::beginTransaction();

            try {
                $user = User::where('email', $validated['email'])->first();
                $user->password = Hash::make($validated['password']);
                $user->save();

                $passwordReset->is_used = true;
                $passwordReset->save();

                // Revoke all user tokens for security
                $user->tokens()->delete();

                DB::commit();

                Log::info('Password reset successful', [
                    'email' => $user->email,
                    'user_id' => $user->id
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Password has been reset successfully. Please login with your new password.'
                ], 200);
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Error resetting password', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Resend password reset OTP
     */
    public function resendPasswordResetOtp(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|exists:users,email'
            ]);

            $user = User::where('email', $validated['email'])->first();

            $existingReset = PasswordReset::where('email', $user->email)
                ->where('is_used', false)
                ->first();

            $otp = sprintf("%06d", mt_rand(1, 999999));
            $token = $existingReset ? $existingReset->token : Str::random(60);
            $expiresAt = Carbon::now()->addMinutes(15);

            if ($existingReset) {
                $existingReset->update([
                    'otp' => $otp,
                    'expires_at' => $expiresAt
                ]);
                $token = $existingReset->token;
            } else {
                PasswordReset::create([
                    'email' => $user->email,
                    'token' => $token,
                    'otp' => $otp,
                    'expires_at' => $expiresAt,
                    'is_used' => false
                ]);
            }

            $this->sendPasswordResetOtpEmail($user->email, $otp, $user->name);

            Log::info('Password reset OTP resent', [
                'email' => $user->email,
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'New OTP sent to your email address',
                'data' => [
                    'email' => $user->email,
                    'token' => $token,
                    'expires_in' => 15
                ]
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Error resending password reset OTP', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to resend OTP',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Send email verification
     */
    public function sendEmailVerification(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|exists:users,email'
            ]);

            $user = User::where('email', $validated['email'])->first();

            if ($user->hasVerifiedEmail()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email already verified'
                ], 422);
            }

            $this->sendVerificationEmail($user);

            return response()->json([
                'success' => true,
                'message' => 'Verification email sent successfully',
                'data' => [
                    'email' => $user->email,
                    'expires_in' => 24
                ]
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Error sending verification email', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification email',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Verify email with OTP
     */
    public function verifyEmailWithOtp(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|exists:users,email',
                'otp' => 'required|string|size:6'
            ]);

            $user = User::where('email', $validated['email'])->first();

            if ($user->hasVerifiedEmail()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email already verified'
                ], 422);
            }

            $verification = EmailVerification::where('user_id', $user->id)
                ->where('otp', $validated['otp'])
                ->where('is_used', false)
                ->first();

            if (!$verification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid OTP',
                    'errors' => [
                        'otp' => ['The OTP is invalid.']
                    ]
                ], 422);
            }

            if (Carbon::now()->gt($verification->expires_at)) {
                return response()->json([
                    'success' => false,
                    'message' => 'OTP has expired',
                    'errors' => [
                        'otp' => ['The OTP has expired. Please request a new verification email.']
                    ]
                ], 422);
            }

            DB::beginTransaction();

            try {
                $user->markEmailAsVerified();
                $verification->is_used = true;
                $verification->save();

                DB::commit();

                Log::info('Email verified successfully', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Email verified successfully'
                ], 200);
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Error verifying email with OTP', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to verify email',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Verify email with token (from link click)
     */
    public function verifyEmailWithToken($token)
    {
        try {
            $verification = EmailVerification::where('token', $token)
                ->where('is_used', false)
                ->first();

            if (!$verification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired verification link'
                ], 422);
            }

            if (Carbon::now()->gt($verification->expires_at)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Verification link has expired'
                ], 422);
            }

            $user = $verification->user;

            if ($user->hasVerifiedEmail()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email already verified'
                ], 422);
            }

            DB::beginTransaction();

            try {
                $user->markEmailAsVerified();
                $verification->is_used = true;
                $verification->save();

                DB::commit();

                Log::info('Email verified via token', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Email verified successfully. You can now login.'
                ], 200);
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (Exception $e) {
            Log::error('Error verifying email with token', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to verify email',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Resend email verification
     */
    public function resendEmailVerification(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|exists:users,email'
            ]);

            $user = User::where('email', $validated['email'])->first();

            if ($user->hasVerifiedEmail()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email already verified'
                ], 422);
            }

            $this->sendVerificationEmail($user);

            return response()->json([
                'success' => true,
                'message' => 'Verification email resent successfully',
                'data' => [
                    'email' => $user->email,
                    'expires_in' => 24
                ]
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Error resending verification email', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to resend verification email',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
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
                    'email_verified' => $user->hasVerifiedEmail(),
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

            try {
                $request->user()->currentAccessToken()->delete();
            } catch (Exception $e) {
                Log::warning('Failed to revoke current token during refresh', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }

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
     * Send verification email to user
     */
    private function sendVerificationEmail($user)
    {
        $token = Str::random(60);
        $otp = sprintf("%06d", mt_rand(1, 999999));
        $expiresAt = Carbon::now()->addHours(24);

        EmailVerification::updateOrCreate(
            ['user_id' => $user->id],
            [
                'token' => $token,
                'otp' => $otp,
                'expires_at' => $expiresAt,
                'is_used' => false
            ]
        );

        $verificationLink = url("/api/verify-email/token/{$token}");

        $subject = 'Verify Your Email - Vehicle Rental System';
        $htmlContent = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .otp-code { font-size: 32px; font-weight: bold; color: #4F46E5; padding: 20px; background: #F3F4F6; text-align: center; border-radius: 8px; letter-spacing: 5px; }
                    .button { background-color: #4F46E5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; }
                    .warning { color: #DC2626; font-size: 14px; margin-top: 20px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <h2>Welcome to Vehicle Rental System!</h2>
                    <p>Dear {$user->name},</p>
                    <p>Thank you for registering. Please verify your email address to complete your registration.</p>
                    
                    <h3>Option 1: Use OTP</h3>
                    <div class='otp-code'>{$otp}</div>
                    <p>Enter this OTP in the app to verify your email. This OTP is valid for 24 hours.</p>
                    
                    <h3>Option 2: Click the Link</h3>
                    <p><a href='{$verificationLink}' class='button'>Verify Email Address</a></p>
                    <p>Or copy and paste this link: <br> <small>{$verificationLink}</small></p>
                    
                    <p>If you didn't create an account, please ignore this email.</p>
                    <div class='warning'>
                        <strong>Security Note:</strong> Never share your OTP or verification link with anyone.
                    </div>
                </div>
            </body>
            </html>
        ";

        Mail::send([], [], function ($message) use ($user, $subject, $htmlContent) {
            $message->to($user->email)
                ->subject($subject)
                ->html($htmlContent);
        });
    }

    /**
     * Send password reset OTP email
     */
    private function sendPasswordResetOtpEmail($email, $otp, $name)
    {
        $subject = 'Password Reset OTP - Vehicle Rental System';
        $htmlContent = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .otp-code { font-size: 32px; font-weight: bold; color: #4F46E5; padding: 20px; background: #F3F4F6; text-align: center; border-radius: 8px; letter-spacing: 5px; }
                    .warning { color: #DC2626; font-size: 14px; margin-top: 20px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <h2>Password Reset Request</h2>
                    <p>Dear {$name},</p>
                    <p>We received a request to reset your password. Use the following OTP to complete the process:</p>
                    <div class='otp-code'>{$otp}</div>
                    <p>This OTP is valid for 15 minutes.</p>
                    <p>If you didn't request this, please ignore this email or contact support.</p>
                    <div class='warning'>
                        <strong>Security Note:</strong> Never share this OTP with anyone.
                    </div>
                </div>
            </body>
            </html>
        ";

        Mail::send([], [], function ($message) use ($email, $subject, $htmlContent) {
            $message->to($email)
                ->subject($subject)
                ->html($htmlContent);
        });
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
