<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailVerification;
use App\Models\PasswordReset;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class AuthController extends Controller
{
    // Security Constants
    protected $maxOtpAttempts = 5;

    protected $otpLockoutMinutes = 15;

    protected $maxEmailVerificationAttempts = 25;

    protected $emailVerificationLockoutHours = 1;

    protected $maxLoginAttempts = 5;

    protected $loginLockoutMinutes = 15;

    protected $minPasswordLength = 8;

    protected $tokenLength = 60;

    /**
     * Register a new user
     */
    public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|regex:/^[a-zA-Z\s\-]+$/',
                'phone' => 'required|string|max:20|regex:/^[0-9]{10,15}$/|unique:users,phone',
                'email' => 'nullable|email|max:255|unique:users,email',
                'password' => 'required|string|min:'.$this->minPasswordLength.'|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{'.$this->minPasswordLength.',}$/',
            ]);

            // Create user inside transaction (no email sending inside transaction)
            DB::beginTransaction();

            try {
                $user = User::create([
                    'name' => $this->sanitizeInput($validated['name']),
                    'phone' => $validated['phone'],
                    'email' => $validated['email'] ? $this->sanitizeInput($validated['email']) : null,
                    'password' => Hash::make($validated['password']),
                    'role' => 'user',
                    'wallet_balance' => 0,
                ]);

                DB::commit();
            } catch (QueryException $e) {
                DB::rollBack();
                throw $e;
            }

            // Send verification email AFTER transaction (outside, so mail failure doesn't rollback)
            $emailSent = false;
            if ($user->email) {
                try {
                    $this->sendVerificationEmail($user);
                    $emailSent = true;
                } catch (Exception $e) {
                    Log::error('Failed to send verification email after registration', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                    // Do not throw – registration still successful
                }
            }

            // Generate token
            try {
                $token = $user->createToken('auth_token', ['*'], now()->addDays(30))->plainTextToken;
            } catch (Exception $e) {
                Log::error('Token creation failed during registration', [
                    'user_id' => $user->id,
                    'error_code' => $e->getCode(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => $user->email
                        ? 'User registered successfully but token generation failed. Please verify your email.'
                        : 'User registered successfully but token generation failed.',
                    'data' => [
                        'user' => [
                            'id' => $user->id,
                            'name' => $user->name,
                            'phone' => $user->phone,
                            'email' => $user->email,
                            'role' => $user->role,
                            'wallet_balance' => (float) $user->wallet_balance,
                            'email_verified' => $user->hasVerifiedEmail(),
                        ],
                        'token' => null,
                        'requires_verification' => $user->email ? true : false,
                    ],
                ], 201);
            }

            // Prepare response
            $message = 'User registered successfully.';
            if ($user->email && ! $emailSent) {
                $message .= ' However, the verification email could not be sent. Please request a new verification link later.';
            } elseif ($user->email && $emailSent) {
                $message .= ' Please verify your email before logging in.';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'phone' => $user->phone,
                        'email' => $user->email,
                        'role' => $user->role,
                        'wallet_balance' => (float) $user->wallet_balance,
                        'email_verified' => $user->hasVerifiedEmail(),
                    ],
                    'token' => $token,
                    'requires_verification' => $user->email ? ! $user->hasVerifiedEmail() : false,
                ],
            ], 201);

        } catch (ValidationException $e) {
            Log::warning('Registration validation failed', [
                'errors' => array_keys($e->errors()),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (QueryException $e) {
            if ($e->errorInfo[1] == 1062 || str_contains($e->getMessage(), 'Duplicate entry')) {
                $field = $this->getDuplicateField($e->getMessage());

                return response()->json([
                    'success' => false,
                    'message' => 'Registration failed',
                    'errors' => [
                        $field => ['This '.$field.' is already registered.'],
                    ],
                ], 422);
            }

            Log::error('Registration database error', [
                'error_code' => $e->getCode(),
                'sql_state' => $e->errorInfo[0] ?? null,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Registration failed due to database error',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred during registration',
            ], 500);
        } catch (Exception $e) {
            Log::error('Unexpected registration error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred',
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
                'login' => 'required|string|max:255',
                'password' => 'required|string',
            ]);

            // Rate limiting check
            $rateLimitKey = 'login_attempts_'.strtolower($validated['login']);
            $attempts = (int) Cache::get($rateLimitKey, 0);

            if ($attempts >= $this->maxLoginAttempts) {
                $remainingMinutes = Cache::ttl($rateLimitKey) / 60;

                return response()->json([
                    'success' => false,
                    'message' => 'Too many login attempts. Please try again after '.ceil($remainingMinutes).' minutes.',
                ], 429);
            }

            // Determine if login is email or phone
            $field = filter_var($validated['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

            try {
                $user = User::where($field, $validated['login'])->first();
            } catch (QueryException $e) {
                Log::error('Database error during login', [
                    'login_field' => $field,
                    'error_code' => $e->getCode(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Login failed due to database error',
                    'error' => 'An error occurred during login',
                ], 500);
            }

            if (! $user) {
                Cache::put($rateLimitKey, $attempts + 1, now()->addMinutes($this->loginLockoutMinutes));

                Log::warning('Failed login attempt - user not found', [
                    'login' => $validated['login'],
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Authentication failed',
                    'errors' => [
                        'login' => ['The provided credentials are incorrect.'],
                    ],
                ], 401);
            }

            // Check if user is Google user without password
            if ($user->is_google_user && ! $user->hasPassword()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This account uses Google login. Please use "Continue with Google" instead.',
                    'errors' => [
                        'login' => ['Please use Google login for this account.'],
                    ],
                    'data' => [
                        'use_google_login' => true,
                        'email' => $user->email,
                    ],
                ], 401);
            }

            // CHECK EMAIL VERIFICATION FOR BOTH EMAIL AND PHONE LOGIN
            if ($user->email && ! $user->hasVerifiedEmail()) {
                Log::warning('Login attempt with unverified email', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'login_method' => $field,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Please verify your email address before logging in',
                    'errors' => [
                        'email' => ['Email not verified. Please check your inbox for verification link.'],
                    ],
                    'data' => [
                        'requires_verification' => true,
                        'email' => $user->email,
                        'user_id' => $user->id,
                    ],
                ], 403);
            }

            if (! Hash::check($validated['password'], $user->password)) {
                Cache::put($rateLimitKey, $attempts + 1, now()->addMinutes($this->loginLockoutMinutes));

                Log::warning('Failed login attempt - invalid password', [
                    'user_id' => $user->id,
                    'login' => $validated['login'],
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Authentication failed',
                    'errors' => [
                        'login' => ['The provided credentials are incorrect.'],
                    ],
                ], 401);
            }

            // Clear rate limit on successful login
            Cache::forget($rateLimitKey);

            try {
                $token = $user->createToken('auth_token', ['*'], now()->addDays(30))->plainTextToken;
            } catch (Exception $e) {
                Log::error('Token creation failed during login', [
                    'user_id' => $user->id,
                    'error_code' => $e->getCode(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Login failed due to token generation error',
                    'error' => 'Internal server error',
                ], 500);
            }

            Log::info('User logged in successfully', [
                'user_id' => $user->id,
                'login' => $validated['login'],
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => $this->formatUserData($user),
                    'token' => $token,
                    'token_expires_in' => 30, // days
                ],
            ], 200);
        } catch (ValidationException $e) {
            Log::warning('Login validation failed', [
                'errors' => array_keys($e->errors()),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            Log::error('Unexpected login error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Login failed',
                'error' => 'An unexpected error occurred',
            ], 500);
        }
    }

    /**
     * Send password reset OTP with rate limiting
     */
    public function sendPasswordResetOtp(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|exists:users,email',
            ]);

            // Rate limiting check
            $rateLimitKey = 'password_reset_otp_'.$validated['email'];
            $attempts = (int) Cache::get($rateLimitKey, 0);

            if ($attempts >= $this->maxOtpAttempts) {
                $remainingMinutes = Cache::ttl($rateLimitKey) / 60;

                return response()->json([
                    'success' => false,
                    'message' => 'Too many OTP requests. Please try again after '.ceil($remainingMinutes).' minutes.',
                ], 429);
            }

            $user = User::where('email', $validated['email'])->first();

            // Check if user is Google user without password
            if ($user->is_google_user && ! $user->hasPassword()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This account uses Google login. Please use "Continue with Google" to login.',
                    'errors' => [
                        'email' => ['Password reset is not available for Google-only accounts.'],
                    ],
                ], 400);
            }

            // Generate OTP (6 digits)
            $otp = sprintf('%06d', mt_rand(1, 999999));
            $token = Str::random($this->tokenLength);
            $expiresAt = Carbon::now()->addMinutes(15);

            // Store or update password reset record
            PasswordReset::updateOrCreate(
                ['email' => $user->email],
                [
                    'token' => $token,
                    'otp' => $otp,
                    'expires_at' => $expiresAt,
                    'is_used' => false,
                ]
            );

            // Send OTP via email
            try {
                $this->sendPasswordResetOtpEmail($user->email, $otp, $user->name);

                // Increment rate limit counter
                Cache::put($rateLimitKey, $attempts + 1, now()->addMinutes($this->otpLockoutMinutes));

                Log::info('Password reset OTP sent', [
                    'email' => $user->email,
                    'user_id' => $user->id,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'OTP sent to your email address',
                    'data' => [
                        'email' => $user->email,
                        'token' => $token,
                        'expires_in' => 15,
                    ],
                ], 200);
            } catch (Exception $e) {
                Log::error('Failed to send password reset OTP email', [
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send OTP email. Please try again later.',
                ], 500);
            }
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            Log::error('Error sending password reset OTP', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP',
                'error' => 'Internal server error',
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
                'token' => 'required|string|size:'.$this->tokenLength,
                'otp' => 'required|string|size:6',
                'password' => 'required|string|min:'.$this->minPasswordLength.'|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{'.$this->minPasswordLength.',}$/',
            ]);

            // Rate limiting for reset attempts
            $resetKey = 'password_reset_attempt_'.$validated['email'];
            $attempts = (int) Cache::get($resetKey, 0);

            if ($attempts >= 5) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many reset attempts. Please try again after 1 hour.',
                ], 429);
            }

            $passwordReset = PasswordReset::where('email', $validated['email'])
                ->where('token', $validated['token'])
                ->where('otp', $validated['otp'])
                ->where('is_used', false)
                ->first();

            if (! $passwordReset) {
                Cache::put($resetKey, $attempts + 1, now()->addHours(1));

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired OTP',
                    'errors' => [
                        'otp' => ['The OTP is invalid or has already been used.'],
                    ],
                ], 422);
            }

            if (Carbon::now()->gt($passwordReset->expires_at)) {
                return response()->json([
                    'success' => false,
                    'message' => 'OTP has expired',
                    'errors' => [
                        'otp' => ['The OTP has expired. Please request a new one.'],
                    ],
                ], 422);
            }

            DB::beginTransaction();

            try {
                $user = User::where('email', $validated['email'])->first();
                $user->password = Hash::make($validated['password']);
                $user->password_set_required = false;
                $user->save();

                // DELETE instead of marking as used (better security)
                $passwordReset->delete();

                // Revoke ALL user tokens for security
                $user->tokens()->delete();

                DB::commit();

                // Clear rate limit cache
                Cache::forget($resetKey);

                Log::info('Password reset successful', [
                    'email' => $user->email,
                    'user_id' => $user->id,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Password has been reset successfully. Please login with your new password.',
                ], 200);
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            Log::error('Error resetting password', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password',
                'error' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Resend password reset OTP with rate limiting
     */
    public function resendPasswordResetOtp(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|exists:users,email',
            ]);

            // Rate limiting check
            $rateLimitKey = 'password_reset_otp_'.$validated['email'];
            $attempts = (int) Cache::get($rateLimitKey, 0);

            if ($attempts >= $this->maxOtpAttempts) {
                $remainingMinutes = Cache::ttl($rateLimitKey) / 60;

                return response()->json([
                    'success' => false,
                    'message' => 'Too many OTP requests. Please try again after '.ceil($remainingMinutes).' minutes.',
                ], 429);
            }

            $user = User::where('email', $validated['email'])->first();

            // Check if user is Google user without password
            if ($user->is_google_user && ! $user->hasPassword()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This account uses Google login. Password reset is not available.',
                    'errors' => [
                        'email' => ['Password reset is not available for Google-only accounts.'],
                    ],
                ], 400);
            }

            $existingReset = PasswordReset::where('email', $user->email)
                ->where('is_used', false)
                ->first();

            $otp = sprintf('%06d', mt_rand(1, 999999));
            $token = $existingReset ? $existingReset->token : Str::random($this->tokenLength);
            $expiresAt = Carbon::now()->addMinutes(15);

            if ($existingReset) {
                $existingReset->update([
                    'otp' => $otp,
                    'expires_at' => $expiresAt,
                ]);
                $token = $existingReset->token;
            } else {
                PasswordReset::create([
                    'email' => $user->email,
                    'token' => $token,
                    'otp' => $otp,
                    'expires_at' => $expiresAt,
                    'is_used' => false,
                ]);
            }

            $this->sendPasswordResetOtpEmail($user->email, $otp, $user->name);

            // Increment rate limit counter
            Cache::put($rateLimitKey, $attempts + 1, now()->addMinutes($this->otpLockoutMinutes));

            Log::info('Password reset OTP resent', [
                'email' => $user->email,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'New OTP sent to your email address',
                'data' => [
                    'email' => $user->email,
                    'token' => $token,
                    'expires_in' => 15,
                ],
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            Log::error('Error resending password reset OTP', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to resend OTP',
                'error' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Send email verification with rate limiting
     */
    public function sendEmailVerification(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|exists:users,email',
            ]);

            // Rate limiting
            $rateLimitKey = 'email_verification_'.$validated['email'];
            $attempts = (int) Cache::get($rateLimitKey, 0);

            if ($attempts >= $this->maxEmailVerificationAttempts) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many verification requests. Please try again after '.$this->emailVerificationLockoutHours.' hour(s).',
                ], 429);
            }

            $user = User::where('email', $validated['email'])->first();

            if ($user->hasVerifiedEmail()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email already verified',
                ], 422);
            }

            $this->sendVerificationEmail($user);

            Cache::put($rateLimitKey, $attempts + 1, now()->addHours($this->emailVerificationLockoutHours));

            return response()->json([
                'success' => true,
                'message' => 'Verification email sent successfully',
                'data' => [
                    'email' => $user->email,
                    'expires_in' => 24,
                ],
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            Log::error('Error sending verification email', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification email',
                'error' => 'Internal server error',
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
                'otp' => 'required|string|size:6',
            ]);

            $user = User::where('email', $validated['email'])->first();

            if ($user->hasVerifiedEmail()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email already verified',
                ], 422);
            }

            $verification = EmailVerification::where('user_id', $user->id)
                ->where('otp', $validated['otp'])
                ->where('is_used', false)
                ->first();

            if (! $verification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid OTP',
                    'errors' => [
                        'otp' => ['The OTP is invalid.'],
                    ],
                ], 422);
            }

            if (Carbon::now()->gt($verification->expires_at)) {
                return response()->json([
                    'success' => false,
                    'message' => 'OTP has expired',
                    'errors' => [
                        'otp' => ['The OTP has expired. Please request a new verification email.'],
                    ],
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
                    'email' => $user->email,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Email verified successfully',
                ], 200);
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            Log::error('Error verifying email with OTP', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to verify email',
                'error' => 'Internal server error',
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

            if (! $verification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired verification link',
                ], 422);
            }

            if (Carbon::now()->gt($verification->expires_at)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Verification link has expired',
                ], 422);
            }

            $user = $verification->user;

            if ($user->hasVerifiedEmail()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email already verified',
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
                    'email' => $user->email,
                ]);

                // Redirect to frontend success page
                return redirect()->to(config('app.frontend_url').'/email-verified?success=1');
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (Exception $e) {
            Log::error('Error verifying email with token', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->to(config('app.frontend_url').'/email-verified?error=1');
        }
    }

    /**
     * Resend email verification with rate limiting
     */
    public function resendEmailVerification(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|exists:users,email',
            ]);

            // Rate limiting
            $rateLimitKey = 'email_verification_'.$validated['email'];
            $attempts = (int) Cache::get($rateLimitKey, 0);

            if ($attempts >= $this->maxEmailVerificationAttempts) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many verification requests. Please try again after '.$this->emailVerificationLockoutHours.' hour(s).',
                ], 429);
            }

            $user = User::where('email', $validated['email'])->first();

            if ($user->hasVerifiedEmail()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email already verified',
                ], 422);
            }

            $this->sendVerificationEmail($user);

            Cache::put($rateLimitKey, $attempts + 1, now()->addHours($this->emailVerificationLockoutHours));

            return response()->json([
                'success' => true,
                'message' => 'Verification email resent successfully',
                'data' => [
                    'email' => $user->email,
                    'expires_in' => 24,
                ],
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            Log::error('Error resending verification email', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to resend verification email',
                'error' => 'Internal server error',
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

            if (! $user) {
                Log::warning('Logout attempt with no authenticated user', [
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            $currentToken = $user->currentAccessToken();

            if (! $currentToken) {
                Log::warning('Logout attempt with no current token', [
                    'user_id' => $user->id,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'No active session found',
                ], 400);
            }

            try {
                $currentToken->delete();
            } catch (QueryException $e) {
                Log::error('Database error during token deletion', [
                    'user_id' => $user->id,
                    'error_code' => $e->getCode(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Logout failed due to database error',
                    'error' => 'Database error occurred',
                ], 500);
            }

            Log::info('User logged out successfully', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully',
            ], 200);
        } catch (UnauthorizedHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        } catch (Exception $e) {
            Log::error('Unexpected logout error', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => 'An unexpected error occurred',
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

            if (! $user) {
                Log::warning('Unauthenticated access to user details', [
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            try {
                $userStats = [
                    'total_rentals' => $user->rentals()->count(),
                    'active_rentals' => $user->rentals()->where('status', 'active')->count(),
                    'completed_rentals' => $user->rentals()->where('status', 'completed')->count(),
                    'total_spent' => (float) $user->rentals()->where('status', 'completed')->sum('total_price'),
                ];
            } catch (QueryException $e) {
                Log::warning('Failed to load user statistics', [
                    'user_id' => $user->id,
                    'error_code' => $e->getCode(),
                ]);

                $userStats = [
                    'total_rentals' => 0,
                    'active_rentals' => 0,
                    'completed_rentals' => 0,
                    'total_spent' => 0,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatUserData($user, $userStats),
            ], 200);
        } catch (UnauthorizedHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        } catch (Exception $e) {
            Log::error('Failed to fetch user details', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user details',
                'error' => 'An unexpected error occurred',
            ], 500);
        }
    }

    /**
     * Change user password with session invalidation
     */
    public function changePassword(Request $request)
    {
        try {
            $validated = $request->validate([
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:'.$this->minPasswordLength.'|confirmed|different:current_password|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{'.$this->minPasswordLength.',}$/',
            ]);

            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            // Check if user has a password (for Google users who haven't set password)
            if (! $user->hasPassword()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You don\'t have a password set. Please use "Set Password" option instead.',
                    'errors' => [
                        'current_password' => ['No password set for this account.'],
                    ],
                ], 400);
            }

            if (! Hash::check($validated['current_password'], $user->password)) {
                Log::warning('Failed password change attempt - incorrect current password', [
                    'user_id' => $user->id,
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect',
                    'errors' => [
                        'current_password' => ['The current password is incorrect.'],
                    ],
                ], 401);
            }

            if (Hash::check($validated['new_password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'New password must be different from current password',
                    'errors' => [
                        'new_password' => ['New password must be different from current password.'],
                    ],
                ], 422);
            }

            try {
                $user->password = Hash::make($validated['new_password']);
                $user->password_set_required = false;
                $user->save();

                // CRITICAL SECURITY: Revoke ALL tokens and force re-login
                $user->tokens()->delete();

                // Create new token for current session
                $newToken = $user->createToken('auth_token', ['*'], now()->addDays(30))->plainTextToken;

                Log::info('User changed password successfully', [
                    'user_id' => $user->id,
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Password changed successfully. Please use the new token for future requests.',
                    'data' => [
                        'new_token' => $newToken,
                        'token_expires_in' => 30,
                    ],
                ], 200);
            } catch (QueryException $e) {
                Log::error('Database error during password change', [
                    'user_id' => $user->id,
                    'error_code' => $e->getCode(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update password due to database error',
                    'error' => 'Database error occurred',
                ], 500);
            }
        } catch (ValidationException $e) {
            Log::warning('Password change validation failed', [
                'errors' => array_keys($e->errors()),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (UnauthorizedHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        } catch (Exception $e) {
            Log::error('Unexpected password change error', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to change password',
                'error' => 'An unexpected error occurred',
            ], 500);
        }
    }

    /**
     * Refresh token (create new token, revoke old one)
     */
    public function refreshToken(Request $request)
    {
        try {
            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            try {
                // Revoke current token
                $request->user()->currentAccessToken()->delete();
            } catch (Exception $e) {
                Log::warning('Failed to revoke current token during refresh', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                $token = $user->createToken('auth_token', ['*'], now()->addDays(30))->plainTextToken;
            } catch (Exception $e) {
                Log::error('Token creation failed during refresh', [
                    'user_id' => $user->id,
                    'error_code' => $e->getCode(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to refresh token',
                    'error' => 'Internal server error',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully',
                'data' => [
                    'token' => $token,
                    'expires_in' => 30,
                ],
            ], 200);
        } catch (Exception $e) {
            Log::error('Token refresh error', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh token',
                'error' => 'An unexpected error occurred',
            ], 500);
        }
    }

    // =============================================
    // GOOGLE LOGIN METHODS
    // =============================================

    /**
     * Get Google OAuth URL for mobile app
     */
    public function getGoogleAuthUrl(Request $request)
    {
        try {
            // Generate state to prevent CSRF
            $state = Str::random(40);

            // Store state in cache (expires in 10 minutes)
            cache(["google_auth_state_{$state}" => true], now()->addMinutes(10));

            // Get redirect URL for mobile app to open in WebView
            $redirectUrl = Socialite::driver('google')
                ->stateless()
                ->with(['state' => $state])
                ->redirect()
                ->getTargetUrl();

            return response()->json([
                'success' => true,
                'data' => [
                    'auth_url' => $redirectUrl,
                    'state' => $state,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Google auth URL error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize Google login',
                'error' => 'Please try again',
            ], 500);
        }
    }

    /**
     * Handle Google OAuth callback
     * - Existing user: login and return token
     * - New user: store Google data in cache, return temp_token (phone required later)
     */
    public function handleGoogleCallback(Request $request)
    {
        try {
            Log::info('Google callback received', [
                'code_present' => $request->has('code'),
                'state_present' => $request->has('state'),
                'error' => $request->get('error'),
                'full_url' => $request->fullUrl(),
            ]);

            $validated = $request->validate([
                'code' => 'required|string',
                'state' => 'nullable|string',
                'device_name' => 'nullable|string|max:255',
            ]);

            // Verify state (CSRF protection)
            if ($validated['state']) {
                $stateKey = "google_auth_state_{$validated['state']}";
                if (! cache($stateKey)) {
                    Log::warning('Invalid Google auth state', ['state' => substr($validated['state'], 0, 20)]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid state parameter',
                    ], 400);
                }
                cache()->forget($stateKey);
            }

            // Get user from Google
            Log::info('Attempting to get Google user');
            $googleUser = Socialite::driver('google')
                ->stateless()
                ->user();

            Log::info('Google user retrieved', [
                'email' => $googleUser->getEmail(),
                'name' => $googleUser->getName(),
                'id' => $googleUser->getId(),
            ]);

            // Check if user already exists by email
            $existingUser = User::where('email', $googleUser->getEmail())->first();

            if ($existingUser) {
                // Existing user – link Google account if not already linked
                if (! $existingUser->google_id) {
                    $existingUser->update([
                        'google_id' => $googleUser->getId(),
                        'avatar' => $googleUser->getAvatar(),
                        'is_google_user' => true,
                        'google_verified_at' => now(),
                    ]);
                }
                // Auto‑verify email if not already
                if (! $existingUser->hasVerifiedEmail()) {
                    $existingUser->markEmailAsVerified();
                }

                // Generate API token
                $deviceName = $validated['device_name'] ?? 'google_auth';
                $token = $existingUser->createToken($deviceName, ['*'], now()->addDays(30))->plainTextToken;

                Log::info('Google login successful (existing user)', [
                    'user_id' => $existingUser->id,
                    'email' => $existingUser->email,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Login successful',
                    'data' => [
                        'user' => $this->formatUserData($existingUser),
                        'token' => $token,
                        'token_expires_in' => 30,
                        'is_new_user' => false,
                        'needs_password_setup' => $existingUser->needsPasswordSetup(),
                    ],
                ]);
            }

            // NEW USER – store Google data in cache with a temporary token
            $tempToken = Str::random(64);
            $googleData = [
                'google_id' => $googleUser->getId(),
                'name' => $googleUser->getName(),
                'email' => $googleUser->getEmail(),
                'avatar' => $googleUser->getAvatar(),
            ];

            Cache::put("google_temp_{$tempToken}", $googleData, now()->addMinutes(30));

            Log::info('New Google user – temp token created', ['email' => $googleUser->getEmail()]);

            return response()->json([
                'success' => false,
                'message' => 'Phone number required to complete registration',
                'data' => [
                    'temp_token' => $tempToken,
                    'google_data' => [
                        'name' => $googleUser->getName(),
                        'email' => $googleUser->getEmail(),
                        'avatar' => $googleUser->getAvatar(),
                    ],
                    'requires_phone' => true,
                ],
            ], 200); // 200 OK so the app can read the data

        } catch (Exception $e) {
            Log::error('Google callback error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Google authentication failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Please try again',
            ], 500);
        }
    }

    public function completeGoogleRegistration(Request $request)
    {
        $validated = $request->validate([
            'temp_token' => 'required|string|size:64',
            'phone' => 'required|string|min:10|max:15|regex:/^[0-9]{10,15}$/|unique:users,phone',
            'device_name' => 'nullable|string|max:255',
        ]);

        $googleData = Cache::get("google_temp_{$validated['temp_token']}");
        if (! $googleData) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired temporary token',
            ], 400);
        }

        // Check if phone already used
        $phoneUser = User::where('phone', $validated['phone'])->first();
        if ($phoneUser) {
            return response()->json([
                'success' => false,
                'message' => 'Phone number already registered',
                'errors' => ['phone' => ['This phone number is already in use.']],
            ], 422);
        }

        // Create the user
        $user = User::create([
            'name' => $googleData['name'],
            'email' => $googleData['email'],
            'phone' => $validated['phone'],
            'google_id' => $googleData['google_id'],
            'avatar' => $googleData['avatar'],
            'is_google_user' => true,
            'email_verified_at' => now(),
            'google_verified_at' => now(),
            'wallet_balance' => 0,
            'role' => 'user',
            'password' => null,
            'password_set_required' => true,
        ]);

        Cache::forget("google_temp_{$validated['temp_token']}");

        $token = $user->createToken($validated['device_name'] ?? 'google_auth', ['*'], now()->addDays(30))->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registration completed successfully',
            'data' => [
                'user' => $this->formatUserData($user),
                'token' => $token,
                'token_expires_in' => 30,
                'needs_password_setup' => true,
            ],
        ]);
    }

    /**
     * Set password for Google users (after registration)
     */
    public function setPasswordForGoogleUser(Request $request)
    {
        try {
            $validated = $request->validate([
                'password' => 'required|string|min:'.$this->minPasswordLength.'|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{'.$this->minPasswordLength.',}$/',
            ]);

            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            if (! $user->is_google_user) {
                return response()->json([
                    'success' => false,
                    'message' => 'This feature is only for Google users',
                ], 400);
            }

            if ($user->hasPassword()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password already set',
                ], 400);
            }

            $user->update([
                'password' => Hash::make($validated['password']),
                'password_set_required' => false,
            ]);

            Log::info('Password set for Google user', ['user_id' => $user->id]);

            return response()->json([
                'success' => true,
                'message' => 'Password set successfully. You can now login with email/password as well.',
                'data' => [
                    'user' => $this->formatUserData($user),
                ],
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            Log::error('Set password error', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to set password',
                'error' => 'Please try again',
            ], 500);
        }
    }

    /**
     * Link Google account to existing user account
     */
    public function linkGoogleAccount(Request $request)
    {
        try {
            $validated = $request->validate([
                'code' => 'required|string',
            ]);

            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            if ($user->google_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Google account already linked',
                ], 400);
            }

            $googleUser = Socialite::driver('google')
                ->stateless()
                ->user();

            // Check if this Google account is linked to another user
            $existingUser = User::where('google_id', $googleUser->getId())
                ->where('id', '!=', $user->id)
                ->first();

            if ($existingUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'This Google account is already linked to another user',
                ], 400);
            }

            // Link Google account
            $user->update([
                'google_id' => $googleUser->getId(),
                'avatar' => filter_var($googleUser->getAvatar(), FILTER_VALIDATE_URL) ? $googleUser->getAvatar() : null,
                'is_google_user' => true,
                'google_verified_at' => now(),
            ]);

            // Auto-verify email if not already
            if (! $user->hasVerifiedEmail() && $user->email) {
                $user->markEmailAsVerified();
            }

            return response()->json([
                'success' => true,
                'message' => 'Google account linked successfully',
                'data' => [
                    'user' => $this->formatUserData($user),
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Link Google account error', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to link Google account',
                'error' => 'Please try again',
            ], 500);
        }
    }

    /**
     * Unlink Google account from user account
     */
    public function unlinkGoogleAccount(Request $request)
    {
        try {
            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            if (! $user->google_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No Google account linked',
                ], 400);
            }

            // Check if user has password to login without Google
            if (! $user->hasPassword()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot unlink Google account. Please set a password first.',
                ], 400);
            }

            $user->update([
                'google_id' => null,
                'is_google_user' => false,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Google account unlinked successfully',
            ]);

        } catch (Exception $e) {
            Log::error('Unlink Google account error', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to unlink Google account',
            ], 500);
        }
    }

    // =============================================
    // PRIVATE HELPER METHODS
    // =============================================

    /**
     * Send verification email to user
     */
    private function sendVerificationEmail($user)
    {
        // Check if email_verifications table exists (optional safety)
        if (! Schema::hasTable('email_verifications')) {
            Log::error('email_verifications table does not exist');
            throw new Exception('Verification table not found');
        }

        $token = Str::random(60);
        $otp = sprintf('%06d', mt_rand(1, 999999));
        $expiresAt = Carbon::now()->addHours(24);

        EmailVerification::updateOrCreate(
            ['user_id' => $user->id],
            [
                'token' => $token,
                'otp' => $otp,
                'expires_at' => $expiresAt,
                'is_used' => false,
            ]
        );

        $verificationLink = route('verification.verify', ['token' => $token]); // adjust to your frontend URL if needed

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
                    <p>Dear ".htmlspecialchars($user->name).",</p>
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
                    <p>Dear ".htmlspecialchars($name).",</p>
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
     * Send welcome email to Google users
     */
    private function sendGoogleWelcomeEmail($user)
    {
        try {
            $subject = 'Welcome to Vehicle Rental System!';
            $htmlContent = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .button { background-color: #4F46E5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <h2>Welcome to Vehicle Rental System, ".htmlspecialchars($user->name).'!</h2>
                        <p>Your account has been created successfully using Google login.</p>
                        <p><strong>Email:</strong> '.htmlspecialchars($user->email).'</p>
                        <p><strong>Phone:</strong> '.htmlspecialchars($user->phone).'</p>
                        <p>To enhance security, please set a password for your account. This will allow you to login even without Google.</p>
                        <p>If you have any questions, feel free to contact our support team.</p>
                        <p>Happy Renting!<br>Vehicle Rental Team</p>
                    </div>
                </body>
                </html>
            ';

            Mail::send([], [], function ($message) use ($user, $subject, $htmlContent) {
                $message->to($user->email)
                    ->subject($subject)
                    ->html($htmlContent);
            });
        } catch (Exception $e) {
            Log::error('Failed to send Google welcome email', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Sanitize user input to prevent XSS
     */
    private function sanitizeInput(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $cleaned = strip_tags($input);
        $cleaned = htmlspecialchars($cleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $cleaned = preg_replace('/[\x00-\x1F\x7F]/', '', $cleaned);

        return mb_substr($cleaned, 0, 255);
    }

    /**
     * Format user data for response
     */
    private function formatUserData($user, $stats = null)
    {
        $data = [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'email' => $user->email,
            'role' => $user->role,
            'avatar' => $user->avatar ?? $this->getDefaultAvatar($user->name),
            'wallet_balance' => (float) $user->wallet_balance,
            'email_verified' => $user->hasVerifiedEmail(),
            'is_google_user' => (bool) $user->is_google_user,
            'has_password' => $user->hasPassword(),
            'needs_password_setup' => $user->needsPasswordSetup(),
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];

        if ($stats) {
            $data['statistics'] = $stats;
        }

        return $data;
    }

    /**
     * Get default avatar URL
     */
    private function getDefaultAvatar($name)
    {
        return 'https://ui-avatars.com/api/?name='.urlencode($name).'&background=4F46E5&color=fff';
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
