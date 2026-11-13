<?php

namespace App\Jobs\AiSalesIntelligence;

use App\Services\AiSalesIntelligence\AiOrchestrator;
use App\Support\Brand\Concerns\BrandAwareJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecalculateAiAgentIntelligenceJob implements ShouldQueue, ShouldBeUnique
{
    use BrandAwareJob;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $uniqueFor = 120;

    public function __construct(public int $userId)
    {
        $this->initializeBrandContext();
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return 'ai-sales-intel-agent-'.$this->brandKey.'-'.$this->userId;
    }

    public function handle(AiOrchestrator $orchestrator): void
    {
        $this->runWithBrandContext(function () use ($orchestrator) {
            try {
                $orchestrator->recalculateUser($this->userId);
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }
}
