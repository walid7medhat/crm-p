<?php

namespace App\Console\Commands;

use App\Support\QueueConnectionResolver;
use App\Support\RedisSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Bitrix24DoctorCommand extends Command
{
    protected $signature = 'bitrix24:doctor';

    protected $description = 'Check queue, Redis, database, and Bitrix24 configuration';

    public function handle(): int
    {
        $this->info('Bitrix24 / infrastructure health check');
        $this->newLine();

        $preferred = env('QUEUE_CONNECTION', 'database');
        $resolved = QueueConnectionResolver::resolve($preferred);
        $this->line("Queue (requested): <fg=cyan>{$preferred}</>");
        $this->line("Queue (resolved):  <fg=green>{$resolved}</>");

        if ($resolved !== $preferred) {
            $this->warn('  → Auto-fallback active (Redis unavailable locally is OK).');
        }

        $redisClient = RedisSupport::preferredClient() ?? 'none';
        $redisUp = RedisSupport::isAvailable();
        $this->line("Redis client:        <fg=cyan>{$redisClient}</>");
        $this->line('Redis reachable:     ' . ($redisUp ? '<fg=green>yes</>' : '<fg=yellow>no (using database queue)</>'));

        $webhook = config('bitrix24.webhook_url');
        $this->line('Bitrix24 webhook:    ' . ($webhook ? '<fg=green>configured</>' : '<fg=red>MISSING</>'));

        $this->line('Pages per job:       ' . config('bitrix24.pages_per_job'));
        $this->line('Parallel shards:     ' . config('bitrix24.parallel_shards'));
        $this->line('Sync queue name:     ' . config('bitrix24.queue'));

        try {
            DB::connection()->getPdo();
            $this->line('Database:            <fg=green>connected</>');
        } catch (\Throwable $e) {
            $this->error('Database:            FAILED — ' . $e->getMessage());
            return self::FAILURE;
        }

        foreach (['jobs', 'failed_jobs', 'job_batches', 'bitrix_sync_states'] as $table) {
            if (!Schema::hasTable($table)) {
                $this->warn("Table missing: {$table} — run php artisan migrate");
            }
        }

        $this->newLine();
        $this->info('Local:  QUEUE_CONNECTION=database, php artisan queue:work database --queue=bitrix24');
        $this->info('Prod:   QUEUE_CONNECTION=redis + Redis server + multiple workers');

        return self::SUCCESS;
    }
}
