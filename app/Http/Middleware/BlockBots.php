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

        if ($user && ($user->hasRole('super_admin') || $user->id == 30)) {
            return $next($request);
        }

        $powerUserIds = [33];
        $isPowerUser = $user && in_array($user->id, $powerUserIds, true);
        $rateMultiplier = $isPowerUser ? 10 : 1;

        if ($user && $user->status != 'active') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            abort(403, 'Account inactive');
        }

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

        $identity = $user?->id ?? $request->ip();

        // Cheap short-circuit — check the block BEFORE doing any counter work.
        $tempBlockKey = "temp_block_{$identity}";
        if (cache()->get($tempBlockKey)) {
            return $this->tooMany('You are temporarily blocked. Please try again later.', 60);
        }

        // --------------------------------------
        // 1. Global rate (atomic increment — avoids the get/put race where
        //    concurrent requests can all read the same stale count)
        // --------------------------------------
        $globalLimit = ($user ? 400 : 120) * $rateMultiplier;
        $key = "hits_{$identity}";
        $count = $this->hit($key, 60);

        if ($count > $globalLimit) {
            if (!$user) Auth::logout();
            return $this->tooMany('Too many requests', 60);
        }

        // --------------------------------------
        // 2. Per-endpoint abuse detection
        // --------------------------------------
        $path = $request->path();
        $method = $request->method();

        $limits = [
            'write' => ($user ? 60 : 20) * $rateMultiplier,
            'read' => ($user ? 300 : 100) * $rateMultiplier,
            'auth' => 10,
        ];

        $isWriteRequest = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE']);
        $isAuthRequest = str_contains($path, 'login') || str_contains($path, 'register');
        $limit = $isAuthRequest ? $limits['auth'] : ($isWriteRequest ? $limits['write'] : $limits['read']);
        $duration = $isAuthRequest ? 300 : 60;

        $routeKey = "route_hits_{$identity}:{$path}:{$method}";
        $routeCount = $this->hit($routeKey, $duration);

        if ($routeCount > $limit) {
            // Scoped penalty: only THIS endpoint backs off. A retry storm
            // hitting one flaky route must never lock the user out of
            // notifications/sidebar/etc — those are unrelated endpoints.
            return $this->tooMany(
                "Rate limit exceeded for this action. Please wait {$duration} seconds.",
                $duration
            );
        }

        // --------------------------------------
        // 3. Burst detection — DoS protection only
        // --------------------------------------
        $burstKey = "burst_{$identity}";
        $burstCount = $this->hit($burstKey, 10);

        if ($burstCount > 200 * $rateMultiplier) {
            cache()->put($tempBlockKey, true, now()->addMinutes(5)); // was 15 — see note below
            // Don't force-logout an authenticated user for a burst that is very
            // plausibly a client retry bug rather than actual abuse. Guests/IPs
            // (no session to protect) still get logged out of any stray session.
            if (!$user) Auth::logout();
            return $this->tooMany('Rate limit exceeded. Too many requests in a short time.', 300);
        }

        return $next($request);
    }

    /**
     * Atomically increment a counter, seeding its TTL only on first hit.
     */
    private function hit(string $key, int $ttlSeconds): int
    {
        $count = cache()->increment($key);
        if ($count === 1) {
            cache()->put($key, 1, now()->addSeconds($ttlSeconds));
        }
        return $count;
    }

    private function tooMany(string $message, int $retryAfterSeconds)
    {
        return response()->json(['message' => $message], 429)
            ->header('Retry-After', $retryAfterSeconds);
    }
}