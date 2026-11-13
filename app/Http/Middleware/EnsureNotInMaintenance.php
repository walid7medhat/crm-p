<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Maintenance\MaintenanceModeService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * HTTP maintenance gate (all brands/hosts).
 *
 * - Scope "all": block everyone except Super Admin (and allowlisted infra).
 * - Scope "branches": block only users under selected CRM branches; public APIs stay up.
 * Bypass ONLY Spatie hasRole('super_admin') and never while impersonating.
 */
class EnsureNotInMaintenance
{
    public function __construct(private MaintenanceModeService $maintenance)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->maintenance->isEnabled()) {
            return $next($request);
        }

        if ($this->isAllowlisted($request)) {
            return $next($request);
        }

        $user = $this->resolveAuthenticatedUser($request);
        if ($this->canBypass($user)) {
            return $next($request);
        }

        if (! $this->maintenance->subjectIsAffected($user)) {
            return $next($request);
        }

        $status = $this->maintenance->statusPayload();

        return response()->json([
            'status' => false,
            'message' => $status['message'] ?: "We'll Be Back Soon",
            'maintenance' => true,
            'data' => [
                'enabled' => true,
                'scope' => $status['scope'] ?? 'all',
                'branch_user_ids' => $status['branch_user_ids'] ?? [],
                'title' => $status['title'],
                'message' => $status['message'],
                'estimated_return' => $status['estimated_return'],
            ],
        ], 503)->header('Retry-After', '60');
    }

    private function isAllowlisted(Request $request): bool
    {
        if ($request->isMethod('OPTIONS')) {
            return true;
        }

        $path = trim($request->path(), '/');

        $exact = [
            'up',
            'api/brand',
            'api/auth/login',
            'api/auth/logout',
            'api/maintenance/status',
        ];

        if (in_array($path, $exact, true)) {
            return true;
        }

        if ($request->is('build/*') || $request->is('assets/*') || $request->is('favicon.ico') || $request->is('favicon.svg')) {
            return true;
        }

        if (($request->isMethod('GET') || $request->isMethod('HEAD'))
            && ! $request->is('api/*')
            && ! $request->is('broadcasting/*')
            && ! $request->is('leads/export')
            && ! $request->is('fb/*')
            && ! $request->is('image/*')
        ) {
            return true;
        }

        return false;
    }

    private function resolveAuthenticatedUser(Request $request): ?User
    {
        try {
            if (! $request->bearerToken()) {
                return null;
            }

            $user = JWTAuth::setToken($request->bearerToken())->authenticate();

            return $user instanceof User ? $user : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function canBypass(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        try {
            $impersonatedBy = JWTAuth::getPayload()->get('impersonated_by');
            if ($impersonatedBy) {
                return false;
            }
        } catch (\Throwable) {
            // fall through
        }

        return $user->hasRole('super_admin');
    }
}
