<?php

namespace App\Services\LeadScan;

use App\Models\Lead;
use App\Models\LeadIntelligenceCandidate;
use App\Models\LeadIntelligenceScan;
use App\Models\LeadIntelligenceSource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LeadScanRunner
{
    public function __construct(
        private readonly WebSearchProviderFactory $providers,
        private readonly LeadScanQueryBuilder $queries,
        private readonly LeadIdentityMatcher $matcher,
        private readonly LeadScanReportBuilder $reports,
        private readonly LeadScanAnalyzer $analyzer,
    ) {
    }

    public function run(int $scanId): void
    {
        $scan = LeadIntelligenceScan::query()->find($scanId);
        if (! $scan || $scan->status === 'cancelled') {
            $this->release($scan?->lead_id);

            return;
        }

        $lead = Lead::query()->find($scan->lead_id);
        if (! $lead) {
            $this->fail($scan, 'The lead is no longer available.');
            $this->release($scan->lead_id);

            return;
        }

        try {
            $scan->forceFill([
                'status' => 'processing',
                'progress' => 20,
                'started_at' => now(),
            ])->save();

            $snapshot = $this->queries->snapshot($lead);
            $provider = $this->providers->make();
            $scan->forceFill(['provider' => $provider->name(), 'progress' => 35])->save();

            if (! $provider->isConfigured()) {
                $this->finishUnconfigured($scan, $snapshot);

                return;
            }

            [$results, $errors] = $this->search($scan, $provider, $snapshot);
            if ($this->cancelled($scan->id)) {
                $this->release($scan->lead_id);

                return;
            }

            $candidates = $this->matcher->rank($snapshot, $results);
            $identity = $this->matcher->identity($candidates);
            $report = $this->reports->build(
                $snapshot,
                $candidates,
                $identity,
                $errors,
                $this->queries->manualSearchUrl($snapshot),
            );

            $scan->forceFill(['progress' => 80])->save();
            $ai = $this->analyzer->summarize($candidates);
            if ($ai['status'] !== 'not_configured') {
                $report = $this->reports->applyAiSummary($report, $ai, $candidates);
            }

            $status = 'completed';
            $message = $identity['message'];
            if ($candidates === [] && $errors !== []) {
                $status = 'failed';
                $message = 'The search provider did not return results.';
            } elseif ($errors !== []) {
                $status = 'partial';
                $message = 'Some searches failed. The report shows what was retrieved.';
            }

            DB::transaction(function () use ($scan, $candidates, $report, $status, $message) {
                $this->storeCandidates($scan, $candidates);
                $scan->forceFill([
                    'status' => $status,
                    'progress' => 100,
                    'status_message' => $message,
                    'report' => $report,
                    'finished_at' => now(),
                    'fresh_until' => $status === 'completed' ? now()->addHours((int) config('lead_scan.fresh_hours', 24)) : null,
                ])->save();
            });

            $this->prune($scan);
        } catch (\Throwable $e) {
            $this->fail($scan, 'The scan could not be completed. Try again.');
            report($e);
        } finally {
            $this->release($scan->lead_id);
        }
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function finishUnconfigured(LeadIntelligenceScan $scan, array $snapshot): void
    {
        $identity = ['status' => 'not_found', 'message' => 'Web search is not configured.'];
        $report = $this->reports->build($snapshot, [], $identity, ['unconfigured'], $this->queries->manualSearchUrl($snapshot));
        $report['sections']['findings'] = [[
            'label' => 'Configuration',
            'value' => 'Web search is not configured. Set LEAD_SCAN_PROVIDER=brave and LEAD_SCAN_API_KEY.',
            'quality' => 'not-found',
            'sources' => [],
        ]];
        $scan->forceFill([
            'status' => 'failed',
            'progress' => 100,
            'provider' => 'unconfigured',
            'status_message' => 'Web search is not configured. Set LEAD_SCAN_PROVIDER and LEAD_SCAN_API_KEY.',
            'report' => $report,
            'finished_at' => now(),
            'fresh_until' => null,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>}
     */
    private function search(LeadIntelligenceScan $scan, Contracts\WebSearchProvider $provider, array $snapshot): array
    {
        $built = $this->queries->queries($snapshot);
        $scan->forceFill([
            'queries_meta' => [
                'count' => count($built),
                'kinds' => array_column($built, 'kind'),
            ],
            'progress' => 50,
        ])->save();

        $results = [];
        $errors = [];
        foreach ($built as $query) {
            if ($this->cancelled($scan->id)) {
                break;
            }
            try {
                $results = array_merge($results, $provider->search($query['q']));
            } catch (LeadScanException $e) {
                $errors[] = $e->kind;
            }
        }

        return [$results, $errors];
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     */
    private function storeCandidates(LeadIntelligenceScan $scan, array $candidates): void
    {
        foreach ($candidates as $candidate) {
            $row = LeadIntelligenceCandidate::query()->create([
                'scan_id' => $scan->id,
                'title' => $candidate['title'] ?? '',
                'url' => $candidate['url'] ?? '',
                'tier' => $candidate['tier'] ?? 'weak',
                'reasons' => $candidate['reasons'] ?? [],
                'uncertainties' => $candidate['uncertainties'] ?? [],
                'excerpt' => $candidate['excerpt'] ?? '',
            ]);
            LeadIntelligenceSource::query()->create([
                'scan_id' => $scan->id,
                'candidate_id' => $row->id,
                'title' => $candidate['title'] ?? '',
                'url' => $candidate['url'] ?? '',
                'source_type' => $candidate['source_type'] ?? 'web',
                'excerpt' => $candidate['excerpt'] ?? '',
                'published_at' => $candidate['published_at'] ?? null,
                'retrieved_at' => now(),
                'origin' => 'extracted',
            ]);
        }
    }

    private function prune(LeadIntelligenceScan $scan): void
    {
        $days = (int) config('lead_scan.retain_days', 90);
        if ($days < 1) {
            return;
        }
        LeadIntelligenceScan::query()
            ->where('lead_id', $scan->lead_id)
            ->where('id', '!=', $scan->id)
            ->where('created_at', '<', now()->subDays($days))
            ->orderBy('id')
            ->limit(30)
            ->get()
            ->each
            ->delete();
    }

    private function cancelled(int $scanId): bool
    {
        return LeadIntelligenceScan::query()->whereKey($scanId)->where('status', 'cancelled')->exists();
    }

    private function fail(LeadIntelligenceScan $scan, string $message): void
    {
        if ($scan->status === 'cancelled') {
            return;
        }
        $scan->forceFill([
            'status' => 'failed',
            'progress' => 100,
            'status_message' => $message,
            'finished_at' => now(),
        ])->save();
    }

    private function release(mixed $leadId): void
    {
        if ($leadId) {
            Cache::forget($this->lockKey((int) $leadId));
        }
    }

    public static function lockKey(int $leadId): string
    {
        return 'lead-intelligence-scan:'.$leadId;
    }
}
