<?php

namespace App\Http\Middleware;

use App\Services\Security\ForeignAccessMonitorService;
use Closure;
use Illuminate\Http\Request;

/**
 * Cheap mid-session foreign-access probe.
 * Cache-gated: only schedules GeoIP work when the client IP changes.
 * Never blocks or alters the response.
 */
class ProbeForeignAccess
{
    public function __construct(
        private ForeignAccessMonitorService $monitor,
    ) {
    }

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        try {
            $user = $request->user();
            if (! $user || $request->attributes->get('impersonated')) {
                return $response;
            }

            $ip = (string) $request->ip();
            if ($ip === '') {
                return $response;
            }

            $sessionKey = $request->input('session_id');
            if (! is_string($sessionKey) || strlen($sessionKey) < 8) {
                $sessionKey = null;
            }

            $this->monitor->probe(
                (int) $user->id,
                $ip,
                $request->userAgent(),
                $sessionKey,
                'session',
            );
        } catch (\Throwable) {
            // Monitoring must never affect CRM responses.
        }

        return $response;
    }
}
