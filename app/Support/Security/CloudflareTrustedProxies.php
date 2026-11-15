<?php

namespace App\Support\Security;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Resolve Cloudflare edge CIDRs for Laravel TrustProxies.
 *
 * Trusted proxies are NEVER "*". Forwarded headers are only honored when the
 * immediate REMOTE_ADDR falls inside these Cloudflare ranges (IPv4 + IPv6).
 */
class CloudflareTrustedProxies
{
    /** @var list<string>|null */
    private static ?array $testingOverride = null;

    /**
     * @param  list<string>|null  $cidrs  Pass null to clear the override
     */
    public static function fake(?array $cidrs): void
    {
        self::$testingOverride = $cidrs;
    }

    /**
     * @return list<string>
     */
    public static function cidrs(): array
    {
        if (self::$testingOverride !== null) {
            return self::normalize(self::$testingOverride);
        }

        $env = self::envOverride();
        if (is_string($env) && trim($env) !== '') {
            $trimmed = trim($env);
            if ($trimmed === '*' || $trimmed === '**') {
                Log::warning('TRUSTED_PROXIES=* rejected; using Cloudflare snapshot instead');
            } else {
                return self::normalize(preg_split('/[\s,]+/', $trimmed) ?: []);
            }
        }

        try {
            if (function_exists('app') && app()->bound('cache')) {
                $cached = Cache::get(self::cacheKey());
                if (is_array($cached) && $cached !== []) {
                    return self::normalize($cached);
                }
            }
        } catch (\Throwable) {
            // Boot-safe: fall through to snapshot files.
        }

        $fromStorage = self::readSnapshotFile(self::snapshotPath());
        if ($fromStorage !== []) {
            return $fromStorage;
        }

        return self::readSnapshotFile(self::bundledSnapshotPath());
    }

    private static function envOverride(): ?string
    {
        try {
            $fromConfig = config('trusted_proxies.env_override');
            if (is_string($fromConfig)) {
                return $fromConfig;
            }
        } catch (\Throwable) {
            // ignore
        }

        $raw = $_ENV['TRUSTED_PROXIES'] ?? $_SERVER['TRUSTED_PROXIES'] ?? getenv('TRUSTED_PROXIES');

        return is_string($raw) ? $raw : null;
    }

    private static function cacheKey(): string
    {
        try {
            return (string) config('trusted_proxies.cache_key', 'cloudflare.trusted_proxy_cidrs');
        } catch (\Throwable) {
            return 'cloudflare.trusted_proxy_cidrs';
        }
    }

    private static function snapshotPath(): string
    {
        try {
            $path = config('trusted_proxies.snapshot_path');
            if (is_string($path) && $path !== '') {
                return $path;
            }
        } catch (\Throwable) {
            // ignore
        }

        return storage_path('app/cloudflare/ips.json');
    }

    private static function bundledSnapshotPath(): string
    {
        try {
            $path = config('trusted_proxies.bundled_snapshot_path');
            if (is_string($path) && $path !== '') {
                return $path;
            }
        } catch (\Throwable) {
            // ignore
        }

        return resource_path('security/cloudflare_ips.json');
    }

    /**
     * Fetch official Cloudflare IP lists and persist snapshots + cache.
     *
     * @return list<string>
     */
    public static function refreshFromOfficialSources(): array
    {
        $v4Url = (string) (config('trusted_proxies.ipv4_url') ?: 'https://www.cloudflare.com/ips-v4');
        $v6Url = (string) (config('trusted_proxies.ipv6_url') ?: 'https://www.cloudflare.com/ips-v6');

        $v4 = Http::timeout(10)->get($v4Url);
        $v6 = Http::timeout(10)->get($v6Url);

        if (! $v4->ok() || ! $v6->ok()) {
            throw new \RuntimeException('Failed to download Cloudflare IP ranges (HTTP error).');
        }

        $cidrs = self::normalize(array_merge(
            preg_split('/\s+/', trim($v4->body())) ?: [],
            preg_split('/\s+/', trim($v6->body())) ?: [],
        ));

        if ($cidrs === []) {
            throw new \RuntimeException('Cloudflare IP lists were empty.');
        }

        $payload = [
            'fetched_at' => now()->utc()->toIso8601String(),
            'sources' => [$v4Url, $v6Url],
            'cidrs' => $cidrs,
        ];

        self::writeSnapshotFile(self::snapshotPath(), $payload);
        self::writeSnapshotFile(self::bundledSnapshotPath(), $payload);

        try {
            Cache::put(
                self::cacheKey(),
                $cidrs,
                (int) (config('trusted_proxies.cache_ttl_seconds') ?: 86_400)
            );
        } catch (\Throwable) {
            // Snapshot files are enough for TrustProxies boot.
        }

        return $cidrs;
    }

    /**
     * @param  list<string>|array<int, mixed>  $cidrs
     * @return list<string>
     */
    private static function normalize(array $cidrs): array
    {
        $out = [];
        foreach ($cidrs as $cidr) {
            if (! is_string($cidr)) {
                continue;
            }
            $cidr = trim($cidr);
            if ($cidr === '' || $cidr === '*' || $cidr === '**') {
                continue;
            }
            // Accept IPv4/IPv6 address or CIDR only.
            if (! self::isValidProxyEntry($cidr)) {
                continue;
            }
            $out[] = $cidr;
        }

        return array_values(array_unique($out));
    }

    private static function isValidProxyEntry(string $value): bool
    {
        if (str_contains($value, '/')) {
            [$ip, $mask] = explode('/', $value, 2);
            if (! is_numeric($mask)) {
                return false;
            }
            $mask = (int) $mask;
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $mask >= 0 && $mask <= 32;
            }
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                return $mask >= 0 && $mask <= 128;
            }

            return false;
        }

        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * @return list<string>
     */
    private static function readSnapshotFile(string $path): array
    {
        if ($path === '' || ! is_readable($path)) {
            return [];
        }

        try {
            $raw = file_get_contents($path);
            if ($raw === false || $raw === '') {
                return [];
            }
            $data = json_decode($raw, true);
            if (! is_array($data)) {
                return [];
            }
            $cidrs = $data['cidrs'] ?? $data;

            return is_array($cidrs) ? self::normalize($cidrs) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  array{fetched_at?: string, sources?: list<string>, cidrs: list<string>}  $payload
     */
    private static function writeSnapshotFile(string $path, array $payload): void
    {
        if ($path === '') {
            return;
        }

        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $path,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
        );
    }
}
