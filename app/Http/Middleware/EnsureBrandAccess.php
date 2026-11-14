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
 * Company accounts are exclusive: OIA members cannot use the Luxvant host and
 * Luxvant members cannot use the OIA host. Super Admin is shared on both.
 * Tokens whose brand_key claim differs from the host brand → 403 (non super admin).
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

        $user = $request->user('api') ?? $request->user() ?? auth('api')->user() ?? auth()->user();
        if (!$user) {
            return $next($request);
        }

        $hostBrandKey = (string) ($brand['key'] ?? 'oia');

        // A token minted on one company's host is never valid on the other's,
        // except for the shared Platform Super Admin.
        $tokenBrandKey = $this->tokenBrandKey();
        if ($tokenBrandKey !== null && $tokenBrandKey !== $hostBrandKey) {
            if (!BrandResolver::isSuperAdmin($user)) {
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
