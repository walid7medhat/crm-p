<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use Closure;
use Illuminate\Http\Request;

/**
 * Rejects JWTs for non-active accounts after successful token authentication.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user('api') ?? $request->user() ?? auth('api')->user() ?? auth()->user();

        if ($user && $user->status !== 'active') {
            return ApiResponse::error(
                'Your account is inactive. Please contact administrator.',
                403
            );
        }

        return $next($request);
    }
}
