<?php

namespace App\Jobs\AiLeadIntelligence;

use App\Models\AiLeadIntelligence\AiLeadIntelligenceRun;
use App\Services\AiLeadIntelligence\AiLeadIntelligenceRunService;
use App\Services\AiLeadIntelligence\LeadIntelligenceAggregationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAiLeadIntelligenceBatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public int $runId)
    {
        $this->onQueue((string) config('ai_lead_intelligence.queue', 'default'));
    }

    public function handle(
        LeadIntelligenceAggregationService $aggregation,
        AiLeadIntelligenceRunService $runs
    ): void {
        $run = AiLeadIntelligenceRun::query()->find($this->runId);
        if (!$run) {
            return;
        }

        if (in_array($run->status, [
            AiLeadIntelligenceRun::STATUS_COMPLETED,
            AiLeadIntelligenceRun::STATUS_PARTIAL,
            AiLeadIntelligenceRun::STATUS_FAILED,
        ], true)) {
            return;
        }

        try {
            $runs->markRunning($run);
            $run->refresh();

            $batchSize = max(10, (int) $run->batch_size);
            $ids = $aggregation->nextLeadIds((int) $run->cursor_after_id, $batchSize);

            if ($ids === []) {
                $runs->finalize($run);

                return;
            }

            $result = $aggregation->processBatch($run, $ids);

            $run->processed = (int) $run->processed + (int) $result['processed'];
            $run->skipped_unchanged = (int) $run->skipped_unchanged + (int) $result['skipped'];
            $run->failed_leads = (int) $run->failed_leads + (int) $result['failed'];
            $run->cursor_after_id = max($ids);
            $run->save();

            self::dispatch($run->id)
                ->onQueue((string) config('ai_lead_intelligence.queue', 'default'));
        } catch (\Throwable $e) {
            Log::error('ali.batch_job_failed', [
                'run_id' => $this->runId,
                'error' => $e->getMessage(),
            ]);
            $runs->fail($run, $e->getMessage());
            throw $e;
        }
    }
}
