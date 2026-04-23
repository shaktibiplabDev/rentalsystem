<?php

use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\Web\EmailVerificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// =============================================
// PUBLIC ROUTES
// =============================================

// Landing page (website)
Route::get('/', [LandingController::class, 'index'])->name('home');

// Email verification landing page (web)
Route::get('/email/verify/{token}', [EmailVerificationController::class, 'verifyToken'])
    ->name('verification.verify');

// Wallet landing page (if you have a Blade view)
Route::get('/wallet', function () {
    return view('wallet');
})->name('wallet');

// Serve protected media files (public disk)
Route::get('/media/{path}', function (string $path) {
    $path = ltrim($path, '/');
    if (str_contains($path, '..')) {
        abort(404);
    }
    if (! Storage::disk('public')->exists($path)) {
        abort(404);
    }

    return response()->file(Storage::disk('public')->path($path), [
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->where('path', '.*')->name('media.public');

// =============================================
// ADMIN AUTHENTICATION (NO MIDDLEWARE)
// =============================================
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AdminLoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AdminLoginController::class, 'login']);
    Route::post('/logout', [AdminLoginController::class, 'logout'])->name('logout');
});

// =============================================
// ADMIN WEB ROUTES (protected by auth + admin middleware)
// =============================================
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\RentalController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\ShopController;
use App\Http\Controllers\Admin\VehicleController;
use App\Http\Controllers\Admin\WalletController;
use App\Http\Controllers\Admin\MapController;
use App\Http\Controllers\Admin\SearchController;

Route::middleware(['admin'])->prefix('admin')->name('admin.')->group(function () {
    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    
    // Shop details for AJAX (MUST come before resource routes)
    Route::get('/shops/{id}/details', [ShopController::class, 'details'])->name('shops.details');
    Route::get('/search', [SearchController::class, 'globalSearch'])->name('admin.search');

    // Shop management (users with role 'user')
    Route::resource('shops', ShopController::class)->only(['index', 'show']);
    Route::post('shops/{id}/status', [ShopController::class, 'updateStatus'])->name('shops.status');

    // Rental management
    Route::resource('rentals', RentalController::class)->only(['index', 'show']);
    Route::post('rentals/{id}/force-end', [RentalController::class, 'forceEnd'])->name('rentals.force-end');

    // Customer management
    Route::resource('customers', CustomerController::class)->only(['index', 'show']);

    // Vehicle management (across all shops)
    Route::resource('vehicles', VehicleController::class)->only(['index']);

    // Wallet management
    Route::get('wallet', [WalletController::class, 'index'])->name('wallet.index');
    Route::post('wallet/{shopId}/add', [WalletController::class, 'addBalance'])->name('wallet.add');

    // Platform settings
    Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
    Route::post('settings', [SettingController::class, 'update'])->name('settings.update');
    
    // Map and Fleet
    Route::get('/map', [MapController::class, 'index'])->name('map');
    Route::get('/fleet', [VehicleController::class, 'index'])->name('fleet');
    
    // Profile
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
});

// =============================================
// LEGACY LOGOUT (kept for compatibility)
// =============================================
Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/admin/login');
})->name('logout');

