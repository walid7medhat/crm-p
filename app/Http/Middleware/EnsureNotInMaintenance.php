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
 * - Scope "all": block everyone except Super Admin, the optional admin-role exemption, and picked people.
 * - Scope "branches": block only users under selected CRM branches; public APIs stay up.
 * - Scope "users": block only the selected people; everyone else and public APIs stay up.
 * Bypass Spatie hasRole('super_admin'), including a Switch account session
 * started by a super admin (JWT claim impersonated_by).
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
        if ($this->switchedBySuperAdmin()) {
            return true;
        }

        return $user && $user->hasRole('super_admin');
    }

    /**
     * Switch account stamps impersonated_by with the actor id.
     * Only a super admin actor keeps the switched session out of maintenance.
     */
    private function switchedBySuperAdmin(): bool
    {
        try {
            $actorId = (int) JWTAuth::getPayload()->get('impersonated_by');
            if ($actorId <= 0) {
                return false;
            }

            $actor = User::query()->find($actorId);

            return $actor && $actor->hasRole('super_admin');
        } catch (\Throwable) {
            return false;
        }
    }
}
