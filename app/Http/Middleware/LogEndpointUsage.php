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
        $user = $request->user();

        Log::channel('endpoint_usage')->info($request->route()?->uri() ?? $request->path(), [
                'user_id'    => $user?->id,
                'role'       => $user?->roles?->pluck('name')->first(),
                'ip'         => $request->ip(),
                'status'     => $response->getStatusCode(),
                'ms'         => round((microtime(true) - $request->attributes->get('usage_start', microtime(true))) * 1000, 1),

                // Who/what project is calling
                'origin'     => $request->header('Origin'),       // browser calls from another site
                'referer'    => $request->header('Referer'),
                'user_agent' => $request->userAgent(),            // e.g. axios, Guzzle, curl, Postman
                'token_name' => $user?->currentAccessToken()?->name ?? null, // Sanctum token name

                'filters'    => array_keys($request->except(['page'])),
            ]);
    }
}