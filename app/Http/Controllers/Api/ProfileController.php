<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailVerification;
use App\Models\User;
use App\Services\CashfreeService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    protected $cashfreeService;

    public function __construct(CashfreeService $cashfreeService)
    {
        $this->cashfreeService = $cashfreeService;
    }

    /**
     * Get user profile with all business details
     */
    public function show(Request $request)
    {
        try {
            $user = auth()->user();
            
            // Check if user is authenticated
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                    'error' => 'No valid authentication token provided or token has expired.'
                ], 401);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'avatar_url' => $user->avatar_url,
                    'role' => $user->role,
                    'wallet_balance' => $user->wallet_balance,
                    'can_change_email' => !($user->is_google_user && $user->google_id),
                    'email_verified' => $user->hasVerifiedEmail(),
                    'is_google_user' => (bool) $user->is_google_user,
                    'has_google_linked' => !empty($user->google_id),
                    'has_password' => $user->hasPassword(),
                    'needs_password_setup' => $user->needsPasswordSetup(),
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    
                    // Business Information
                    'business' => [
                        'display_name' => $user->business_display_name,
                        'display_address' => $user->business_display_address,
                        'legal_name' => $user->legal_business_name,
                        'gst_number' => $user->gst_number,
                        'gst_verified' => $user->isGstVerified(),
                        'gst_verified_at' => $user->gst_verified_at,
                        'gst_status' => $user->gst_status,
                        'taxpayer_type' => $user->taxpayer_type,
                        'constitution' => $user->constitution_of_business,
                        'nature_of_business' => $user->nature_of_business_activities,
                        'registered_address' => $user->registered_business_address,
                        'verification_status' => $user->business_verification_status,
                        'verification_status_text' => $user->verification_status_text,
                        'logo_url' => $user->business_logo ? Storage::disk('public')->url($user->business_logo) : null,
                        'phone' => $user->business_phone,
                        'email' => $user->business_email,
                    ],
                    
                    // Location
                    'location' => [
                        'latitude' => $user->latitude,
                        'longitude' => $user->longitude,
                    ],
                ]
            ], 200);
            
        } catch (Exception $e) {
            Log::error('Profile fetch error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch profile',
                'error' => 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Update basic profile information (name, phone, avatar only)
     * Email updates are handled separately with verification
     */
    public function update(Request $request)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                    'error' => 'No valid authentication token provided or token has expired.'
                ], 401);
            }
            
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'phone' => 'sometimes|string|max:20|unique:users,phone,' . $user->id,
                'avatar' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            ]);
            
            // Remove email from validated array if present (email has separate flow)
            if (isset($validated['email'])) {
                unset($validated['email']);
            }
            
            DB::transaction(function () use ($user, $validated, $request) {
                // Handle avatar upload
                if ($request->hasFile('avatar')) {
                    if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                        Storage::disk('public')->delete($user->avatar);
                    }
                    
                    $avatarPath = $request->file('avatar')->store('avatars/' . date('Y/m/d'), 'public');
                    $validated['avatar'] = $avatarPath;
                }
                
                $user->update(array_filter($validated));
            });
            
            $user->refresh();
            
            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'avatar_url' => $user->avatar_url,
                ]
            ], 200);
            
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Profile update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile'
            ], 500);
        }
    }

    /**
     * Request email change (sends OTP to new email)
     */
    public function changeEmail(Request $request)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                    'error' => 'No valid authentication token provided or token has expired.'
                ], 401);
            }
            
            $validated = $request->validate([
                'new_email' => 'required|email|max:255|unique:users,email',
            ]);
            
            // CHECK: Google-authenticated users cannot change email
            if ($user->is_google_user && $user->google_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Google-authenticated users cannot change email. Email is managed by Google.',
                    'errors' => [
                        'email' => ['Email is linked to your Google account and cannot be changed here.']
                    ]
                ], 403);
            }
            
            // Don't allow changing to same email
            if ($user->email === $validated['new_email']) {
                return response()->json([
                    'success' => false,
                    'message' => 'New email must be different from current email',
                ], 422);
            }
            
            // Generate OTP
            $otp = sprintf('%06d', mt_rand(1, 999999));
            $token = Str::random(60);
            $expiresAt = Carbon::now()->addMinutes(15);
            
            // Store pending email change in cache
            Cache::put(
                "email_change_{$user->id}", 
                [
                    'new_email' => $validated['new_email'],
                    'otp' => $otp,
                    'token' => $token,
                    'expires_at' => $expiresAt,
                ], 
                now()->addMinutes(15)
            );
            
            // Send OTP to new email
            $this->sendEmailChangeOtp($validated['new_email'], $otp, $user->name);
            
            return response()->json([
                'success' => true,
                'message' => 'Verification OTP sent to new email address',
                'data' => [
                    'token' => $token,
                    'expires_in' => 15,
                ]
            ], 200);
            
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Email change request error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification email'
            ], 500);
        }
    }

    /**
     * Verify OTP and complete email change
     */
    public function verifyEmailChange(Request $request)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                    'error' => 'No valid authentication token provided or token has expired.'
                ], 401);
            }
            
            $validated = $request->validate([
                'token' => 'required|string|size:60',
                'otp' => 'required|string|size:6',
            ]);
            
            // CHECK: Google-authenticated users cannot change email
            if ($user->is_google_user && $user->google_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Google-authenticated users cannot change email.',
                ], 403);
            }
            
            // Get pending email change from cache
            $pending = Cache::get("email_change_{$user->id}");
            
            if (!$pending) {
                return response()->json([
                    'success' => false,
                    'message' => 'No pending email change request',
                ], 404);
            }
            
            // Verify token and OTP
            if ($pending['token'] !== $validated['token'] || $pending['otp'] !== $validated['otp']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid OTP or token',
                ], 422);
            }
            
            // Check expiry
            if (Carbon::now()->gt($pending['expires_at'])) {
                Cache::forget("email_change_{$user->id}");
                return response()->json([
                    'success' => false,
                    'message' => 'OTP has expired. Please request a new one.',
                ], 422);
            }
            
            // Check if new email is already taken
            $existingUser = User::where('email', $pending['new_email'])->first();
            if ($existingUser && $existingUser->id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email already taken by another user',
                ], 422);
            }
            
            // Update email
            $user->email = $pending['new_email'];
            $user->email_verified_at = null; // Require re-verification
            $user->save();
            
            // Clear cache
            Cache::forget("email_change_{$user->id}");
            
            // Send verification email to new email
            $this->sendNewEmailVerification($user);
            
            return response()->json([
                'success' => true,
                'message' => 'Email changed successfully. Please verify your new email address.',
                'data' => [
                    'email' => $user->email,
                    'requires_verification' => true,
                ]
            ], 200);
            
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Email change verification error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to change email'
            ], 500);
        }
    }

    /**
     * Setup business profile (manual entry - no GST verification)
     * Status becomes 'pending'
     */
    public function setupBusiness(Request $request)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                    'error' => 'No valid authentication token provided or token has expired.'
                ], 401);
            }
            
            $validated = $request->validate([
                'display_name' => 'required|string|max:255',
                'display_address' => 'required|string|max:500',
                'phone' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
            ]);
            
            DB::transaction(function () use ($user, $validated) {
                $user->update([
                    'business_display_name' => $validated['display_name'],
                    'business_display_address' => $validated['display_address'],
                    'business_phone' => $validated['phone'] ?? null,
                    'business_email' => $validated['email'] ?? null,
                    'latitude' => $validated['latitude'] ?? null,
                    'longitude' => $validated['longitude'] ?? null,
                    'business_verification_status' => 'pending',
                ]);
            });
            
            return response()->json([
                'success' => true,
                'message' => 'Business profile created successfully. You can add GST details later for verification.',
                'data' => [
                    'display_name' => $user->business_display_name,
                    'display_address' => $user->business_display_address,
                    'verification_status' => 'pending',
                    'can_add_gst_later' => true,
                ]
            ], 200);
            
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Business setup error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to setup business profile'
            ], 500);
        }
    }

    /**
     * Update business display information (only display name and address)
     */
    public function updateBusinessDisplay(Request $request)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                    'error' => 'No valid authentication token provided or token has expired.'
                ], 401);
            }
            
            $validated = $request->validate([
                'display_name' => 'required|string|max:255',
                'display_address' => 'required|string|max:500',
                'phone' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
            ]);
            
            $user->update([
                'business_display_name' => $validated['display_name'],
                'business_display_address' => $validated['display_address'],
                'business_phone' => $validated['phone'] ?? $user->business_phone,
                'business_email' => $validated['email'] ?? $user->business_email,
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Business display information updated successfully',
                'data' => [
                    'display_name' => $user->business_display_name,
                    'display_address' => $user->business_display_address,
                    'legal_name' => $user->legal_business_name,
                    'verification_status' => $user->business_verification_status,
                ]
            ], 200);
            
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Business display update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update business display information'
            ], 500);
        }
    }

    /**
     * Update business location
     */
    public function updateLocation(Request $request)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                    'error' => 'No valid authentication token provided or token has expired.'
                ], 401);
            }
            
            $validated = $request->validate([
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'address' => 'nullable|string|max:500',
            ]);
            
            $updateData = [
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
            ];
            
            if (isset($validated['address'])) {
                $updateData['business_display_address'] = $validated['address'];
            }
            
            $user->update($updateData);
            
            return response()->json([
                'success' => true,
                'message' => 'Location updated successfully',
                'data' => [
                    'latitude' => $user->latitude,
                    'longitude' => $user->longitude,
                    'display_address' => $user->business_display_address,
                ]
            ], 200);
            
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Location update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update location'
            ], 500);
        }
    }

    /**
     * Add/Update GST for verification (optional)
     */
    public function addGST(Request $request)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                    'error' => 'No valid authentication token provided or token has expired.'
                ], 401);
            }
            
            $validated = $request->validate([
                'gst_number' => 'required|string|regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i',
                'business_name' => 'nullable|string|max:255',
            ]);
            
            if ($user->isGstVerified()) {
                return response()->json([
                    'success' => false,
                    'message' => 'GST already verified',
                    'data' => [
                        'gst_number' => $user->gst_number,
                        'verified_at' => $user->gst_verified_at,
                    ]
                ], 400);
            }
            
            $verificationResult = $this->cashfreeService->verifyGST(
                $validated['gst_number'],
                $validated['business_name'] ?? null
            );
            
            if (!$verificationResult['success'] || !$verificationResult['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'GST verification failed',
                    'errors' => ['gst_number' => [$verificationResult['error'] ?? 'Invalid GST number']],
                ], 422);
            }
            
            DB::transaction(function () use ($user, $validated, $verificationResult) {
                $user->update([
                    'gst_number' => $validated['gst_number'],
                    'gst_verified_at' => now(),
                    'gst_verification_data' => $verificationResult['raw_response'],
                    'legal_business_name' => $verificationResult['legal_business_name'] ?? $verificationResult['business_name'],
                    'gst_status' => $verificationResult['gst_status'],
                    'taxpayer_type' => $verificationResult['taxpayer_type'],
                    'constitution_of_business' => $verificationResult['constitution_of_business'],
                    'nature_of_business_activities' => $verificationResult['nature_of_business_activities'],
                    'registered_business_address' => $verificationResult['address'],
                    'business_verification_status' => 'verified',
                    
                    'latitude' => $user->latitude ?? ($verificationResult['principal_address_details']['latitude'] ?? null),
                    'longitude' => $user->longitude ?? ($verificationResult['principal_address_details']['longitude'] ?? null),
                    'business_display_name' => $user->business_display_name ?? $verificationResult['business_name'],
                ]);
                
                if (!$user->business_display_address && $verificationResult['address']) {
                    $user->business_display_address = $verificationResult['address'];
                    $user->save();
                }
            });
            
            $user->refresh();
            
            return response()->json([
                'success' => true,
                'message' => 'GST verified successfully. Business is now verified.',
                'data' => [
                    'gst_number' => $user->gst_number,
                    'legal_name' => $user->legal_business_name,
                    'display_name' => $user->business_display_name,
                    'gst_status' => $user->gst_status,
                    'verification_status' => 'verified',
                    'verified_at' => $user->gst_verified_at,
                    'registered_address' => $user->registered_business_address,
                    'display_address' => $user->business_display_address,
                ]
            ], 200);
            
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('GST verification error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify GST. Please try again later.',
            ], 500);
        }
    }

    /**
     * Get GST verification status
     */
    public function getGSTStatus()
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                    'error' => 'No valid authentication token provided or token has expired.'
                ], 401);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'has_gst' => !is_null($user->gst_number),
                    'gst_number' => $user->gst_number,
                    'is_verified' => $user->isGstVerified(),
                    'verified_at' => $user->gst_verified_at,
                    'legal_name' => $user->legal_business_name,
                    'display_name' => $user->business_display_name,
                    'gst_status' => $user->gst_status,
                    'taxpayer_type' => $user->taxpayer_type,
                    'constitution' => $user->constitution_of_business,
                    'registered_address' => $user->registered_business_address,
                    'display_address' => $user->business_display_address,
                    'nature_of_business' => $user->nature_of_business_activities,
                    'status_text' => $user->verification_status_text,
                ]
            ], 200);
            
        } catch (Exception $e) {
            Log::error('GST status error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch GST status'
            ], 500);
        }
    }

    /**
     * Get business verification status
     */
    public function getBusinessStatus()
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                    'error' => 'No valid authentication token provided or token has expired.'
                ], 401);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'verification_status' => $user->business_verification_status,
                    'has_display_info' => !is_null($user->business_display_name),
                    'has_gst' => !is_null($user->gst_number),
                    'is_gst_verified' => $user->isGstVerified(),
                    'can_upgrade_to_verified' => $user->hasBusinessDetails() && !$user->isGstVerified() && !$user->gst_number,
                    'status_text' => $user->verification_status_text,
                    'next_step' => $user->next_verification_step,
                ]
            ], 200);
            
        } catch (Exception $e) {
            Log::error('Business status error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch business status'
            ], 500);
        }
    }

    /**
     * Upload business logo
     */
    public function uploadLogo(Request $request)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                    'error' => 'No valid authentication token provided or token has expired.'
                ], 401);
            }
            
            $validated = $request->validate([
                'logo' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            ]);
            
            if ($user->business_logo && Storage::disk('public')->exists($user->business_logo)) {
                Storage::disk('public')->delete($user->business_logo);
            }
            
            $logoPath = $request->file('logo')->store('business/logos/' . date('Y/m/d'), 'public');
            $user->update(['business_logo' => $logoPath]);
            
            return response()->json([
                'success' => true,
                'message' => 'Business logo uploaded successfully',
                'data' => ['logo_url' => Storage::disk('public')->url($logoPath)]
            ], 200);
            
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Logo upload error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload logo'
            ], 500);
        }
    }

    // =============================================
    // PRIVATE HELPER METHODS
    // =============================================

    /**
     * Send OTP for email change
     */
    private function sendEmailChangeOtp($email, $otp, $name)
    {
        $subject = 'Email Change Verification - Vehicle Rental System';
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
                    <h2>Email Change Request</h2>
                    <p>Dear " . htmlspecialchars($name) . ",</p>
                    <p>You requested to change your email address to this email. Use the following OTP to confirm:</p>
                    <div class='otp-code'>{$otp}</div>
                    <p>This OTP is valid for 15 minutes.</p>
                    <p>If you didn't request this, please ignore this email.</p>
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
     * Send verification email for new email address
     */
    private function sendNewEmailVerification($user)
    {
        if (!Schema::hasTable('email_verifications')) {
            Log::error('email_verifications table does not exist');
            return;
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

        $verificationLink = route('verification.verify', ['token' => $token]);

        $subject = 'Verify Your New Email - Vehicle Rental System';
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
                    <h2>Verify Your New Email Address</h2>
                    <p>Dear " . htmlspecialchars($user->name) . ",</p>
                    <p>Your email address has been changed. Please verify your new email address to continue using all features.</p>
                    
                    <h3>Option 1: Use OTP</h3>
                    <div class='otp-code'>{$otp}</div>
                    <p>Enter this OTP in the app to verify your email. This OTP is valid for 24 hours.</p>
                    
                    <h3>Option 2: Click the Link</h3>
                    <p><a href='{$verificationLink}' class='button'>Verify Email Address</a></p>
                    <p>Or copy and paste this link: <br> <small>{$verificationLink}</small></p>
                    
                    <p>If you didn't change your email, please contact support immediately.</p>
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
}