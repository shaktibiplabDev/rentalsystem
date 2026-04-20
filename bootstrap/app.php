<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\AuthenticateApi; // Add this

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )

    ->withMiddleware(function (Middleware $middleware) {
        // Register middleware aliases
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'security.headers' => SecurityHeaders::class,
            'auth.api' => AuthenticateApi::class, // Add this
        ]);

        // Replace the default auth middleware with our custom one for API
        $middleware->api(prepend: [
            SecurityHeaders::class,
        ]);
        
        // Override the default auth middleware
        $middleware->replace(\Illuminate\Auth\Middleware\Authenticate::class, AuthenticateApi::class);
    })

    ->withExceptions(function (Exceptions $exceptions) {
        // Handle unauthenticated
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                    'error' => 'No valid authentication token provided or token has expired.',
                    'code' => 401
                ], 401);
            }
            throw $e;
        });

        // Handle forbidden
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
                'error' => 'You do not have permission to access this resource.'
            ], 403);
        });

        // Handle other exceptions (optional)
        if (app()->environment('local')) {
            $exceptions->render(function (\Throwable $e, Request $request) {
                return response()->json([
                    'success' => false,
                    'message' => 'Server Error',
                    'error' => $e->getMessage()
                ], 500);
            });
        }
    })

    ->create();