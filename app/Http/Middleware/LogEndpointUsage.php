<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogEndpointUsage
{
    public function handle(Request $request, Closure $next)
    {
        $request->attributes->set('usage_start', microtime(true));

        return $next($request);
    }

    // Runs after the response is sent, so it doesn't slow the user down.
    public function terminate(Request $request, $response): void
{
    try {
        $user = $request->user();

        Log::channel('endpoint_usage')->info($request->route()?->uri() ?? $request->path(), [
            'user_id'    => $user?->id,
            'role'       => $user?->roles?->pluck('name')->first(),
            'ip'         => $request->ip(),
            'status'     => $response->getStatusCode(),
            'ms'         => round((microtime(true) - $request->attributes->get('usage_start', microtime(true))) * 1000, 1),

            'origin'     => $request->header('Origin'),
            'referer'    => $request->header('Referer'),
            'user_agent' => $request->userAgent(),
            'host'       => $request->getHost(),
            'app_header' => $request->header('X-App-Name'),

            'filters'    => $request->except(['page']),
        ]);
    } catch (\Throwable $e) {
        // never let logging break the request lifecycle
    }
}
}