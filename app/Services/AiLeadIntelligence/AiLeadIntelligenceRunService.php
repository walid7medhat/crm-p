<?php

namespace App\Services\AiLeadIntelligence;

use App\Jobs\AiLeadIntelligence\ProcessAiLeadIntelligenceBatchJob;
use App\Models\AiLeadIntelligence\AiLeadIntelligenceRun;
use Illuminate\Support\Facades\Log;

class AiLeadIntelligenceRunService
{
    public function __construct(
        protected LeadIntelligenceAggregationService $aggregation
    ) {}

    public function latestUsableRun(): ?AiLeadIntelligenceRun
    {
        return AiLeadIntelligenceRun::query()
            ->whereIn('status', [
                AiLeadIntelligenceRun::STATUS_COMPLETED,
                AiLeadIntelligenceRun::STATUS_PARTIAL,
                AiLeadIntelligenceRun::STATUS_RUNNING,
                AiLeadIntelligenceRun::STATUS_QUEUED,
            ])
            ->orderByDesc('id')
            ->first();
    }

    public function latestFinishedRun(): ?AiLeadIntelligenceRun
    {
        return AiLeadIntelligenceRun::query()
            ->whereIn('status', [
                AiLeadIntelligenceRun::STATUS_COMPLETED,
                AiLeadIntelligenceRun::STATUS_PARTIAL,
            ])
            ->whereNotNull('dashboard_payload')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function overviewPayload(): array
    {
        $inProgress = AiLeadIntelligenceRun::query()
            ->whereIn('status', [
                AiLeadIntelligenceRun::STATUS_QUEUED,
                AiLeadIntelligenceRun::STATUS_RUNNING,
            ])
            ->orderByDesc('id')
            ->first();

        $finished = $this->latestFinishedRun();

        if ($inProgress) {
            if ($finished && is_array($finished->dashboard_payload)) {
                $payload = $finished->dashboard_payload;
                $payload['analysis_status'] = $inProgress->status === AiLeadIntelligenceRun::STATUS_QUEUED
                    ? AiLeadIntelligenceContract::STATUS_QUEUED
                    : AiLeadIntelligenceContract::STATUS_RUNNING;
                $payload['run_status'] = $inProgress->status;
                $payload['message'] = $inProgress->status === AiLeadIntelligenceRun::STATUS_QUEUED
                    ? 'CRM intelligence refresh is queued.'
                    : sprintf(
                        'Analyzing CRM leads… %d / %d processed.',
                        (int) $inProgress->processed,
                        (int) $inProgress->total_eligible
                    );
                $payload['progress'] = [
                    'processed' => (int) $inProgress->processed,
                    'total_eligible' => (int) $inProgress->total_eligible,
                    'skipped_unchanged' => (int) $inProgress->skipped_unchanged,
                    'failed_leads' => (int) $inProgress->failed_leads,
                ];

                return $payload;
            }

            return [
                'phase' => AiLeadIntelligenceContract::PHASE,
                'analysis_status' => $inProgress->status === AiLeadIntelligenceRun::STATUS_QUEUED
                    ? AiLeadIntelligenceContract::STATUS_QUEUED
                    : AiLeadIntelligenceContract::STATUS_RUNNING,
                'run_status' => $inProgress->status,
                'last_updated_at' => null,
                'message' => $inProgress->status === AiLeadIntelligenceRun::STATUS_QUEUED
                    ? 'CRM intelligence refresh is queued.'
                    : sprintf(
                        'Analyzing CRM leads… %d / %d processed.',
                        (int) $inProgress->processed,
                        (int) $inProgress->total_eligible
                    ),
                'progress' => [
                    'processed' => (int) $inProgress->processed,
                    'total_eligible' => (int) $inProgress->total_eligible,
                    'skipped_unchanged' => (int) $inProgress->skipped_unchanged,
                    'failed_leads' => (int) $inProgress->failed_leads,
                ],
                'brief' => AiLeadIntelligenceContract::briefSection(),
                'priority_leads' => AiLeadIntelligenceContract::listSection(),
                'at_risk' => AiLeadIntelligenceContract::listSection(),
                'property_opportunities' => AiLeadIntelligenceContract::propertyOpportunitiesSection(),
                'neglected' => AiLeadIntelligenceContract::neglectedSection(),
                'actions_today' => AiLeadIntelligenceContract::listSection(),
                'insight_preview' => [
                    'status' => AiLeadIntelligenceContract::STATUS_PREVIEW,
                    'text' => null,
                    'disclaimer' => 'Aggregation in progress.',
                ],
            ];
        }

        if ($finished && is_array($finished->dashboard_payload)) {
            return $finished->dashboard_payload;
        }

        $failed = AiLeadIntelligenceRun::query()
            ->where('status', AiLeadIntelligenceRun::STATUS_FAILED)
            ->orderByDesc('id')
            ->first();

        if ($failed) {
            $payload = AiLeadIntelligenceContract::emptyNotStarted(
                'Last CRM intelligence run failed. You can retry Refresh Analysis.'
            );
            $payload['analysis_status'] = AiLeadIntelligenceContract::STATUS_FAILED;
            $payload['run_status'] = AiLeadIntelligenceRun::STATUS_FAILED;
            $payload['message'] = $failed->error_message
                ? 'Analysis failed. Please retry.'
                : 'Last CRM intelligence run failed. Please retry.';

            return $payload;
        }

        return AiLeadIntelligenceContract::emptyNotStarted();
    }

    /**
     * @return array{run:AiLeadIntelligenceRun,queued:bool,reused:bool}
     */
    public function queueRefresh(?int $userId): array
    {
        if (!config('ai_lead_intelligence.enabled', true)) {
            throw new \RuntimeException('AI Lead Intelligence is disabled.');
        }

        $existing = AiLeadIntelligenceRun::query()
            ->whereIn('status', [
                AiLeadIntelligenceRun::STATUS_QUEUED,
                AiLeadIntelligenceRun::STATUS_RUNNING,
            ])
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            return ['run' => $existing, 'queued' => false, 'reused' => true];
        }

        $batchSize = max(10, (int) config('ai_lead_intelligence.batch_size', 40));
        $total = $this->aggregation->countEligible();

        $run = AiLeadIntelligenceRun::query()->create([
            'status' => AiLeadIntelligenceRun::STATUS_QUEUED,
            'triggered_by' => $userId,
            'total_eligible' => $total,
            'processed' => 0,
            'skipped_unchanged' => 0,
            'failed_leads' => 0,
            'batch_size' => $batchSize,
            'cursor_after_id' => 0,
            'started_at' => null,
            'finished_at' => null,
        ]);

        ProcessAiLeadIntelligenceBatchJob::dispatch($run->id)
            ->onQueue((string) config('ai_lead_intelligence.queue', 'default'));

        Log::info('ali.refresh_queued', [
            'run_id' => $run->id,
            'total_eligible' => $total,
            'triggered_by' => $userId,
        ]);

        return ['run' => $run, 'queued' => true, 'reused' => false];
    }

    public function markRunning(AiLeadIntelligenceRun $run): void
    {
        if ($run->status === AiLeadIntelligenceRun::STATUS_QUEUED) {
            $run->forceFill([
                'status' => AiLeadIntelligenceRun::STATUS_RUNNING,
                'started_at' => $run->started_at ?? now(),
            ])->save();
        }
    }

    public function finalize(AiLeadIntelligenceRun $run): void
    {
        $status = ((int) $run->failed_leads > 0 && (int) $run->processed > 0)
            ? AiLeadIntelligenceRun::STATUS_PARTIAL
            : (((int) $run->processed === 0 && (int) $run->total_eligible > 0)
                ? AiLeadIntelligenceRun::STATUS_FAILED
                : AiLeadIntelligenceRun::STATUS_COMPLETED);

        if ((int) $run->total_eligible === 0) {
            $status = AiLeadIntelligenceRun::STATUS_COMPLETED;
        }

        $run->status = $status;
        $payload = $this->aggregation->buildDashboardPayload($run);
        $run->overview = $payload['overview_counts'] ?? null;
        $run->dashboard_payload = $payload;
        $run->finished_at = now();
        if ($status === AiLeadIntelligenceRun::STATUS_FAILED && !$run->error_message) {
            $run->error_message = 'No leads were processed successfully.';
        }
        $run->save();
    }

    public function fail(AiLeadIntelligenceRun $run, string $message): void
    {
        $run->forceFill([
            'status' => AiLeadIntelligenceRun::STATUS_FAILED,
            'error_message' => mb_substr($message, 0, 1000),
            'finished_at' => now(),
        ])->save();
    }
}
