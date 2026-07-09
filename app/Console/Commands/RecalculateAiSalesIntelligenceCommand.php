<?php

namespace App\Console\Commands;

use App\Jobs\AiSalesIntelligence\RecalculateAllAiSalesIntelligenceJob;
use App\Services\AiSalesIntelligence\AiOrchestrator;
use Illuminate\Console\Command;

class RecalculateAiSalesIntelligenceCommand extends Command
{
    protected $signature = 'ai-sales-intelligence:recalculate {--user=} {--sync : Run synchronously (required if queue driver is sync)}';

    protected $description = 'Recalculate AI Sales Intelligence metrics for all agents or a single user';

    public function handle(AiOrchestrator $orchestrator): int
    {
        $userId = $this->option('user');
        if ($userId) {
            $orchestrator->recalculateUser((int) $userId);
            $this->info("Recalculated AI intelligence for user {$userId}");

            return self::SUCCESS;
        }

        if ($this->option('sync') || config('queue.default') === 'sync') {
            $count = $orchestrator->recalculateAll();
            $this->info("Recalculated AI intelligence for {$count} agents");

            return self::SUCCESS;
        }

        RecalculateAllAiSalesIntelligenceJob::dispatch();
        $this->info('Queued full AI intelligence recalculation for all agents.');

        return self::SUCCESS;
    }
}
