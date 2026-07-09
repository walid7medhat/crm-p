<?php

namespace App\Jobs\AiSalesIntelligence;

use App\Services\AiSalesIntelligence\AiOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class FinalizeAiSalesIntelligenceRankingsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $uniqueFor = 300;

    public function __construct(public int $expectedAgents = 0)
    {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return 'ai-sales-intelligence-finalize-rankings';
    }

    public function handle(AiOrchestrator $orchestrator): void
    {
        $orchestrator->finalizeRankings();

        Cache::put('ai_sales_intelligence:bootstrap_status', [
            'status' => 'ready',
            'total' => $this->expectedAgents,
            'completed' => $this->expectedAgents,
            'finished_at' => now()->toIso8601String(),
        ], 3600);
        Cache::forget('ai_sales_intelligence:bootstrap_queued');
    }
}
