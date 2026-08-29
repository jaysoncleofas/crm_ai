<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defence-in-depth headers. nginx sets these too, but the app must stand on its
 * own behind any proxy (and in `artisan serve` / tests).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Content-Security-Policy' => $this->contentSecurityPolicy(),
        ];

        if ($request->secure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $key => $value) {
            $response->headers->set($key, $value, false);
        }

        $response->headers->remove('X-Powered-By');

        return $response;
    }

    protected function contentSecurityPolicy(): string
    {
        // Vite's dev server needs its own origin plus websocket HMR; production
        // serves only bundled assets from the app origin.
        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "object-src 'none'",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
        ];

        if (app()->isProduction()) {
            $directives[] = "script-src 'self'";
            $directives[] = "style-src 'self' 'unsafe-inline'";
            $directives[] = "connect-src 'self'";
        } else {
            $dev = rtrim((string) config('app.vite_dev_server', 'http://localhost:5173'), '/');
            $ws = str_replace(['http://', 'https://'], ['ws://', 'wss://'], $dev);

            $directives[] = "script-src 'self' 'unsafe-eval' {$dev}";
            $directives[] = "style-src 'self' 'unsafe-inline' {$dev}";
            $directives[] = "connect-src 'self' {$dev} {$ws}";
        }

        return implode('; ', $directives);
    }
}
