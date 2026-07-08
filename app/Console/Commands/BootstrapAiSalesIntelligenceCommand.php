<?php

namespace App\Console\Commands;

use App\Jobs\AiSalesIntelligence\RecalculateAllAiSalesIntelligenceJob;
use App\Models\AiSalesIntelligence\AiSalesIntelligenceSetting;
use App\Services\AiSalesIntelligence\AiOrchestrator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class BootstrapAiSalesIntelligenceCommand extends Command
{
    protected $signature = 'ai-sales-intelligence:bootstrap {--sync : Run recalculation synchronously instead of queueing}';

    protected $description = 'Initialize AI Sales Intelligence settings and scan all agents/leads for scoring';

    public function handle(AiOrchestrator $orchestrator): int
    {
        if (!Schema::hasTable('ai_agent_metrics')) {
            $this->error('AI Sales Intelligence tables are missing. Run: php artisan migrate --force');

            return self::FAILURE;
        }

        AiSalesIntelligenceSetting::current();
        $this->info('Settings initialized.');

        if ($this->option('sync')) {
            $count = $orchestrator->recalculateAll();
            $this->info("Synchronously recalculated {$count} agents.");

            return self::SUCCESS;
        }

        RecalculateAllAiSalesIntelligenceJob::dispatch();
        $this->info('Full agent + lead scan queued. Ensure queue worker is running, or use --sync on small environments.');

        return self::SUCCESS;
    }
}
