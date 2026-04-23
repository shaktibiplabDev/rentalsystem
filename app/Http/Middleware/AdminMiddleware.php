<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        // Not authenticated - session expired or not logged in
        if (!$user) {
            // For API requests - return JSON
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated. Please login again.'], 401);
            }
            
            // For web requests - redirect to admin login page
            // Store the intended URL to redirect back after login
            if (!$request->is('admin/login') && !$request->is('admin/logout')) {
                session()->put('url.intended', url()->current());
            }
            
            return redirect()->route('admin.login')->with('error', 'Your session has expired. Please login again.');
        }

        // Authenticated but not admin - logout and redirect to login
        if ($user->role !== 'admin') {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Forbidden - Admin access only'], 403);
            }
            
            return redirect()->route('admin.login')->with('error', 'You do not have admin access.');
        }

        return $next($request);
    }
}