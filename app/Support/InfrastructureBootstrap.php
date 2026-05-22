<?php

namespace App\Support;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Log;

class InfrastructureBootstrap
{
    public static function boot(Application $app): void
    {
        $config = $app->make('config');

        $preferredQueue = env('QUEUE_CONNECTION', 'database');
        $resolvedQueue = QueueConnectionResolver::resolve($preferredQueue);
        $config->set('queue.default', $resolvedQueue);

        if ($resolvedQueue !== $preferredQueue) {
            Log::info('Queue driver auto-fallback active', [
                'requested' => $preferredQueue,
                'resolved'  => $resolvedQueue,
            ]);
        }

        $redisClient = RedisSupport::preferredClient();
        if ($redisClient !== null) {
            $config->set('database.redis.client', $redisClient);
        }

        Bitrix24Config::applyDefaults($app);
    }
}
