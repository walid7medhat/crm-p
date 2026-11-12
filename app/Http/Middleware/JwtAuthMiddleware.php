<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Http\Middleware\Authenticate as TymonAuthenticate;

/**
 * Live jwt.auth entrypoint.
 *
 * Step 1 — Tymon Authenticate: identical to the package middleware that was
 *           already winning the alias race (preserves current OIA auth behavior).
 * Step 2 — EnsureBrandAccess: no-op when brand access.mode is "open" (OIA);
 *           enforces Luxvant allowlist + JWT brand_key checks only for Luxvant.
 */
class JwtAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        auth()->shouldUse('api');

        return app(TymonAuthenticate::class)->handle(
            $request,
            function (Request $request) use ($next) {
                return app(EnsureBrandAccess::class)->handle($request, $next);
            }
        );
    }
}
