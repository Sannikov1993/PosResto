<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security Headers Middleware
 *
 * Добавляет security headers ко всем ответам:
 * - X-Frame-Options: защита от clickjacking
 * - X-Content-Type-Options: защита от MIME sniffing
 * - Referrer-Policy: контроль передачи Referer
 * - Permissions-Policy: ограничение browser features
 * - HSTS: принудительный HTTPS (только в production)
 * - CSP: Content Security Policy (permissive start)
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self)');

        // HSTS только в production с HTTPS
        if (app()->isProduction() && $request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // CSP: permissive start — разрешаем inline scripts/styles для Vite HMR
        // В production будет более строгий CSP
        if (app()->isProduction()) {
            $response->headers->set(
                'Content-Security-Policy',
                "default-src 'self'; " .
                "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
                "style-src 'self' 'unsafe-inline'; " .
                "img-src 'self' data: blob:; " .
                "font-src 'self'; " .
                "connect-src 'self' ws: wss:; " .
                "frame-ancestors 'self';"
            );
        }

        return $response;
    }
}
