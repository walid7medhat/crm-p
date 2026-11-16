<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Branch\BranchView;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Reads `X-Branch-View` (abu_dhabi|dubai) and turns on the branch filter for
 * READ requests of super_admin / user #30 on the OIA host. Anything else → off.
 */
class ApplyBranchView
{
    public function handle(Request $request, Closure $next): Response
    {
        BranchView::reset();

        $key = BranchView::normalizeKey($request->header(BranchView::HEADER));

        if ($key !== null && ($request->isMethod('GET') || $request->isMethod('HEAD'))) {
            $user = $this->resolveUser($request);
            if (BranchView::canUse($user)) {
                BranchView::set($key);
            }
        }

        try {
            return $next($request);
        } finally {
            BranchView::reset();
        }
    }

    private function resolveUser(Request $request): ?User
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
}
