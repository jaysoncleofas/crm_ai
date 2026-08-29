<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A deactivated account keeps its session but loses API access immediately —
 * policies enforce this per-record, this closes the endpoints that have none.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->is_active) {
            abort(403, 'This account has been deactivated.');
        }

        return $next($request);
    }
}
