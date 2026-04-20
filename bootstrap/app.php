<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use App\Http\Middleware\SecurityHeaders;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )

    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'security.headers' => SecurityHeaders::class,
        ]);

        $middleware->api(prepend: [
            SecurityHeaders::class,
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions) {
        // HANDLE UNAUTHENTICATED - THIS SHOULD BE FIRST
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            // Only return JSON for API requests
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                    'error' => 'Token expired or invalid.'
                ], 401);
            }
            // For non-API requests, let it redirect
            throw $e;
        });

        // HANDLE FORBIDDEN
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
                'error' => 'You do not have permission to access this resource.'
            ], 403);
        });

        // OPTIONAL: Handle other exceptions only in debug mode
        if (app()->environment('local')) {
            $exceptions->render(function (\Throwable $e, Request $request) {
                return response()->json([
                    'success' => false,
                    'message' => 'Server Error',
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ], 500);
            });
        }
    })

    ->create();