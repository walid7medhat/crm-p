<?php

namespace App\Jobs\AiSalesIntelligence;

use App\Services\AiSalesIntelligence\AiAgentUserResolver;
use App\Services\AiSalesIntelligence\AiOrchestrator;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class RecalculateAllAiSalesIntelligenceJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $uniqueFor = 3600;

    public int $timeout = 7200;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return 'ai-sales-intelligence-recalculate-all';
    }

    public function handle(AiAgentUserResolver $resolver, AiOrchestrator $orchestrator): void
    {
        $ids = $resolver->scoredUserIds();

        Cache::put('ai_sales_intelligence:bootstrap_status', [
            'status' => 'running',
            'total' => count($ids),
            'completed' => 0,
            'started_at' => now()->toIso8601String(),
        ], 7200);

        if ($ids === []) {
            $orchestrator->finalizeRankings();
            $this->markReady(0);

            return;
        }

        if (config('queue.default') === 'sync') {
            foreach ($ids as $index => $userId) {
                $orchestrator->recalculateUser((int) $userId);
                Cache::put('ai_sales_intelligence:bootstrap_status', [
                    'status' => 'running',
                    'total' => count($ids),
                    'completed' => $index + 1,
                    'started_at' => Cache::get('ai_sales_intelligence:bootstrap_status')['started_at'] ?? now()->toIso8601String(),
                ], 7200);
            }
            $orchestrator->finalizeRankings();
            $this->markReady(count($ids));

            return;
        }

        $jobs = collect($ids)
            ->map(fn (int $userId) => new RecalculateAiAgentIntelligenceJob($userId))
            ->all();

        if (Schema::hasTable('job_batches')) {
            Bus::batch($jobs)
                ->name('AI Sales Intelligence full recalculation')
                ->then(function () use ($ids) {
                    FinalizeAiSalesIntelligenceRankingsJob::dispatch(count($ids));
                })
                ->catch(function (Batch $batch, Throwable $e) {
                    Log::error('AI Sales Intelligence batch failed', [
                        'batch_id' => $batch->id,
                        'message' => $e->getMessage(),
                    ]);
                    Cache::put('ai_sales_intelligence:bootstrap_status', [
                        'status' => 'failed',
                        'message' => $e->getMessage(),
                        'failed_at' => now()->toIso8601String(),
                    ], 3600);
                })
                ->dispatch();

            return;
        }

        foreach ($jobs as $job) {
            dispatch($job);
        }

        $delayMinutes = max(2, (int) ceil(count($ids) / 8));
        FinalizeAiSalesIntelligenceRankingsJob::dispatch(count($ids))
            ->delay(now()->addMinutes($delayMinutes));
    }

    protected function markReady(int $count): void
    {
        Cache::put('ai_sales_intelligence:bootstrap_status', [
            'status' => 'ready',
            'total' => $count,
            'completed' => $count,
            'finished_at' => now()->toIso8601String(),
        ], 3600);
        Cache::forget('ai_sales_intelligence:bootstrap_queued');
    }
}
