<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\LeadScoringSetting;
use App\Services\LeadIntelligenceService;
use App\Services\LeadKanbanHeatCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ScoreLeadsCommand extends Command
{
    protected $signature = 'leads:score {--chunk=200} {--force : Rescore every lead, not only the ones that changed}';

    protected $description = 'Recalculate intelligence score for leads that changed since they were last scored';

    /** Lead columns LeadIntelligenceService reads. */
    private const SCORING_COLUMNS = [
        'leads.id', 'leads.lead_name', 'leads.lead_source', 'leads.comment', 'leads.raw_meta_data',
        'leads.budget', 'leads.whatsapp_number', 'leads.email', 'leads.stage_id',
        'leads.created_at', 'leads.updated_at',
    ];

    public function handle(LeadIntelligenceService $service): int
    {
        $requiredColumns = ['score', 'priority', 'intent', 'next_action', 'last_scored_at', 'score_breakdown'];
        $missingColumns = array_values(array_diff($requiredColumns, Schema::getColumnListing('leads')));

        if (!empty($missingColumns)) {
            $this->error('Lead intelligence columns are missing: ' . implode(', ', $missingColumns));
            $this->warn('Run php artisan migrate after fixing earlier migration errors, then run leads:score again.');
            return self::FAILURE;
        }

        $settings = LeadScoringSetting::resolved();
        $automation = $settings['automation_flags'] ?? [];
        if (($automation['scheduled_enabled'] ?? true) === false && !$this->option('force')) {
            $this->info('Lead scoring is disabled by automation settings. Use --force to run manually.');
            return self::SUCCESS;
        }

        $chunkSize = max(1, (int) $this->option('chunk'));
        $processed = 0;

        // Only the columns scoring reads (skips big JSON like bitrix24_data).
        $query = Lead::query()
            ->select(self::SCORING_COLUMNS)
            ->orderBy('id');
        if (!$this->option('force')) {
            $this->onlyLeadsNeedingRescore($query, $settings);
        }

        // Per chunk: one query for the leads, one for their latest comments; score them
        // all (this may call OpenAI, so no locks are held yet), then write every result
        // in one short transaction (one disk flush per chunk, not one per lead).
        $query->chunkById($chunkSize, function ($leads) use ($service, &$processed) {
            $service->primeLatestComments($leads->pluck('id')->map(fn ($id) => (int) $id)->all());

            $updates = [];
            foreach ($leads as $lead) {
                $result = $service->generateRecommendation($lead);

                // Plain UPDATE: no model events and no updated_at bump, so scoring
                // doesn't rewrite every lead's updated_at (and its indexes).
                $updates[(int) $lead->id] = [
                    'score' => $result['score'],
                    'priority' => $result['priority'],
                    'intent' => $result['intent'],
                    'next_action' => $result['next_action'],
                    'last_scored_at' => now(),
                    'score_breakdown' => isset($result['score_breakdown']) ? json_encode($result['score_breakdown']) : null,
                ];

                if (config('lead_intelligence.debug', false)) {
                    Log::info('lead_intelligence.scored_command', [
                        'lead_id' => $lead->id,
                        'score' => $result['score'] ?? null,
                        'priority' => $result['priority'] ?? null,
                        'intent' => $result['intent'] ?? null,
                        'reason' => $result['reason'] ?? null,
                        'next_action' => $result['next_action'] ?? null,
                        'risk' => $result['risk'] ?? null,
                        'score_breakdown' => $result['score_breakdown'] ?? null,
                    ]);
                }

                $processed++;
            }

            DB::transaction(function () use ($updates) {
                foreach ($updates as $id => $values) {
                    DB::table('leads')->where('id', $id)->update($values);
                }
            });

            $this->info("Processed: {$processed}");
        }, 'leads.id', 'id');

        $service->forgetLatestComments();

        if ($processed > 0) {
            LeadKanbanHeatCache::bump();
        }

        $this->info("Lead scoring completed. Total processed: {$processed}");

        return self::SUCCESS;
    }

    /**
     * A score only depends on the lead's own fields, its latest comment and its age
     * (the "recent lead" bonus). So only rescore leads that:
     *  - were never scored,
     *  - changed after they were scored,
     *  - got a new comment after they were scored, or
     *  - crossed the recency window since they were scored (bonus must drop).
     */
    private function onlyLeadsNeedingRescore($query, array $settings): void
    {
        $recencyHours = (int) (data_get($settings, 'rules.recency_hours') ?? config('lead_scoring.rules.recency_hours', 48));
        $recencyCutoff = now()->subHours($recencyHours);

        $query->where(function ($q) use ($recencyHours, $recencyCutoff) {
            $q->whereNull('leads.last_scored_at')
                ->orWhereColumn('leads.updated_at', '>', 'leads.last_scored_at')
                ->orWhereExists(function ($c) {
                    $c->selectRaw('1')
                        ->from('lead_comments')
                        ->whereColumn('lead_comments.lead_id', 'leads.id')
                        ->whereColumn('lead_comments.created_at', '>', 'leads.last_scored_at');
                })
                ->orWhere(function ($r) use ($recencyHours, $recencyCutoff) {
                    $r->where('leads.created_at', '<=', $recencyCutoff)
                        ->whereRaw('leads.last_scored_at < DATE_ADD(leads.created_at, INTERVAL ? HOUR)', [$recencyHours]);
                });
        });
    }
}
