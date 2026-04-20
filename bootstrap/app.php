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

        // =============================================
        // REGISTER MIDDLEWARE ALIASES
        // =============================================
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'security.headers' => SecurityHeaders::class,
        ]);

        // =============================================
        // APPLY SECURITY HEADERS TO API ROUTES
        // =============================================
        $middleware->api(prepend: [
            SecurityHeaders::class,
        ]);

        // OPTIONAL: Apply globally (web + api)
        // $middleware->prepend([
        //     SecurityHeaders::class,
        // ]);
    })

    ->withExceptions(function (Exceptions $exceptions) {

        // =============================================
        // HANDLE UNAUTHENTICATED (TOKEN INVALID/EXPIRED)
        // =============================================
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'error' => 'Token expired or invalid.'
            ], 401);
        });

        // =============================================
        // HANDLE FORBIDDEN (NO PERMISSION)
        // =============================================
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
                'error' => 'You do not have permission to access this resource.'
            ], 403);
        });

        // =============================================
        // HANDLE ALL OTHER EXCEPTIONS (SAFE FALLBACK)
        // =============================================
        $exceptions->render(function (\Throwable $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => 'Server Error',
                'error' => app()->environment('local') ? $e->getMessage() : 'Something went wrong'
            ], 500);
        });

    })

    ->create();