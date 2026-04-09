<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\EmailVerification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    /**
     * Verify email via token and show nice HTML page
     */
    public function verifyToken($token)
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

            // Mark email as verified
            $user->markEmailAsVerified();
            $verification->is_used = true;
            $verification->save();

            return $this->renderResponse(
                true,
                'Email Verified Successfully!',
                'Your email has been verified. You can now close this window and login to the app.',
                true
            );

        } catch (\Exception $e) {
            \Log::error('Email verification error', [
                'token' => $token,
                'error' => $e->getMessage()
            ]);

            return $this->renderResponse(
                false,
                'Verification Failed',
                'An error occurred while verifying your email. Please try again or request a new verification link.'
            );
        }
    }

    /**
     * Render nice HTML response
     */
    private function renderResponse($success, $title, $message, $showLoginButton = false)
    {
        $html = '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
            <title>' . htmlspecialchars($title) . ' - Vehicle Rental</title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    padding: 20px;
                }
                
                .container {
                    max-width: 500px;
                    width: 100%;
                    animation: fadeIn 0.5s ease-out;
                }
                
                .card {
                    background: white;
                    border-radius: 24px;
                    padding: 40px 32px;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                    text-align: center;
                }
                
                .icon {
                    width: 80px;
                    height: 80px;
                    margin: 0 auto 24px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    animation: bounce 0.5s ease-out;
                }
                
                .icon.success {
                    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                }
                
                .icon.error {
                    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
                }
                
                .icon svg {
                    width: 48px;
                    height: 48px;
                    color: white;
                }
                
                h1 {
                    font-size: 28px;
                    font-weight: 700;
                    margin-bottom: 12px;
                    color: #1f2937;
                }
                
                .message {
                    font-size: 16px;
                    color: #6b7280;
                    line-height: 1.5;
                    margin-bottom: 32px;
                }
                
                .button {
                    display: inline-block;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    text-decoration: none;
                    padding: 14px 32px;
                    border-radius: 12px;
                    font-weight: 600;
                    font-size: 16px;
                    transition: transform 0.2s, box-shadow 0.2s;
                    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
                }
                
                .button:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 6px 16px rgba(102, 126, 234, 0.5);
                }
                
                .app-link {
                    margin-top: 24px;
                    font-size: 14px;
                    color: #9ca3af;
                }
                
                .app-link a {
                    color: #667eea;
                    text-decoration: none;
                    font-weight: 500;
                }
                
                @keyframes fadeIn {
                    from {
                        opacity: 0;
                        transform: translateY(20px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
                
                @keyframes bounce {
                    0%, 100% {
                        transform: scale(1);
                    }
                    50% {
                        transform: scale(1.1);
                    }
                }
                
                @media (max-width: 640px) {
                    .card {
                        padding: 32px 24px;
                    }
                    
                    h1 {
                        font-size: 24px;
                    }
                    
                    .icon {
                        width: 64px;
                        height: 64px;
                    }
                    
                    .icon svg {
                        width: 36px;
                        height: 36px;
                    }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="card">
                    <div class="icon ' . ($success ? 'success' : 'error') . '">
                        ' . ($success ? '
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        ' : '
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        ') . '
                    </div>
                    <h1>' . htmlspecialchars($title) . '</h1>
                    <div class="message">' . htmlspecialchars($message) . '</div>
                    ' . ($showLoginButton ? '
                    <a href="javascript:void(0)" onclick="openApp()" class="button">Open App & Login</a>
                    <div class="app-link">
                        <small>Or <a href="' . url('/') . '">return to home page</a></small>
                    </div>
                    ' : '
                    <a href="' . url('/') . '" class="button">Go to Home</a>
                    ') . '
                </div>
            </div>
            
            <script>
                function openApp() {
                    window.location.href = "yourapp://login";
                    
                    // Fallback to app store after 2 seconds
                    setTimeout(function() {
                        window.location.href = "https://play.google.com/store/apps/details?id=com.yourapp.package";
                    }, 2000);
                }
                
                // Auto redirect to app if installed (for verified emails)
                ' . ($showLoginButton ? '
                setTimeout(function() {
                    openApp();
                }, 3000);
                ' : '') . '
            </script>
        </body>
        </html>
        ';
        
        return response($html, $success ? 200 : 400)
            ->header('Content-Type', 'text/html');
    }
}