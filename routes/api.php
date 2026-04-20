<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RentalController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - SECURED VERSION
|--------------------------------------------------------------------------
|
| This file contains all API routes with comprehensive security measures:
| - Rate limiting on sensitive endpoints
| - Admin middleware protection
| - Sanctum authentication
| - IP whitelisting for webhooks
| - Security headers applied via bootstrap/app.php
|
*/

// =============================================
// 🔐 PUBLIC AUTHENTICATION ROUTES (With Rate Limiting)
// =============================================

// Registration and Login - Stricter rate limits
Route::middleware(['throttle:10,15'])->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Email verification with rate limiting
Route::middleware(['throttle:5,15'])->prefix('email')->group(function () {
    Route::post('/verify/send', [AuthController::class, 'sendEmailVerification']);
    Route::post('/verify/otp', [AuthController::class, 'verifyEmailWithOtp']);
    Route::post('/verify/resend', [AuthController::class, 'resendEmailVerification']);
});

// Password reset with rate limiting
Route::middleware(['throttle:5,15'])->prefix('password')->group(function () {
    Route::post('/forgot', [AuthController::class, 'sendPasswordResetOtp']);
    Route::post('/reset', [AuthController::class, 'resetPasswordWithOtp']);
    Route::post('/resend-otp', [AuthController::class, 'resendPasswordResetOtp']);
});

// Email verification token route (no rate limit needed, single-use token)
Route::get('/email/verify/token/{token}', [AuthController::class, 'verifyEmailWithToken'])
    ->name('api.verification.verify');

// =============================================
// 🔐 GOOGLE LOGIN ROUTES (Web OAuth Flow - KEPT ORIGINAL)
// =============================================
Route::prefix('auth/google')->group(function () {
    Route::get('/auth-url', [AuthController::class, 'getGoogleAuthUrl']);
    Route::get('/callback', [AuthController::class, 'handleGoogleCallback']);

    // Protected Google routes
    Route::middleware(['auth:sanctum', 'throttle:10,1'])->group(function () {
        Route::post('/set-password', [AuthController::class, 'setPasswordForGoogleUser']);
        Route::post('/link', [AuthController::class, 'linkGoogleAccount']);
        Route::post('/unlink', [AuthController::class, 'unlinkGoogleAccount']);
    });
});

Route::post('/auth/google/complete-registration', [AuthController::class, 'completeGoogleRegistration']);

// =============================================
// 🆕 FLUTTER NATIVE GOOGLE SIGN-IN (NEW - ADDED WITHOUT BREAKING)
// =============================================
Route::prefix('auth/google')->group(function () {
    // Primary method - uses ID token (RECOMMENDED for Flutter)
    Route::post('/signin', [AuthController::class, 'googleSignIn'])
        ->middleware('throttle:10,1'); // 10 attempts per minute
    
    // Fallback method - uses access token (for compatibility)
    Route::post('/signin/access-token', [AuthController::class, 'googleSignInWithAccessToken'])
        ->middleware('throttle:5,1'); // 5 attempts per minute
});

// =============================================
// 🔒 PROTECTED ROUTES (Authentication Required)
// Security headers are automatically applied via bootstrap/app.php
// =============================================
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {

    // =============================================
    // 👤 AUTHENTICATION & USER PROFILE
    // =============================================
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/refresh-token', [AuthController::class, 'refreshToken']);
    });

    // =============================================
    // 🏪 BUSINESS PROFILE MANAGEMENT
    // =============================================
    Route::prefix('profile')->group(function () {
        // Get complete profile (one API call - returns everything)
        Route::get('/', [ProfileController::class, 'show']);
        
        // Update basic profile (name, phone, avatar only)
        Route::put('/', [ProfileController::class, 'update']);
        
        // Email change with verification
        Route::post('/email/change', [ProfileController::class, 'changeEmail']);
        Route::post('/email/verify-change', [ProfileController::class, 'verifyEmailChange']);
        
        // Business setup (manual entry - no GST verification)
        Route::post('/business/setup', [ProfileController::class, 'setupBusiness']);
        
        // Update business display info (name, address - always editable)
        Route::put('/business/display', [ProfileController::class, 'updateBusinessDisplay']);
        
        // Get business verification status
        Route::get('/business/status', [ProfileController::class, 'getBusinessStatus']);
        
        // GST verification (optional, can be added anytime)
        Route::post('/gst/add', [ProfileController::class, 'addGST']);
        Route::get('/gst/status', [ProfileController::class, 'getGSTStatus']);
        
        // Location management (shop coordinates)
        Route::post('/location', [ProfileController::class, 'updateLocation']);
        
        // Business logo upload
        Route::post('/business/logo', [ProfileController::class, 'uploadLogo']);
    });

    // =============================================
    // 🚗 VEHICLE MANAGEMENT
    // =============================================
    Route::prefix('vehicles')->group(function () {
        Route::get('/', [VehicleController::class, 'index']);
        Route::post('/', [VehicleController::class, 'store']);
        Route::get('/available', [VehicleController::class, 'available']);
        Route::get('/{id}', [VehicleController::class, 'show']);
        Route::put('/{id}', [VehicleController::class, 'update']);
        Route::patch('/{id}/status', [VehicleController::class, 'updateStatus']);
        Route::delete('/{id}', [VehicleController::class, 'destroy']);
        Route::get('/{id}/statistics', [VehicleController::class, 'statistics']);
    });

    // =============================================
    // 👥 CUSTOMER MANAGEMENT - ADMIN ONLY
    // Shop owners CANNOT access customer data to protect revenue
    // =============================================

    // ✅ ADMIN ONLY - Full customer management
    Route::prefix('customers')->middleware('admin')->group(function () {
        Route::get('/', [CustomerController::class, 'index']);
        Route::get('/search', [CustomerController::class, 'search']);
        Route::get('/incomplete-documentation', [CustomerController::class, 'incompleteDocumentation']);
        Route::get('/verified', [CustomerController::class, 'verifiedCustomers']);
        Route::get('/{id}', [CustomerController::class, 'show']);
        Route::get('/{id}/statistics', [CustomerController::class, 'statistics']);
        Route::get('/{id}/rental-history', [CustomerController::class, 'rentalHistory']);
        Route::put('/{id}', [CustomerController::class, 'update']);
        Route::get('/export', [CustomerController::class, 'export']);
        Route::get('/analytics', [CustomerController::class, 'analytics']);
    });

    // ✅ INTERNAL USE ONLY - Minimal data for rental verification
    // This is the ONLY customer endpoint shop owners can access
    Route::prefix('customers')->group(function () {
        Route::post('/check-by-license', [CustomerController::class, 'checkByLicense']);
    });

    // =============================================
    // 📦 RENTAL MANAGEMENT - MULTI-PHASE SYSTEM
    // =============================================
    Route::prefix('rentals')->group(function () {

        // PHASE 1: Verify DL & Deduct Fee
        Route::post('/phase1/verify', [RentalController::class, 'phase1Verify']);

        // PHASE 2: Upload Documents & Generate Agreement
        Route::post('/phase2/documents', [RentalController::class, 'phase2UploadDocuments']);

        // PHASE 3: Sign Agreement, Take Photos/Video & Start Rental
        Route::post('/{id}/phase3/sign', [RentalController::class, 'phase3SignAndHandover']);

        // RETURN VEHICLE: End rental, assess damage, generate receipt
        Route::post('/{id}/return', [RentalController::class, 'returnVehicle']);

        // GET CURRENT PHASE STATUS
        Route::get('/{id}/phase-status', [RentalController::class, 'getPhaseStatus']);

        // CANCEL RENTAL (any phase - verification fee is NON-REFUNDABLE)
        Route::post('/{id}/cancel', [RentalController::class, 'cancel']);

        // =============================================
        // 📋 RENTAL QUERY ENDPOINTS (NO ID)
        // =============================================
        Route::get('/statistics', [RentalController::class, 'statistics']);   // ⬅️ MUST come before any {id} route
        Route::get('/active', [RentalController::class, 'active']);
        Route::get('/history', [RentalController::class, 'history']);

        // =============================================
        // 🔍 RENTAL ENDPOINTS WITH ID (must be last)
        // =============================================
        Route::get('/{id}', [RentalController::class, 'show'])->where('id', '[0-9]+');  // only numeric
        Route::get('/{id}/agreement', [RentalController::class, 'downloadAgreement'])->where('id', '[0-9]+');
        Route::get('/{id}/signed-agreement', [RentalController::class, 'downloadSignedAgreement'])->where('id', '[0-9]+');
        Route::get('/{id}/receipt', [RentalController::class, 'downloadReceipt'])->where('id', '[0-9]+');
    });

    // =============================================
    // 📄 DOCUMENT MANAGEMENT
    // =============================================
    Route::prefix('documents')->group(function () {
        // Regular user endpoints
        Route::get('/', [DocumentController::class, 'index']);
        Route::get('/{id}', [DocumentController::class, 'show']);
        Route::get('/{id}/download/{type}', [DocumentController::class, 'download'])
            ->where('type', 'aadhaar|license');
        Route::delete('/{id}', [DocumentController::class, 'destroy']);

        // Admin only - Document management
        Route::middleware('admin')->group(function () {
            Route::get('/unverified', [DocumentController::class, 'unverified']);
            Route::post('/bulk-verify', [DocumentController::class, 'bulkVerify']);
            Route::post('/{id}/verify', [DocumentController::class, 'verify']);
            Route::post('/{id}/reject', [DocumentController::class, 'reject']);
            Route::get('/analytics', [DocumentController::class, 'analytics']);
        });
    });

    // =============================================
    // 📊 DASHBOARD & STATISTICS
    // =============================================
    Route::prefix('dashboard')->group(function () {
        Route::get('/', [DashboardController::class, 'stats']);
        Route::get('/recent', [DashboardController::class, 'recentActivity']);
        Route::get('/vehicles', [DashboardController::class, 'vehicleStats']);
        Route::get('/rentals', [DashboardController::class, 'rentalStats']);
        Route::get('/summary', [DashboardController::class, 'summary']);
        Route::get('/top-vehicles', [DashboardController::class, 'topVehicles']);
    });

    // =============================================
    // 💰 WALLET MANAGEMENT
    // =============================================
    Route::prefix('wallet')->group(function () {
        // Basic wallet operations
        Route::get('/', [WalletController::class, 'balance']);
        Route::get('/transactions', [WalletController::class, 'transactions']);
        Route::get('/transactions/{id}', [WalletController::class, 'transactionDetails']);
        Route::post('/transfer', [WalletController::class, 'transfer']);
        Route::get('/statement', [WalletController::class, 'statement']);

        // Cashfree Payment Gateway Integration
        Route::post('/recharge/initiate', [WalletController::class, 'initiateRecharge']);
    });

    // =============================================
    // 📈 REPORTS & ANALYTICS
    // =============================================
    Route::prefix('reports')->group(function () {
        // Reports accessible by authenticated users
        Route::get('/earnings', [ReportController::class, 'earnings']);
        Route::get('/rentals', [ReportController::class, 'rentals']);
        Route::get('/summary', [ReportController::class, 'summary']);
        Route::get('/top-vehicles', [ReportController::class, 'topVehicles']);
        Route::get('/top-customers', [ReportController::class, 'topCustomers']);
        Route::get('/documents', [ReportController::class, 'documentStatistics']);
        Route::get('/export/{type}', [ReportController::class, 'export'])
            ->where('type', 'rentals|earnings|vehicles|customers');

        // Admin only reports
        Route::middleware('admin')->group(function () {
            Route::get('/verification-metrics', [ReportController::class, 'verificationMetrics']);
            Route::get('/fraud-detection', [ReportController::class, 'fraudDetectionReport']);
            Route::get('/customer-analytics', [ReportController::class, 'customerAnalytics']);
            Route::get('/access-logs', [ReportController::class, 'accessLogs']);
        });
    });

    // =============================================
    // ⚙️ USER SETTINGS
    // =============================================
    Route::prefix('settings')->group(function () {
        // User settings
        Route::get('/', [SettingController::class, 'index']);
        Route::put('/', [SettingController::class, 'update']);
        Route::get('/defaults', [SettingController::class, 'getDefaults']);
        Route::get('/type/{type}', [SettingController::class, 'getByType']);
        Route::post('/reset', [SettingController::class, 'reset']);
        Route::get('/{key}', [SettingController::class, 'show']);
        Route::put('/{key}', [SettingController::class, 'updateSingle']);
        Route::delete('/{key}', [SettingController::class, 'destroy']);
        Route::delete('/', [SettingController::class, 'bulkDelete']);
    });

    // =============================================
    // 🔔 NOTIFICATIONS
    // =============================================
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::get('/statistics', [NotificationController::class, 'statistics']);
        Route::get('/type/{type}', [NotificationController::class, 'getByType']);
        Route::put('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::put('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/read', [NotificationController::class, 'deleteRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
        Route::delete('/', [NotificationController::class, 'destroyAll']);
        Route::get('/{id}', [NotificationController::class, 'show']);
    });

    // =============================================
    // 👑 ADMIN ROUTES (Full Access)
    // =============================================
    Route::middleware('admin')->prefix('admin')->group(function () {

        // User Management
        Route::prefix('users')->group(function () {
            Route::get('/', [AdminController::class, 'users']);
            Route::put('/{id}/role', [AdminController::class, 'updateUserRole']);
            Route::delete('/{id}', [AdminController::class, 'deleteUser']);
            Route::get('/{id}/details', [AdminController::class, 'userDetails']);
        });

        // Rental Management
        Route::prefix('rentals')->group(function () {
            Route::get('/', [AdminController::class, 'rentals']);
            Route::get('/stats', [AdminController::class, 'rentalStats']);
            Route::get('/fraud-alerts', [AdminController::class, 'fraudAlerts']);
            Route::post('/{id}/force-end', [AdminController::class, 'forceEndRental']);
            Route::get('/analytics', [AdminController::class, 'rentalAnalytics']);
        });

        // Settings Management
        Route::prefix('settings')->group(function () {
            Route::get('/', [AdminController::class, 'getSettings']);
            Route::post('/verification-price', [AdminController::class, 'setVerificationPrice']);
            Route::post('/lease-threshold', [AdminController::class, 'setLeaseThreshold']);
        });

        // Statistics
        Route::prefix('stats')->group(function () {
            Route::get('/users', [AdminController::class, 'userStats']);
            Route::get('/vehicles', [AdminController::class, 'vehicleStats']);
            Route::get('/earnings', [AdminController::class, 'earningsStats']);
            Route::get('/dashboard', [AdminController::class, 'dashboardStats']);
            Route::get('/verification', [AdminController::class, 'verificationStats']);
            Route::get('/fraud', [AdminController::class, 'fraudStats']);
        });

        // System Health & Monitoring
        Route::prefix('system')->group(function () {
            Route::get('/health', [AdminController::class, 'systemHealth']);
            Route::get('/logs', [AdminController::class, 'systemLogs']);
            Route::post('/clear-cache', [AdminController::class, 'clearCache']);
            Route::get('/cashfree-status', [AdminController::class, 'cashfreeStatus']);
        });

        // Audit Logs
        Route::prefix('audit')->group(function () {
            Route::get('/customer-access', [AdminController::class, 'customerAccessLogs']);
            Route::get('/rental-activity', [AdminController::class, 'rentalActivityLogs']);
            Route::get('/user-activity', [AdminController::class, 'userActivityLogs']);
            Route::get('/export', [AdminController::class, 'exportAuditLogs']);
        });
    });
});

Route::get('/wallet/payment-status', [WalletController::class, 'checkPaymentStatus']);

// =============================================
// 💳 CASHFREE WEBHOOKS (IP Whitelisted - No Auth)
// Security headers are still applied from bootstrap/app.php
// =============================================
Route::prefix('webhooks/cashfree')->group(function () {
    // Health check for Cashfree verification (GET)
    Route::get('/payment', [WebhookController::class, 'healthCheck']);
    
    // Payment webhook - actual event (POST)
    Route::post('/payment', [WebhookController::class, 'handlePayment']);

    // Optional additional health check
    Route::get('/health', [WebhookController::class, 'healthCheck']);
});

// =============================================
// 🔄 FALLBACK ROUTE FOR 404 ERRORS
// =============================================
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'API endpoint not found',
        'error' => 'The requested endpoint does not exist',
        'path' => request()->path(),
        'method' => request()->method(),
    ], 404);
});