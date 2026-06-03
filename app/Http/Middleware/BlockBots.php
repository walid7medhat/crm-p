<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BlockBots
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();
if($user && !$user->hasRole('super_admin')){
        /*
        |--------------------------------------
        | 1. Blocked / Inactive user
        |--------------------------------------
        */
        if ($user && $user->status != 'active' && !$user->hasRole('super_admin')) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                abort(403, 'Account inactive');
            }

        /*
        |--------------------------------------
        | 2. Basic User-Agent filter
        |--------------------------------------
        */
        $agent = strtolower($request->header('User-Agent') ?? '');

        if (
            $agent === '' ||
            str_contains($agent, 'bot') ||
            str_contains($agent, 'curl') ||
            str_contains($agent, 'python') ||
            str_contains($agent, 'scrapy') ||
            str_contains($agent, 'wget')
        ) {
            abort(403, 'Bots not allowed');
        }

        /*
        |--------------------------------------
        | 3. Rate limit per user/IP
        |--------------------------------------
        */
        $key = 'hits_' . ($user?->id ?? $request->ip());

        $count = cache()->get($key, 0);
        $count++;

        cache()->put($key, $count, now()->addSeconds(60));

        if ($count > 120) {
            Auth::logout();
            abort(429, 'Too many requests');
        }

        /*
        |--------------------------------------
        | 4. Endpoint abuse detection
        |--------------------------------------
        */
        $routeKey = 'route_hits_' . ($user?->id ?? $request->ip()) . ':' . $request->path();

        $routeCount = cache()->get($routeKey, 0);
        $routeCount++;

        cache()->put($routeKey, $routeCount, now()->addSeconds(60));

        if ($routeCount > 50) {
            $user->update(['status'=>'blocked']);
            Auth::logout();
            abort(429, 'Suspicious activity detected');
        }

        /*
        |--------------------------------------
        | 5. Burst detection (rapid requests)
        |--------------------------------------
        */
        $burstKey = 'burst_' . ($user?->id ?? $request->ip());

        cache()->increment($burstKey);
        cache()->put($burstKey, cache()->get($burstKey), now()->addSeconds(10));

        if (cache()->get($burstKey) > 200) {
            Auth::logout();
            abort(429, 'Rate limit exceeded');
        }
}
        return $next($request);
    }
}