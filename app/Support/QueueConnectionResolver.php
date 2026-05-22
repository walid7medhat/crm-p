<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

class QueueConnectionResolver
{
    private static ?string $resolved = null;

    /**
     * Resolve a safe queue connection name. Never returns "redis" unless Redis works.
     */
    public static function resolve(?string $preferred = null): string
    {
        if (self::$resolved !== null) {
            return self::$resolved;
        }

        $preferred = strtolower(trim((string) ($preferred ?? env('QUEUE_CONNECTION', 'database'))));

        if ($preferred === '' || $preferred === 'sync') {
            return self::$resolved = $preferred ?: 'database';
        }

        if ($preferred === 'redis') {
            if (RedisSupport::isAvailable()) {
                return self::$resolved = 'redis';
            }

            Log::warning(
                'QUEUE_CONNECTION=redis but Redis is unreachable or no client (phpredis/predis). '
                . 'Falling back to database queue.',
                ['redis_client' => RedisSupport::preferredClient()]
            );

            return self::$resolved = 'database';
        }

        // database, beanstalkd, sqs — pass through
        return self::$resolved = $preferred;
    }

    public static function reset(): void
    {
        self::$resolved = null;
    }
}
