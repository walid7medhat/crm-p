<?php

namespace App\Support;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Log;

class Bitrix24Config
{
    public static function applyDefaults(Application $app): void
    {
        $config = $app->make('config');

        $webhook = trim((string) ($config->get('bitrix24.webhook_url') ?? ''));
        if ($webhook === '') {
            Log::debug('BITRIX24_WEBHOOK_URL is not set — sync endpoints will fail until configured.');
        } elseif (!filter_var($webhook, FILTER_VALIDATE_URL)) {
            Log::warning('BITRIX24_WEBHOOK_URL does not look like a valid URL.', ['value' => $webhook]);
        }

        $config->set('bitrix24.webhook_url', $webhook !== '' ? rtrim($webhook, '/') . '/' : null);

        $config->set('bitrix24.pages_per_job', self::clampInt(
            $config->get('bitrix24.pages_per_job'),
            1,
            50,
            20
        ));

        $config->set('bitrix24.parallel_shards', self::clampInt(
            $config->get('bitrix24.parallel_shards'),
            1,
            100,
            1
        ));

        $config->set('bitrix24.db_insert_chunk', self::clampInt(
            $config->get('bitrix24.db_insert_chunk'),
            50,
            2000,
            500
        ));

        $config->set('bitrix24.batch_size', self::clampInt(
            $config->get('bitrix24.batch_size'),
            1,
            50,
            20
        ));

        $config->set('bitrix24.http_timeout', self::clampInt(
            $config->get('bitrix24.http_timeout'),
            10,
            300,
            60
        ));

        $config->set('bitrix24.queue', self::resolveSyncQueueName($config));
    }

    /**
     * Local dev often runs `php artisan queue:work` without --queue=bitrix24.
     * Use the default queue on database driver unless explicitly forced.
     */
    public static function resolveSyncQueueName($config = null): string
    {
        $requested = trim((string) env('BITRIX24_SYNC_QUEUE', 'bitrix24'));
        if ($requested === '') {
            $requested = 'bitrix24';
        }

        if (filter_var(env('BITRIX24_FORCE_DEDICATED_QUEUE', false), FILTER_VALIDATE_BOOL)) {
            return $requested;
        }

        $isLocal = in_array(env('APP_ENV', 'production'), ['local', 'development'], true);
        $queueDriver = $config
            ? $config->get('queue.default', 'database')
            : config('queue.default', 'database');

        if ($isLocal && $queueDriver === 'database') {
            return 'default';
        }

        return $requested;
    }

    private static function clampInt(mixed $value, int $min, int $max, int $default): int
    {
        $n = is_numeric($value) ? (int) $value : $default;

        return max($min, min($max, $n));
    }
}
