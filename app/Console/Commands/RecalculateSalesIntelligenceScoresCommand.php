<?php

namespace App\Console\Commands;

use App\Services\SalesIntelligence\SalesIntelligenceOrchestrator;
use Illuminate\Console\Command;

class RecalculateSalesIntelligenceScoresCommand extends Command
{
    protected $signature = 'sales-intelligence:recalculate-scores {--user= : Limit to a single user id}';

    protected $description = 'Recompute agent metrics and scores for the Sales Intelligence engine';

    public function handle(SalesIntelligenceOrchestrator $orchestrator): int
    {
        $user = $this->option('user');
        $ids = $user ? [(int) $user] : null;
        $count = $orchestrator->recalculateAll($ids);
        $this->info("Recalculated {$count} agent(s).");

        return self::SUCCESS;
    }
}
