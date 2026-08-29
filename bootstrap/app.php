<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum SPA auth: session cookie + CSRF for the same-site frontend.
        $middleware->statefulApi();

        // Baseline throttle on every API route. Counters live in the cache store,
        // which is Redis in production (CACHE_STORE=redis), so limits are shared
        // across app instances — and swappable to the array store under test.
        $middleware->throttleApi('api');

        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // A throttled client gets a generic message plus Retry-After — details stay in the log.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 429 || ! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            logger()->warning('Rate limit hit', [
                'ip' => $request->ip(),
                'user_id' => $request->user()?->id,
                'path' => $request->path(),
            ]);

            return response()->json([
                'message' => 'Too many requests. Please slow down and try again shortly.',
            ], 429, array_filter(['Retry-After' => $e->getHeaders()['Retry-After'] ?? null]));
        });
    })
    ->booted(function (): void {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip()));

        // Auth endpoints are throttled on two axes. The per-account limit is the
        // strict one — it stops an attacker grinding a single password list.
        // The per-IP limit is deliberately looser: a whole office can share one
        // NAT address, and five sign-ins a minute would lock them all out.
        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(5)->by('auth-email:'.strtolower((string) $request->input('email'))),
            Limit::perMinute(20)->by('auth-ip:'.$request->ip()),
        ]);

        RateLimiter::for('mutations', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->id ?: $request->ip()));

        // Each assistant turn is several paid upstream calls, so it is limited
        // far more tightly than an ordinary write.
        RateLimiter::for('assistant', fn (Request $request) => Limit::perMinute(10)
            ->by('assistant:'.($request->user()?->id ?: $request->ip())));
    })
    ->create();
