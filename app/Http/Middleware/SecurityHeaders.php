<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Security headers configuration
     */
    protected $headers = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => "geolocation=(), microphone=(), camera=(), payment=(), usb=()",
        'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
        'Pragma' => 'no-cache',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);
        
        // Apply all security headers
        foreach ($this->headers as $key => $value) {
            $response->headers->set($key, $value);
        }
        
        // Apply environment-specific headers
        $this->applyEnvironmentSpecificHeaders($response);
        
        // Remove sensitive server information
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');
        
        return $response;
    }
    
    /**
     * Apply environment-specific security headers
     *
     * @param \Symfony\Component\HttpFoundation\Response $response
     * @return void
     */
    protected function applyEnvironmentSpecificHeaders(Response $response): void
    {
        // Production-only headers
        if (app()->environment('production')) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
            $response->headers->set(
                'Content-Security-Policy',
                $this->getProductionCSP()
            );
        } 
        // Development environment - less restrictive CSP
        elseif (app()->environment('local', 'development')) {
            $response->headers->set(
                'Content-Security-Policy',
                $this->getDevelopmentCSP()
            );
        }
    }
    
    /**
     * Get Production Content Security Policy
     */
    protected function getProductionCSP(): string
    {
        $policies = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net",
            "img-src 'self' data: https: http:",
            "connect-src 'self' https://api.cashfree.com https://cashfree.com",
            "frame-src 'self' https://cashfree.com https://test.cashfree.com",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "upgrade-insecure-requests",
        ];
        return implode('; ', $policies);
    }
    
    /**
     * Get Development Content Security Policy
     */
    protected function getDevelopmentCSP(): string
    {
        $policies = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' http://localhost:* https://*",
            "style-src 'self' 'unsafe-inline' https://* http://localhost:*",
            "font-src 'self' data: https://* http://localhost:*",
            "img-src 'self' data: https://* http://localhost:*",
            "connect-src 'self' https://* http://localhost:* ws://localhost:*",
            "frame-src 'self' https://* http://localhost:*",
            "frame-ancestors 'self' http://localhost:*",
            "form-action 'self' http://localhost:*",
            "base-uri 'self'",
            "object-src 'none'",
        ];
        return implode('; ', $policies);
    }
    
    /**
     * Get current security headers for debugging
     */
    public function getCurrentHeaders(): array
    {
        return $this->headers;
    }
    
    /**
     * Add custom security header dynamically
     */
    public function addHeader(string $key, string $value): self
    {
        $this->headers[$key] = $value;
        return $this;
    }
    
    /**
     * Remove a security header
     */
    public function removeHeader(string $key): self
    {
        unset($this->headers[$key]);
        return $this;
    }
}