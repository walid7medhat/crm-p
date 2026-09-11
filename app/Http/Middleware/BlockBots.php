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

        // Full bypass for super_admin and user 30
        if ($user && ($user->hasRole('super_admin') || $user->id == 30)) {
            return $next($request);
        }

        // Power users get much higher limits instead of a full bypass — they
        // still go through the account-status/bot-detection checks below,
        // just with a far higher ceiling before hitting a 429.
        $powerUserIds = [33];
        $isPowerUser = $user && in_array($user->id, $powerUserIds, true);
        $rateMultiplier = $isPowerUser ? 10 : 1;

        // Account status check
        if ($user && $user->status != 'active') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            abort(403, 'Account inactive');
        }

        // User-Agent check
        $agent = strtolower($request->header('User-Agent') ?? '');
        $botKeywords = ['curl', 'python', 'scrapy', 'wget', 'perl', 'ruby', 'java/', 'http-client'];
        
        if ($agent === '' || str_contains($agent, 'bot')) {
            abort(403, 'Bots not allowed');
        }
        
        foreach ($botKeywords as $keyword) {
            if (str_contains($agent, $keyword)) {
                abort(403, 'Bots not allowed');
            }
        }

        // --------------------------------------
        // 1. Global rate (for normal pages)
        // --------------------------------------
        $globalLimit = ($user ? 400 : 120) * $rateMultiplier;
        $key = 'hits_' . ($user?->id ?? $request->ip());
        $count = cache()->get($key, 0);
        $count++;
        cache()->put($key, $count, now()->addSeconds(60));

        if ($count > $globalLimit) {
            if (!$user) Auth::logout();
            abort(429, 'Too many requests');
        }

        // --------------------------------------
        // 2. Per-endpoint abuse detection (limit varies by request type)
        // --------------------------------------
        $path = $request->path();
        $method = $request->method();
        
        // Different thresholds depending on the endpoint type
        $limits = [
            'write' => ($user ? 60 : 20) * $rateMultiplier,
            'read' => ($user ? 300 : 100) * $rateMultiplier,
            'auth' => 10,
        ];
        
        // Classify which type this endpoint belongs to
        $isWriteRequest = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE']);
        $isAuthRequest = str_contains($path, 'login') || str_contains($path, 'register');
        
        if ($isAuthRequest) {
            $limit = $limits['auth'];
        } elseif ($isWriteRequest) {
            $limit = $limits['write'];
        } else {
            $limit = $limits['read'];
        }
        
        $routeKey = 'route_hits_' . ($user?->id ?? $request->ip()) . ':' . $path . ':' . $method;
        $routeCount = cache()->get($routeKey, 0);
        $routeCount++;
        
        // Shorter window for repeated actions
        $duration = $isAuthRequest ? 60 * 5 : 60; // Auth: 5 minutes, everything else: 1 minute
        cache()->put($routeKey, $routeCount, now()->addSeconds($duration));
        
        if ($routeCount > $limit) {
            abort(429, "Rate limit exceeded for this action. Please wait {$duration} seconds.");
        }
        
        // Temporary block check
        $tempBlockKey = 'temp_block_' . ($user?->id ?? $request->ip());
        if (cache()->get($tempBlockKey)) {
            abort(429, 'You are temporarily blocked. Please try again later.');
        }

        // --------------------------------------
        // 3. Burst detection — DoS protection only
        // --------------------------------------
        $burstKey = 'burst_' . ($user?->id ?? $request->ip());
        $burstCount = cache()->get($burstKey, 0);
        $burstCount++;
        
        if ($burstCount == 1) {
            cache()->put($burstKey, $burstCount, now()->addSeconds(10));
        } else {
            cache()->put($burstKey, $burstCount, now()->addSeconds(10));
        }
        
        // 200 requests in 10 seconds = 20 requests/second — real DoS, not a normal user
        if ($burstCount > 200 * $rateMultiplier) {
            // Even here, don't ban the account permanently — just a temporary block
            $tempBlockKey = 'temp_block_' . ($user?->id ?? $request->ip());
            cache()->put($tempBlockKey, true, now()->addMinutes(15));
            
            if ($user) Auth::logout();
            abort(429, 'Rate limit exceeded. Too many requests in a short time.');
        }

        return $next($request);
    }
}