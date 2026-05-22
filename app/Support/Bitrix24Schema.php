<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Guards Bitrix24 sync code when optional tables/columns are not migrated yet.
 */
class Bitrix24Schema
{
    public static function syncStateTableExists(): bool
    {
        return Schema::hasTable('bitrix_sync_states');
    }

    public static function shardsTableExists(): bool
    {
        return Schema::hasTable('bitrix_sync_shards');
    }

    public static function parallelShardsEnabled(): bool
    {
        return static::shardsTableExists()
            && (int) config('bitrix24.parallel_shards', 1) > 1;
    }
}
