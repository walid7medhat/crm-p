<?php

namespace App\Jobs;

use App\Services\SalesIntelligence\SalesIntelligenceOrchestrator;
use App\Support\Brand\Concerns\BrandAwareJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecalculateSalesAgentScoreJob implements ShouldQueue, ShouldBeUnique
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
        return 'sales-intel-agent-'.$this->brandKey.'-'.$this->userId;
    }

    public function handle(SalesIntelligenceOrchestrator $orchestrator): void
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
