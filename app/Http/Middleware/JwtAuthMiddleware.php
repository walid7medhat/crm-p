<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
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
            Auth::setUser($user);
        } catch (TokenExpiredException $e) {
            return ApiResponse::error('Token expired', 401);
        } catch (TokenInvalidException $e) {
            return ApiResponse::error('Token invalid', 401);
        } catch (JWTException $e) {
            return ApiResponse::error('Token absent', 401);
        }

        $request->attributes->set('user', $user);

        return $next($request);
    }
}
