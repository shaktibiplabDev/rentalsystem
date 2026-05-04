<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AdminForgotPasswordController extends Controller
{
    /**
     * Show the forgot password request form
     */
    public function showForgotForm()
    {
        return view('admin.forgot-password');
    }

    /**
     * Send reset link to admin email
     */
    public function sendResetLink(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
            ]);

            $email = $request->email;

            // Check if email belongs to an admin user
            $user = User::where('email', $email)
                ->where('role', 'admin')
                ->first();

            if (!$user) {
                // Don't reveal if email exists or not for security
                return back()
                    ->with('success', 'If an admin account exists with this email, a password reset link has been sent.')
                    ->withInput();
            }

            // Generate unique token
            $token = Str::random(64);

            // Store token in password_resets table
            DB::table('password_resets')->updateOrInsert(
                ['email' => $email],
                [
                    'email' => $email,
                    'token' => Hash::make($token),
                    'created_at' => now(),
                ]
            );

            // Generate reset URL
            $resetUrl = route('admin.password.reset', ['token' => $token, 'email' => $email]);

            // Send email (in production, use Mailable)
            // For now, we'll show a success message with the link
            // In production, you should configure Mail::send()
            if (app()->environment('production')) {
                // TODO: Configure email sending in production
                // Mail::send('emails.password-reset', ['url' => $resetUrl, 'user' => $user], function ($message) use ($user) {
                //     $message->to($user->email)->subject('Password Reset Request');
                // });
            }

            // For development, include the reset link in success message
            $successMessage = app()->environment('production')
                ? 'If an admin account exists with this email, a password reset link has been sent.'
                : 'Password reset link generated. Click here to reset: ' . $resetUrl;

            return back()
                ->with('success', $successMessage)
                ->withInput();

        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            return back()
                ->with('error', 'An error occurred while processing your request. Please try again later.')
                ->withInput();
        }
    }

    /**
     * Show the password reset form
     */
    public function showResetForm(Request $request, $token = null)
    {
        $email = $request->email;
        
        if (!$token || !$email) {
            return redirect()->route('admin.password.request')
                ->with('error', 'Invalid or expired password reset link.');
        }

        return view('admin.reset-password', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    /**
     * Reset the password
     */
    public function resetPassword(Request $request)
    {
        try {
            $request->validate([
                'token' => 'required',
                'email' => 'required|email',
                'password' => 'required|min:8|confirmed',
            ]);

            $email = $request->email;
            $token = $request->token;

            // Verify the token exists and is valid
            $resetRecord = DB::table('password_resets')
                ->where('email', $email)
                ->first();

            if (!$resetRecord) {
                return back()
                    ->with('error', 'Invalid or expired password reset link. Please request a new one.');
            }

            // Check if token is expired (valid for 60 minutes)
            $createdAt = \Carbon\Carbon::parse($resetRecord->created_at);
            if ($createdAt->diffInMinutes(now()) > 60) {
                DB::table('password_resets')->where('email', $email)->delete();
                return back()
                    ->with('error', 'Password reset link has expired. Please request a new one.');
            }

            // Verify token hash
            if (!Hash::check($token, $resetRecord->token)) {
                return back()
                    ->with('error', 'Invalid password reset token. Please request a new one.');
            }

            // Find and update admin user
            $user = User::where('email', $email)
                ->where('role', 'admin')
                ->first();

            if (!$user) {
                return back()
                    ->with('error', 'Admin account not found. Please contact support.');
            }

            // Update password
            $user->password = Hash::make($request->password);
            $user->save();

            // Delete the used token
            DB::table('password_resets')->where('email', $email)->delete();

            return redirect()->route('admin.login')
                ->with('success', 'Your password has been reset successfully. Please login with your new password.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            return back()
                ->with('error', 'An error occurred while resetting your password. Please try again.')
                ->withInput();
        }
    }
}
