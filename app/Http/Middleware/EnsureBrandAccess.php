<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use App\Support\Brand\BrandContext;
use App\Support\Brand\BrandResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Enforces brand allowlists after authentication.
 *
 * OIA (access.mode = open): immediate pass — no authorization change.
 * Luxvant (allowlist): Super Admin / env allowlist only; ordinary OIA JWTs → 403.
 */
class EnsureBrandAccess
{
    public function __construct(
        private BrandContext $brand,
        private BrandResolver $resolver,
    ) {
    }

    public function handle(Request $request, Closure $next)
    {
        $brand = $this->brand->get();
        $access = $brand['access'] ?? ['mode' => 'open'];

        // OIA and any open brand: preserve existing authenticated API behavior exactly.
        if (($access['mode'] ?? 'open') === 'open') {
            return $next($request);
        }

        $user = $request->user('api') ?? $request->user() ?? auth('api')->user() ?? auth()->user();
        if (!$user) {
            return $next($request);
        }

        $hostBrandKey = (string) ($brand['key'] ?? 'oia');

        $tokenBrandKey = $this->tokenBrandKey();
        if ($tokenBrandKey !== null && $tokenBrandKey !== $hostBrandKey) {
            if (!$this->resolver->userMayAccess($brand, $user)) {
                Log::warning('brand jwt mismatch rejected', [
                    'token_brand' => $tokenBrandKey,
                    'host_brand' => $hostBrandKey,
                    'user_id' => $user->id,
                    'host' => $request->headers->get('Host'),
                    'path' => $request->path(),
                ]);

                return ApiResponse::error('Tenant context mismatch. Please sign in again.', 403);
            }

            Log::info('brand jwt mismatch allowed for authorized user', [
                'token_brand' => $tokenBrandKey,
                'host_brand' => $hostBrandKey,
                'user_id' => $user->id,
                'host' => $request->headers->get('Host'),
            ]);
        }

        if ($this->resolver->userMayAccess($brand, $user)) {
            return $next($request);
        }

        Log::warning('brand access denied', [
            'brand' => $hostBrandKey,
            'user_id' => $user->id,
            'email' => $user->email,
            'host' => $request->headers->get('Host'),
            'path' => $request->path(),
        ]);

        return ApiResponse::error(
            'Your account is not authorized for ' . ($brand['company_name'] ?? 'this workspace') . '.',
            403
        );
    }

    private function tokenBrandKey(): ?string
    {
        try {
            $payload = auth('api')->payload();
            $key = $payload->get('brand_key');

            return is_string($key) && $key !== '' ? $key : null;
        } catch (\Throwable) {
            try {
                $payload = auth()->payload();
                $key = $payload->get('brand_key');

                return is_string($key) && $key !== '' ? $key : null;
            } catch (\Throwable) {
                return null;
            }
        }
    }
}
