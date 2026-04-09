<?php
// app/Http/Controllers/Api/EmailVerificationController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\EmailVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmailVerificationController extends Controller
{
    /**
     * Send verification email after registration
     */
    public function sendVerificationEmail(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|exists:users,email'
            ]);
            
            $user = User::where('email', $validated['email'])->first();
            
            // Check if email is already verified
            if ($user->hasVerifiedEmail()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email already verified',
                    'errors' => [
                        'email' => ['This email is already verified.']
                    ]
                ], 422);
            }
            
            // Generate verification token and OTP
            $token = Str::random(60);
            $otp = sprintf("%06d", mt_rand(1, 999999));
            $expiresAt = Carbon::now()->addHours(24); // Valid for 24 hours
            
            // Store verification record
            EmailVerification::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'token' => $token,
                    'otp' => $otp,
                    'expires_at' => $expiresAt,
                    'is_used' => false
                ]
            );
            
            // Send verification email
            try {
                $this->sendVerificationEmailContent($user->email, $user->name, $otp, $token);
                
                Log::info('Verification email sent', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Verification email sent successfully',
                    'data' => [
                        'email' => $user->email,
                        'token' => $token,
                        'expires_in' => 24 // hours
                    ]
                ], 200);
                
            } catch (\Exception $e) {
                Log::error('Failed to send verification email', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send verification email. Please try again later.'
                ], 500);
            }
            
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
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
     * Verify email using OTP
     */
    public function verifyWithOtp(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|exists:users,email',
                'otp' => 'required|string|size:6'
            ]);
            
            $user = User::where('email', $validated['email'])->first();
            
            // Check if already verified
            if ($user->hasVerifiedEmail()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email already verified'
                ], 422);
            }
            
            // Find verification record
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
            
            // Check if expired
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
                // Mark email as verified
                $user->markEmailAsVerified();
                
                // Mark verification as used
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
                
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
            
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
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
     * Verify email using token (from link click)
     */
    public function verifyWithToken($token)
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
            
            // Check if expired
            if (Carbon::now()->gt($verification->expires_at)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Verification link has expired'
                ], 422);
            }
            
            $user = $verification->user;
            
            // Check if already verified
            if ($user->hasVerifiedEmail()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email already verified'
                ], 422);
            }
            
            DB::beginTransaction();
            
            try {
                // Mark email as verified
                $user->markEmailAsVerified();
                
                // Mark verification as used
                $verification->is_used = true;
                $verification->save();
                
                DB::commit();
                
                Log::info('Email verified via token', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);
                
                // Redirect to frontend success page or return JSON
                return response()->json([
                    'success' => true,
                    'message' => 'Email verified successfully. You can now login.'
                ], 200);
                
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
            
        } catch (\Exception $e) {
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
     * Resend verification email
     */
    public function resendVerification(Request $request)
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
            
            // Generate new OTP and token
            $newOtp = sprintf("%06d", mt_rand(1, 999999));
            $newToken = Str::random(60);
            $expiresAt = Carbon::now()->addHours(24);
            
            // Update existing verification record
            EmailVerification::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'token' => $newToken,
                    'otp' => $newOtp,
                    'expires_at' => $expiresAt,
                    'is_used' => false
                ]
            );
            
            // Send new verification email
            $this->sendVerificationEmailContent($user->email, $user->name, $newOtp, $newToken);
            
            Log::info('Verification email resent', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Verification email resent successfully',
                'data' => [
                    'email' => $user->email,
                    'token' => $newToken,
                    'expires_in' => 24
                ]
            ], 200);
            
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
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
     * Send verification email content
     */
    private function sendVerificationEmailContent($email, $name, $otp, $token)
    {
        $verificationLink = route('verification.verify', ['token' => $token]);
        
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
                    <p>Dear $name,</p>
                    <p>Thank you for registering. Please verify your email address to complete your registration.</p>
                    
                    <h3>Option 1: Use OTP</h3>
                    <div class='otp-code'>$otp</div>
                    <p>Enter this OTP in the app to verify your email. This OTP is valid for 24 hours.</p>
                    
                    <h3>Option 2: Click the Link</h3>
                    <p><a href='$verificationLink' class='button'>Verify Email Address</a></p>
                    <p>Or copy and paste this link: <br> <small>$verificationLink</small></p>
                    
                    <p>If you didn't create an account, please ignore this email.</p>
                    <div class='warning'>
                        <strong>Security Note:</strong> Never share your OTP or verification link with anyone.
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
}