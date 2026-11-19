<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LogEndpointUsage
{
    /** Per-request DB stats (one request per PHP worker on LSAPI/FPM). */
    private static bool $listening = false;
    private static bool $active = false;
    private static int $queryCount = 0;
    private static float $queryMs = 0.0;
    private static float $slowestMs = 0.0;
    private static ?string $slowestSql = null;

    public function handle(Request $request, Closure $next)
    {
        $request->attributes->set('usage_start', microtime(true));

        self::$active = true;
        self::$queryCount = 0;
        self::$queryMs = 0.0;
        self::$slowestMs = 0.0;
        self::$slowestSql = null;

        if (! self::$listening) {
            self::$listening = true;
            DB::listen(function (QueryExecuted $query) {
                if (! self::$active) {
                    return;
                }
                self::$queryCount++;
                self::$queryMs += (float) $query->time;
                if ($query->time > self::$slowestMs) {
                    self::$slowestMs = (float) $query->time;
                    // SQL with ? placeholders only — bindings (phones/emails) are never logged.
                    self::$slowestSql = mb_substr(preg_replace('/\s+/', ' ', $query->sql), 0, 300);
                }
            });
        }

        $response = $next($request);

        // Time to build the response (excludes sending it to the browser).
        $request->attributes->set('usage_app_ms', round((microtime(true) - $request->attributes->get('usage_start')) * 1000, 1));
        self::$active = false;

        return $response;
    }

    // Runs after the response is sent, so it doesn't slow the user down.
    public function terminate(Request $request, $response): void
    {
        try {
            $user = $request->user();

            Log::channel('endpoint_usage')->info($request->route()?->uri() ?? $request->path(), [
                'method'     => $request->method(),
                'user_id'    => $user?->id,
                'role'       => $user?->roles?->pluck('name')->first(),
                'ip'         => $request->ip(),
                'status'     => $response->getStatusCode(),
                'ms'         => round((microtime(true) - $request->attributes->get('usage_start', microtime(true))) * 1000, 1),
                'app_ms'     => $request->attributes->get('usage_app_ms'),
                'db_queries' => self::$queryCount,
                'db_ms'      => round(self::$queryMs, 1),
                'slowest_ms' => round(self::$slowestMs, 1),
                'slowest_sql'=> self::$slowestSql,

                'origin'     => $request->header('Origin'),
                'referer'    => $request->header('Referer'),
                'user_agent' => $request->userAgent(),
                'host'       => $request->getHost(),
                'app_header' => $request->header('X-App-Name'),

                // Query filters only — never log request bodies (lead saves carry phone/email).
                'filters'    => $request->isMethod('GET') ? \Illuminate\Support\Arr::except($request->query(), ['page']) : null,
            ]);
        } catch (\Throwable $e) {
            // never let logging break the request lifecycle
        }
    }
}
