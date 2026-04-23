<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate as BaseAuthenticate;
use Illuminate\Http\Request;

class AuthenticateApi extends BaseAuthenticate
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // For API requests, return null (no redirect) - will trigger exception handler
        if ($request->is('api/*') || $request->expectsJson()) {
            return null;
        }
        
        // For admin routes, redirect to admin login
        if ($request->is('admin') || $request->is('admin/*')) {
            return route('admin.login');
        }
        
        // For other web requests, redirect to home/login
        return route('login');
    }
}