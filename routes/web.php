<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\EmailVerificationController;

Route::get('/', function () {
    return response()->json([
        'message' => 'Vehicle Rental System API',
        'version' => '1.0.0',
        'status' => 'running'
    ]);
});

use App\Http\Controllers\Admin\DashboardController;

Route::get('/admin', [DashboardController::class, 'index']);

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

Route::post('/logout', function (Request $request) {
    Auth::logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/admin');
})->name('logout');

Route::get('/email/verify/{token}', [EmailVerificationController::class, 'verifyToken'])
    ->name('verification.verify');

Route::get('/wallet', function () {
    return view('wallet');
})->name('wallet');

Route::get('/media/{path}', function (string $path) {
    $path = ltrim($path, '/');

    // Prevent path traversal attempts.
    if (str_contains($path, '..')) {
        abort(404);
    }

    // Serve only files that exist on the public disk.
    if (! Storage::disk('public')->exists($path)) {
        abort(404);
    }

    return response()->file(Storage::disk('public')->path($path), [
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->where('path', '.*')->name('media.public');
