<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
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
        // ADD SECURITY HEADERS TO API GROUP (RECOMMENDED)
        // =============================================
        // This adds security headers to all API routes automatically
        $middleware->api(prepend: [
            SecurityHeaders::class,
        ]);

        // =============================================
        // ALTERNATIVE: Add to global middleware (ALL requests)
        // Uncomment the line below if you want headers on ALL routes (web + api)
        // =============================================
        // $middleware->prepend([
        //     SecurityHeaders::class,
        // ]);

        // =============================================
        // OPTIONAL: Configure CORS if needed
        // =============================================
        // $middleware->trustProxies(at: '*');

    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
