<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WebSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', "geolocation=(), microphone=(), camera=(), payment=(), usb=()");

        if (app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        $response->headers->set('Content-Security-Policy', $this->csp($request));

        return $response;
    }

    private function csp(Request $request): string
    {
        $isAdminRoute = $request->is('admin') || $request->is('admin/*');

        $scriptSrc = [
            "'self'",
            "'unsafe-inline'",
            'https://cdn.jsdelivr.net',
            'https://unpkg.com',
        ];

        $styleSrc = [
            "'self'",
            "'unsafe-inline'",
            'https://fonts.googleapis.com',
            'https://cdnjs.cloudflare.com',
            'https://unpkg.com',
        ];

        $fontSrc = [
            "'self'",
            'https://fonts.gstatic.com',
            'https://cdnjs.cloudflare.com',
            'data:',
        ];

        $imgSrc = [
            "'self'",
            'data:',
            'https:',
        ];

        // Admin dashboard uses Leaflet map tiles.
        if ($isAdminRoute) {
            $imgSrc[] = 'https://*.basemaps.cartocdn.com';
            $imgSrc[] = 'https://*.tile.openstreetmap.org';
        }

        return implode('; ', [
            "default-src 'self'",
            'script-src '.implode(' ', $scriptSrc),
            'style-src '.implode(' ', $styleSrc),
            'font-src '.implode(' ', $fontSrc),
            'img-src '.implode(' ', array_unique($imgSrc)),
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ]);
    }
}
