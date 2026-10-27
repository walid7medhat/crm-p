<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use App\Helpers\ApiResponse;

class JwtAuthMiddleware
{
    public function handle($request, Closure $next)
    {
        try {
            auth()->shouldUse('api');
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return ApiResponse::error('Unauthorized', 401);
            }
            // Inactive accounts must not use protected APIs (even with a previously issued JWT).
            if (($user->status ?? null) !== 'active') {
                return ApiResponse::error('Account inactive', 403);
            }
            Auth::setUser($user);
        } catch (TokenExpiredException $e) {
            return ApiResponse::error('Token expired', 401);
        } catch (TokenInvalidException $e) {
            return ApiResponse::error('Token invalid', 401);
        } catch (JWTException $e) {
            return ApiResponse::error('Token absent', 401);
        }

        $request->attributes->set('user', $user);

        // Keep "online" fresh while the user is actively using the CRM (at most every 5 min).
        $cacheKey = 'user-presence-touch:' . $user->id;
        if (! Cache::has($cacheKey)) {
            Cache::put($cacheKey, true, now()->addMinutes(5));
            $user->forceFill(['last_login_at' => now()])->save();
        }

        return $next($request);
    }
}
