<?php
// app/Http/Controllers/Api/ForgotPasswordController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ForgotPasswordController extends Controller
{
    /**
     * Send OTP to user's email for password reset
     */
    public function sendOtp(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|exists:users,email'
            ]);

            $user = User::where('email', $validated['email'])->first();
            
            // Generate OTP (6 digits)
            $otp = sprintf("%06d", mt_rand(1, 999999));
            $token = Str::random(60);
            $expiresAt = Carbon::now()->addMinutes(15); // OTP valid for 15 minutes

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
                $this->sendOtpEmail($user->email, $otp, $user->name);
                
                Log::info('Password reset OTP sent', [
                    'email' => $user->email,
                    'user_id' => $user->id
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'OTP sent to your email address',
                    'data' => [
                        'email' => $user->email,
                        'token' => $token, // Send token for the next step
                        'expires_in' => 15 // minutes
                    ]
                ], 200);
                
            } catch (\Exception $e) {
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
        } catch (\Exception $e) {
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
    public function verifyOtpAndReset(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|exists:users,email',
                'token' => 'required|string',
                'otp' => 'required|string|size:6',
                'password' => 'required|string|min:6|confirmed'
            ]);
            
            // Find the password reset record
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
            
            // Check if OTP has expired
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
                // Update user password
                $user = User::where('email', $validated['email'])->first();
                $user->password = Hash::make($validated['password']);
                $user->save();
                
                // Mark OTP as used
                $passwordReset->is_used = true;
                $passwordReset->save();
                
                // Optional: Revoke all user tokens for security
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
     * Resend OTP for password reset
     */
    public function resendOtp(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|exists:users,email'
            ]);
            
            $user = User::where('email', $validated['email'])->first();
            
            // Check if there's an existing OTP that's not used
            $existingReset = PasswordReset::where('email', $user->email)
                ->where('is_used', false)
                ->first();
            
            // Generate new OTP
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
            
            // Send new OTP via email
            $this->sendOtpEmail($user->email, $otp, $user->name);
            
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
        } catch (\Exception $e) {
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
     * Send OTP email
     */
    private function sendOtpEmail($email, $otp, $name)
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
                    <p>Dear $name,</p>
                    <p>We received a request to reset your password. Use the following OTP to complete the process:</p>
                    <div class='otp-code'>$otp</div>
                    <p>This OTP is valid for 15 minutes.</p>
                    <p>If you didn't request this, please ignore this email or contact support.</p>
                    <div class='warning'>
                        <strong>Security Note:</strong> Never share this OTP with anyone.
                    </div>
                </div>
            </body>
            </html>
        ";
        
        // Using Laravel's mail system
        Mail::send([], [], function ($message) use ($email, $subject, $htmlContent) {
            $message->to($email)
                    ->subject($subject)
                    ->html($htmlContent);
        });
    }
}