<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use App\Models\User;
use Closure;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * User #30 may "switch account" into sales users (User::canSwitchInto) but must not see
 * or send their chats. Blocks the chat API for any session switched into by that user
 * (the token's `impersonated_by` claim, set by AuthController::impersonate).
 * Runs after the JWT auth middleware, so the token is already validated.
 */
class BlockChatForSalesSwitcher
{
    public function handle($request, Closure $next)
    {
        try {
            $impersonatedBy = JWTAuth::getPayload()->get('impersonated_by');
        } catch (\Throwable $e) {
            $impersonatedBy = null;
        }

        if ((int) $impersonatedBy === User::SALES_SWITCHER_USER_ID) {
            return ApiResponse::error('Chat is not available while switched into this account', 403);
        }

        return $next($request);
    }
}
