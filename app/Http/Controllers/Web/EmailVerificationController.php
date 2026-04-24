<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\EmailVerification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class EmailVerificationController extends Controller
{
    /**
     * Verify email via token and render response page.
     */
    public function verifyToken(string $token)
    {
        try {
            $verification = EmailVerification::where('token', $token)
                ->where('is_used', false)
                ->first();

            if (!$verification) {
                return $this->renderResponse(
                    false,
                    'Invalid or expired verification link',
                    'The verification link is invalid or has already been used.'
                );
            }

            if (Carbon::now()->gt($verification->expires_at)) {
                return $this->renderResponse(
                    false,
                    'Verification link has expired',
                    'The verification link has expired. Please request a new verification email from the app.'
                );
            }

            $user = $verification->user;

            if ($user->hasVerifiedEmail()) {
                return $this->renderResponse(
                    true,
                    'Email already verified',
                    'Your email has already been verified. You can now login to the app.',
                    true
                );
            }

            $user->markEmailAsVerified();
            $verification->is_used = true;
            $verification->save();

            return $this->renderResponse(
                true,
                'Email Verified Successfully',
                'Your email has been verified. You can now close this page and continue in the app.',
                true
            );
        } catch (\Throwable $e) {
            Log::error('Email verification error', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);

            return $this->renderResponse(
                false,
                'Verification Failed',
                'An unexpected error occurred while verifying your email. Please try again later.'
            );
        }
    }

    private function renderResponse(bool $success, string $title, string $message, bool $showLoginButton = false)
    {
        return response()
            ->view('email-verification', compact('success', 'title', 'message', 'showLoginButton'), $success ? 200 : 400)
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
