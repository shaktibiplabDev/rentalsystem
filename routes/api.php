<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\RentalController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\WebhookController;

/*
|--------------------------------------------------------------------------
| API Routes - 100% Coverage with Revenue Protection
|--------------------------------------------------------------------------
|
| IMPORTANT: Customer data is ADMIN ONLY to protect your revenue model.
| Shop owners cannot see customer details to prevent data theft.
| Only the check-by-license endpoint is available for rental verification.
|
*/

// =============================================
// 🔐 PUBLIC AUTHENTICATION ROUTES
// =============================================
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// =============================================
// 🔒 PROTECTED ROUTES (Authentication Required)
// =============================================
Route::middleware('auth:sanctum')->group(function () {

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
    // 🚗 VEHICLE MANAGEMENT (100% Coverage)
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
    // 👥 CUSTOMER MANAGEMENT - PROTECTED (ADMIN ONLY)
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
    // 📦 RENTAL MANAGEMENT (100% Coverage)
    // =============================================
    Route::prefix('rentals')->group(function () {
        Route::post('/start', [RentalController::class, 'start']);
        Route::post('/{id}/end', [RentalController::class, 'end']);
        Route::get('/active', [RentalController::class, 'active']);
        Route::get('/history', [RentalController::class, 'history']);
        Route::get('/{id}', [RentalController::class, 'show']);
        Route::get('/statistics', [RentalController::class, 'statistics']);
        Route::get('/{id}/agreement', [RentalController::class, 'downloadAgreement']);
        Route::get('/{id}/receipt', [RentalController::class, 'downloadReceipt']);
    });

    // =============================================
    // 📄 DOCUMENT MANAGEMENT (100% Coverage)
    // =============================================
    Route::prefix('documents')->group(function () {
        // Regular user endpoints
        Route::get('/', [DocumentController::class, 'index']);
        Route::get('/{id}', [DocumentController::class, 'show']);
        Route::get('/{id}/download/{type}', [DocumentController::class, 'download'])
            ->where('type', 'aadhaar|license');
        Route::get('/{id}/ocr-data', [DocumentController::class, 'getOcrData']);
        Route::put('/{id}/verify', [DocumentController::class, 'verify']);
        Route::delete('/{id}', [DocumentController::class, 'destroy']);
        
        // Admin only - Document management
        Route::middleware('admin')->group(function () {
            Route::get('/unverified', [DocumentController::class, 'unverified']);
            Route::get('/fraud-suspected', [DocumentController::class, 'fraudSuspected']);
            Route::get('/failed-verification', [DocumentController::class, 'failedVerification']);
            Route::get('/with-aadhaar', [DocumentController::class, 'withAadhaar']);
            Route::get('/without-aadhaar', [DocumentController::class, 'withoutAadhaar']);
            Route::get('/missing-aadhaar', [DocumentController::class, 'missingAadhaar']);
            Route::post('/bulk-verify', [DocumentController::class, 'bulkVerify']);
            Route::post('/bulk-reject', [DocumentController::class, 'bulkReject']);
            Route::post('/{id}/reject', [DocumentController::class, 'reject']);
            Route::get('/analytics', [DocumentController::class, 'analytics']);
            Route::post('/verify-all-pending', [DocumentController::class, 'verifyAllPending']);
        });
    });

    // =============================================
    // 📊 DASHBOARD & STATISTICS (100% Coverage)
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
    // 💰 WALLET MANAGEMENT (100% Coverage)
    // =============================================
    Route::prefix('wallet')->group(function () {
        // Basic wallet operations
        Route::get('/', [WalletController::class, 'balance']);
        Route::get('/transactions', [WalletController::class, 'transactions']);
        Route::get('/transactions/{id}', [WalletController::class, 'transactionDetails']);
        Route::post('/add', [WalletController::class, 'addMoney']);
        Route::post('/deduct', [WalletController::class, 'deductMoney']);
        Route::post('/transfer', [WalletController::class, 'transfer']);
        Route::get('/statement', [WalletController::class, 'statement']);
        
        // Cashfree Payment Gateway Integration
        Route::post('/recharge/initiate', [WalletController::class, 'initiateRecharge']);
        Route::get('/payment-status', [WalletController::class, 'checkPaymentStatus']);
        Route::get('/payment-callback', [WalletController::class, 'paymentCallback']);
    });

    // =============================================
    // 📈 REPORTS & ANALYTICS (100% Coverage)
    // =============================================
    Route::prefix('reports')->group(function () {
        // Reports accessible by all staff
        Route::get('/earnings', [ReportController::class, 'earnings']);
        Route::get('/rentals', [ReportController::class, 'rentals']);
        Route::get('/summary', [ReportController::class, 'summary']);
        Route::get('/top-vehicles', [ReportController::class, 'topVehicles']);
        Route::get('/top-customers', [ReportController::class, 'topCustomers']);
        Route::get('/documents', [ReportController::class, 'documentStatistics']);
        Route::get('/export/{type}', [ReportController::class, 'export']);
        
        // Admin only reports
        Route::middleware('admin')->group(function () {
            Route::get('/verification-metrics', [ReportController::class, 'verificationMetrics']);
            Route::get('/fraud-detection', [ReportController::class, 'fraudDetectionReport']);
            Route::get('/aadhaar-statistics', [ReportController::class, 'aadhaarStatistics']);
            Route::get('/document-verification-costs', [ReportController::class, 'verificationCosts']);
            Route::get('/cost-savings-report', [ReportController::class, 'costSavingsReport']);
            Route::get('/customer-analytics', [ReportController::class, 'customerAnalytics']);
            Route::get('/access-logs', [ReportController::class, 'accessLogs']);
        });
    });

    // =============================================
    // ⚙️ USER SETTINGS (100% Coverage)
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
        
        // Admin only - System settings
        Route::middleware('admin')->group(function () {
            Route::put('/verification/aadhaar-optional', [SettingController::class, 'setAadhaarOptional']);
            Route::get('/verification/requirements', [SettingController::class, 'getVerificationRequirements']);
            Route::put('/verification/price', [SettingController::class, 'setVerificationPrice']);
            Route::put('/rental/lease-threshold', [SettingController::class, 'setLeaseThreshold']);
        });
    });

    // =============================================
    // 🔔 NOTIFICATIONS (100% Coverage)
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
    // 👑 ADMIN ROUTES (100% Coverage)
    // =============================================
    Route::middleware('admin')->prefix('admin')->group(function () {
        
        // User Management
        Route::prefix('users')->group(function () {
            Route::get('/', [AdminController::class, 'users']);
            Route::put('/{id}/role', [AdminController::class, 'updateUserRole']);
            Route::delete('/{id}', [AdminController::class, 'deleteUser']);
        });
        
        // Rental Management
        Route::prefix('rentals')->group(function () {
            Route::get('/', [AdminController::class, 'rentals']);
            Route::get('/stats', [AdminController::class, 'rentalStats']);
            Route::get('/fraud-alerts', [AdminController::class, 'fraudAlerts']);
            Route::post('/{id}/force-end', [AdminController::class, 'forceEndRental']);
            Route::get('/without-aadhaar', [AdminController::class, 'rentalsWithoutAadhaar']);
            Route::get('/analytics', [AdminController::class, 'rentalAnalytics']);
        });
        
        // Settings Management
        Route::prefix('settings')->group(function () {
            Route::get('/', [AdminController::class, 'getSettings']);
            Route::post('/verification-price', [AdminController::class, 'setVerificationPrice']);
            Route::post('/lease-threshold', [AdminController::class, 'setLeaseThreshold']);
            Route::post('/ocr-confidence-threshold', [AdminController::class, 'setOcrConfidenceThreshold']);
            Route::post('/aadhaar-required', [AdminController::class, 'setAadhaarRequired']);
            Route::get('/verification-costs', [AdminController::class, 'getVerificationCosts']);
        });
        
        // Cashfree OCR Reports
        Route::prefix('ocr')->group(function () {
            Route::get('/statistics', [AdminController::class, 'ocrStatistics']);
            Route::get('/fraud-patterns', [AdminController::class, 'fraudPatterns']);
            Route::get('/quality-metrics', [AdminController::class, 'qualityMetrics']);
            Route::get('/verification-logs', [AdminController::class, 'verificationLogs']);
            Route::post('/retry-failed', [AdminController::class, 'retryFailedVerification']);
            Route::get('/cost-analysis', [AdminController::class, 'ocrCostAnalysis']);
            Route::get('/cost-savings', [AdminController::class, 'aadhaarCostSavings']);
        });
        
        // Statistics
        Route::prefix('stats')->group(function () {
            Route::get('/users', [AdminController::class, 'userStats']);
            Route::get('/vehicles', [AdminController::class, 'vehicleStats']);
            Route::get('/earnings', [AdminController::class, 'earningsStats']);
            Route::get('/rentals', [AdminController::class, 'rentalStats']);
            Route::get('/dashboard', [AdminController::class, 'dashboardStats']);
            Route::get('/verification', [AdminController::class, 'verificationStats']);
            Route::get('/fraud', [AdminController::class, 'fraudStats']);
            Route::get('/documents', [AdminController::class, 'documentStats']);
            Route::get('/aadhaar-adoption', [AdminController::class, 'aadhaarAdoptionStats']);
            Route::get('/customer-access', [AdminController::class, 'customerAccessStats']);
        });
        
        // System Health & Monitoring
        Route::prefix('system')->group(function () {
            Route::get('/health', [AdminController::class, 'systemHealth']);
            Route::get('/logs', [AdminController::class, 'systemLogs']);
            Route::post('/clear-cache', [AdminController::class, 'clearCache']);
            Route::get('/cashfree-status', [AdminController::class, 'cashfreeStatus']);
            Route::post('/sync-verifications', [AdminController::class, 'syncVerifications']);
            Route::get('/verification-costs', [AdminController::class, 'verificationCostMonitoring']);
            Route::post('/optimize-verification', [AdminController::class, 'optimizeVerificationSettings']);
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

// =============================================
// 💳 CASHFREE WEBHOOKS (NO AUTH REQUIRED)
// =============================================
Route::post('/webhooks/cashfree/payment', [WebhookController::class, 'handlePayment']);

// =============================================
// 🔄 FALLBACK ROUTE FOR 404 ERRORS
// =============================================
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message'=> 'API endpoint not found',
        'error' => 'The requested endpoint does not exist'
    ], 404);
});