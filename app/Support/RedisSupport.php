<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Detect Redis availability without fatal "Class Redis not found" errors.
 */
class RedisSupport
{
    private static ?bool $available = null;

    private static ?string $preferredClient = null;

    /**
     * phpredis extension or predis package (pure PHP).
     */
    public static function preferredClient(): ?string
    {
        if (self::$preferredClient !== null) {
            return self::$preferredClient;
        }

        $forced = env('REDIS_CLIENT');
        if ($forced === 'phpredis' && extension_loaded('redis')) {
            return self::$preferredClient = 'phpredis';
        }
        if ($forced === 'predis' && class_exists(\Predis\Client::class)) {
            return self::$preferredClient = 'predis';
        }

        if (extension_loaded('redis')) {
            return self::$preferredClient = 'phpredis';
        }
        if (class_exists(\Predis\Client::class)) {
            return self::$preferredClient = 'predis';
        }

        return self::$preferredClient = null;
    }

    public static function isAvailable(): bool
    {
        if (self::$available !== null) {
            return self::$available;
        }

        $client = self::preferredClient();
        if ($client === null) {
            return self::$available = false;
        }

        try {
            if ($client === 'phpredis') {
                $redis = new \Redis();
                $host = env('REDIS_HOST', '127.0.0.1');
                $port = (int) env('REDIS_PORT', 6379);
                $timeout = (float) env('REDIS_CONNECT_TIMEOUT', 1.5);
                if (!$redis->connect($host, $port, $timeout)) {
                    return self::$available = false;
                }
                $password = env('REDIS_PASSWORD');
                if ($password !== null && $password !== '') {
                    $redis->auth($password);
                }
                $redis->ping();
                $redis->close();

                return self::$available = true;
            }

            $predis = new \Predis\Client([
                'scheme'   => env('REDIS_SCHEME', 'tcp'),
                'host'     => env('REDIS_HOST', '127.0.0.1'),
                'port'     => (int) env('REDIS_PORT', 6379),
                'password' => env('REDIS_PASSWORD') ?: null,
                'database' => (int) env('REDIS_DB', 0),
            ], [
                'timeout' => (float) env('REDIS_CONNECT_TIMEOUT', 1.5),
            ]);
            $predis->ping();

            return self::$available = true;
        } catch (\Throwable $e) {
            Log::debug('Redis unavailable', ['reason' => $e->getMessage()]);

            return self::$available = false;
        }
    }

    public static function reset(): void
    {
        self::$available = null;
        self::$preferredClient = null;
    }
}
