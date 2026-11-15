<?php

namespace App\Services\Security;

use App\Events\ForeignAccessDetected;
use App\Helpers\LocationHelper;
use App\Jobs\EvaluateForeignAccessJob;
use App\Models\ForeignAccessEvent;
use App\Models\User;
use App\Support\Brand\BrandIsolation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Detects CRM access from outside the UAE using IP geolocation.
 * Monitoring / alerting only — never blocks users or invalidates sessions.
 */
class ForeignAccessMonitorService
{
    public const REASON_FOREIGN_LOGIN = 'foreign_login';

    public const REASON_FOREIGN_IP = 'foreign_ip';

    public const REASON_COUNTRY_CHANGE = 'country_change';

    /**
     * Cheap mid-session gate: compare request IP to the cached fingerprint.
     * Dispatches evaluation only when the IP changed (or first sighting).
     *
     * @return bool True when an evaluation was scheduled
     */
    public function probe(int $userId, string $ip, ?string $userAgent = null, ?string $sessionKey = null, string $trigger = 'request'): bool
    {
        if (! config('foreign_access.enabled', true)) {
            return false;
        }

        if ($ip === '') {
            return false;
        }

        $cooldown = max(30, (int) config('foreign_access.probe_cooldown_seconds', 120));
        $fingerprintKey = $this->fingerprintCacheKey($userId);
        $previous = Cache::get($fingerprintKey);
        $previousIp = is_array($previous) ? (string) ($previous['ip'] ?? '') : '';

        if ($previousIp === $ip) {
            return false;
        }

        // Serialize probes per user so burst traffic cannot enqueue duplicate jobs.
        $lockKey = "foreign_access:probe_lock:{$userId}";
        if (! Cache::add($lockKey, 1, $cooldown)) {
            return false;
        }

        Cache::put($fingerprintKey, [
            'ip' => $ip,
            'country_code' => is_array($previous) ? ($previous['country_code'] ?? null) : null,
            'probed_at' => now()->toIso8601String(),
        ], now()->addDay());

        EvaluateForeignAccessJob::dispatch(
            $userId,
            $ip,
            BrandIsolation::trustedKey(),
            $userAgent,
            $sessionKey,
            $trigger,
            is_array($previous) ? ($previous['country_code'] ?? null) : null,
        );

        return true;
    }

    /**
     * Full evaluation (GeoIP + persist + broadcast). Safe to call from a job.
     */
    public function evaluate(
        int $userId,
        string $ip,
        string $brandKey,
        ?string $userAgent = null,
        ?string $sessionKey = null,
        string $trigger = 'login',
        ?string $previousCountryCode = null,
    ): ?ForeignAccessEvent {
        if (! config('foreign_access.enabled', true)) {
            return null;
        }

        if ($ip === '') {
            return null;
        }

        $user = User::query()->find($userId);
        if (! $user) {
            return null;
        }

        $geo = LocationHelper::fromIp($ip);
        $countryCode = strtoupper((string) ($geo['country_code'] ?? ''));
        if ($countryCode === '' && strcasecmp((string) ($geo['country'] ?? ''), 'Local') === 0) {
            // Dev/loopback fallback — treat as non-foreign.
            $this->rememberFingerprint($userId, $ip, 'LOCAL');

            return null;
        }

        $homeCodes = array_map('strtoupper', (array) config('foreign_access.home_country_codes', ['AE']));
        $isForeign = $countryCode !== '' && ! in_array($countryCode, $homeCodes, true);

        $this->rememberFingerprint($userId, $ip, $countryCode !== '' ? $countryCode : null);

        if (! $isForeign) {
            return null;
        }

        $sessionKey = $sessionKey ?: $this->fallbackSessionKey($userId, $ip, $userAgent);
        $reason = $this->resolveReason($trigger, $previousCountryCode, $countryCode, $homeCodes);

        if ($this->isDuplicate($userId, $ip, $sessionKey, $countryCode, $reason)) {
            return null;
        }

        $uaInfo = $this->parseUserAgent($userAgent);

        $event = ForeignAccessEvent::query()->create([
            'user_id' => $userId,
            'brand_key' => $brandKey !== '' ? $brandKey : BrandIsolation::trustedKey(),
            'ip' => $ip,
            'country' => $geo['country'] ?? null,
            'country_code' => $countryCode,
            'city' => $geo['city'] ?? null,
            'region' => $geo['region'] ?? null,
            'isp' => $geo['isp'] ?? ($geo['org'] ?? null),
            'browser' => $uaInfo['browser'],
            'operating_system' => $uaInfo['os'],
            'user_agent' => $userAgent ? mb_substr($userAgent, 0, 1000) : null,
            'session_key' => $sessionKey,
            'detection_reason' => $reason,
            'status' => 'new',
            'occurred_at' => now(),
            'meta' => [
                'trigger' => $trigger,
                'previous_country_code' => $previousCountryCode,
                'timezone' => $geo['timezone'] ?? null,
            ],
        ]);

        $this->markDeduped($userId, $ip, $sessionKey, $countryCode, $reason);

        try {
            event(new ForeignAccessDetected($event, User::resolveDisplayName($user)));
        } catch (\Throwable $e) {
            Log::debug('foreign access broadcast skipped', ['error' => $e->getMessage()]);
        }

        return $event;
    }

    /**
     * @param  list<string>  $homeCodes
     */
    private function resolveReason(string $trigger, ?string $previousCountryCode, string $countryCode, array $homeCodes): string
    {
        $prev = $previousCountryCode ? strtoupper($previousCountryCode) : null;

        if ($prev && $prev !== $countryCode) {
            // Left UAE (or switched between foreign countries) mid-session.
            return self::REASON_COUNTRY_CHANGE;
        }

        if ($trigger === 'login') {
            return self::REASON_FOREIGN_LOGIN;
        }

        return self::REASON_FOREIGN_IP;
    }

    private function isDuplicate(int $userId, string $ip, string $sessionKey, string $countryCode, string $reason): bool
    {
        $ttl = max(60, (int) config('foreign_access.dedupe_ttl_seconds', 21600));
        $cacheKey = $this->dedupeCacheKey($userId, $ip, $sessionKey, $countryCode, $reason);

        if (Cache::has($cacheKey)) {
            return true;
        }

        // DB safety net (cache may be empty after deploy / flush).
        return ForeignAccessEvent::query()
            ->where('user_id', $userId)
            ->where('ip', $ip)
            ->where('session_key', $sessionKey)
            ->where('country_code', $countryCode)
            ->where('detection_reason', $reason)
            ->where('occurred_at', '>=', now()->subSeconds($ttl))
            ->exists();
    }

    private function markDeduped(int $userId, string $ip, string $sessionKey, string $countryCode, string $reason): void
    {
        $ttl = max(60, (int) config('foreign_access.dedupe_ttl_seconds', 21600));
        Cache::put(
            $this->dedupeCacheKey($userId, $ip, $sessionKey, $countryCode, $reason),
            1,
            $ttl
        );
    }

    private function rememberFingerprint(int $userId, string $ip, ?string $countryCode): void
    {
        Cache::put($this->fingerprintCacheKey($userId), [
            'ip' => $ip,
            'country_code' => $countryCode,
            'probed_at' => now()->toIso8601String(),
        ], now()->addDay());
    }

    private function fingerprintCacheKey(int $userId): string
    {
        return "foreign_access:fp:{$userId}";
    }

    private function dedupeCacheKey(int $userId, string $ip, string $sessionKey, string $countryCode, string $reason): string
    {
        return 'foreign_access:dedupe:'.sha1(implode('|', [$userId, $ip, $sessionKey, $countryCode, $reason]));
    }

    private function fallbackSessionKey(int $userId, string $ip, ?string $userAgent): string
    {
        return substr(sha1($userId.'|'.$ip.'|'.mb_substr((string) $userAgent, 0, 180)), 0, 40);
    }

    /**
     * Lightweight UA parser — no extra packages.
     *
     * @return array{browser: ?string, os: ?string}
     */
    public function parseUserAgent(?string $ua): array
    {
        if ($ua === null || trim($ua) === '') {
            return ['browser' => null, 'os' => null];
        }

        $os = match (true) {
            stripos($ua, 'Android') !== false => 'Android',
            stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false => 'iOS',
            stripos($ua, 'Windows') !== false => 'Windows',
            stripos($ua, 'Mac OS') !== false || stripos($ua, 'Macintosh') !== false => 'macOS',
            stripos($ua, 'Linux') !== false => 'Linux',
            default => null,
        };

        $browser = match (true) {
            stripos($ua, 'Edg/') !== false => 'Edge',
            stripos($ua, 'OPR/') !== false || stripos($ua, 'Opera') !== false => 'Opera',
            stripos($ua, 'Chrome/') !== false && stripos($ua, 'Chromium') === false => 'Chrome',
            stripos($ua, 'Firefox/') !== false => 'Firefox',
            stripos($ua, 'Safari/') !== false && stripos($ua, 'Chrome/') === false => 'Safari',
            default => null,
        };

        return ['browser' => $browser, 'os' => $os];
    }
}
