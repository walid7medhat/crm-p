<?php

namespace App\Services\AiLeadIntelligence;

use App\Models\AiLeadIntelligence\AiLeadIntelligenceLeadContext;
use App\Models\AiLeadIntelligence\AiLeadIntelligenceRun;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadComment;
use App\Models\LeadHistory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Phase 3 — deterministic CRM aggregation for AI Lead Intelligence.
 * No LLM calls.
 */
class LeadIntelligenceAggregationService
{
    public function __construct(
        protected MatchingListingsResolver $matching
    ) {}

    public function eligibleLeadsQuery(): Builder
    {
        return Lead::query()
            ->where(function (Builder $q) {
                $q->whereNull('converted_at')
                    ->where(function (Builder $inner) {
                        $inner->whereNull('status_lead')
                            ->orWhere('status_lead', '!=', 'converted');
                    });
            });
    }

    public function countEligible(): int
    {
        return (int) $this->eligibleLeadsQuery()->count();
    }

    /**
     * @return list<int>
     */
    public function nextLeadIds(int $afterId, int $limit): array
    {
        return $this->eligibleLeadsQuery()
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Process a batch of lead IDs for a run.
     *
     * @param  list<int>  $leadIds
     * @return array{processed:int,skipped:int,failed:int}
     */
    public function processBatch(AiLeadIntelligenceRun $run, array $leadIds): array
    {
        $processed = 0;
        $skipped = 0;
        $failed = 0;

        if ($leadIds === []) {
            return compact('processed', 'skipped', 'failed');
        }

        $leads = Lead::query()
            ->with([
                'stage:id,name,order',
                'responsiblePerson:id,name',
                'area:id,name',
                'propertyType:id,name',
                'integration:id,project_id',
            ])
            ->whereIn('id', $leadIds)
            ->get()
            ->keyBy('id');

        $meta = $this->batchMeta($leadIds);
        $existing = AiLeadIntelligenceLeadContext::query()
            ->whereIn('lead_id', $leadIds)
            ->get()
            ->keyBy('lead_id');

        $matchEnabled = (bool) config('ai_lead_intelligence.property_matches.enabled', true);

        foreach ($leadIds as $leadId) {
            try {
                /** @var Lead|null $lead */
                $lead = $leads->get($leadId);
                if (!$lead) {
                    continue;
                }

                $fingerprint = $this->fingerprint($lead, $meta[$leadId] ?? []);
                /** @var AiLeadIntelligenceLeadContext|null $row */
                $row = $existing->get($leadId);

                if ($row && $row->fingerprint === $fingerprint && is_array($row->dashboard_card)) {
                    $row->forceFill([
                        'last_run_id' => $run->id,
                        'analyzed_at' => now(),
                    ])->save();
                    $skipped++;
                    $processed++;
                    continue;
                }

                $built = $this->buildLeadIntelligence($lead, $meta[$leadId] ?? [], $matchEnabled);

                AiLeadIntelligenceLeadContext::query()->updateOrCreate(
                    ['lead_id' => $leadId],
                    [
                        'fingerprint' => $fingerprint,
                        'urgency_bucket' => $built['urgency_bucket'],
                        'is_high_priority' => $built['is_high_priority'],
                        'is_at_risk' => $built['is_at_risk'],
                        'is_neglected' => $built['is_neglected'],
                        'requires_action_today' => $built['requires_action_today'],
                        'matching_listings_count' => $built['matching_listings_count'],
                        'dashboard_card' => $built['dashboard_card'],
                        'context' => $built['context'],
                        'last_run_id' => $run->id,
                        'analyzed_at' => now(),
                    ]
                );

                $processed++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('ali.lead_aggregation_failed', [
                    'lead_id' => $leadId,
                    'run_id' => $run->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return compact('processed', 'skipped', 'failed');
    }

    /**
     * Build dashboard overview payload from persisted lead contexts.
     * Uses aggregate COUNT queries + capped card fetches (no full-table hydrate).
     *
     * @return array<string, mixed>
     */
    public function buildDashboardPayload(AiLeadIntelligenceRun $run): array
    {
        $limits = config('ai_lead_intelligence.dashboard', []);
        $priorityLimit = (int) ($limits['priority_limit'] ?? 25);
        $atRiskLimit = (int) ($limits['at_risk_limit'] ?? 25);
        $propLimit = (int) ($limits['property_opportunities_limit'] ?? 25);
        $neglectLimit = (int) ($limits['neglect_group_limit'] ?? 15);
        $todayLimit = (int) ($limits['today_actions_limit'] ?? 20);

        $base = AiLeadIntelligenceLeadContext::query()->whereNotNull('dashboard_card');
        $analyzed = (int) (clone $base)->count();
        $eligible = (int) $run->total_eligible;

        $highIntentValues = array_map('strtolower', config('ai_lead_intelligence.high_intent_values', ['high']));

        $overviewCounts = [
            'total_eligible' => $eligible,
            'analyzed_leads' => $analyzed,
            'high_priority' => (int) (clone $base)->where('is_high_priority', true)->count(),
            'at_risk' => (int) (clone $base)->where('is_at_risk', true)->count(),
            'neglected' => (int) (clone $base)->where('is_neglected', true)->count(),
            'critical' => (int) (clone $base)->where('urgency_bucket', 'critical')->count(),
            'needs_attention' => (int) (clone $base)->where('urgency_bucket', 'needs_attention')->count(),
            'monitor' => (int) (clone $base)->where('urgency_bucket', 'monitor')->count(),
            'property_opportunities' => (int) (clone $base)->where('matching_listings_count', '>', 0)->count(),
            'actions_today' => (int) (clone $base)->where('requires_action_today', true)->count(),
            'high_intent' => $highIntentValues === []
                ? 0
                : (int) (clone $base)->where(function ($q) use ($highIntentValues) {
                    foreach ($highIntentValues as $i => $val) {
                        $method = $i === 0 ? 'where' : 'orWhere';
                        $q->{$method}('dashboard_card->intent', $val);
                    }
                })->count(),
        ];

        $summary = sprintf(
            'CRM Intelligence Brief: %d eligible leads analyzed. %d high priority, %d at risk, %d neglected (%d critical), %d with property matches, %d actions for today.',
            $analyzed,
            $overviewCounts['high_priority'],
            $overviewCounts['at_risk'],
            $overviewCounts['neglected'],
            $overviewCounts['critical'],
            $overviewCounts['property_opportunities'],
            $overviewCounts['actions_today']
        );

        $sectionStatus = $analyzed > 0 ? AiLeadIntelligenceContract::STATUS_READY : AiLeadIntelligenceContract::STATUS_EMPTY;
        $analysisStatus = $run->status === AiLeadIntelligenceRun::STATUS_PARTIAL
            ? AiLeadIntelligenceContract::STATUS_PARTIAL
            : ($analyzed > 0 ? AiLeadIntelligenceContract::STATUS_READY : AiLeadIntelligenceContract::STATUS_EMPTY);

        $highPriorityItems = $this->fetchCards((clone $base)->where('is_high_priority', true)->orderByDesc('id'), $priorityLimit);
        $atRiskItems = $this->fetchCards((clone $base)->where('is_at_risk', true)->orderByDesc('id'), $atRiskLimit);
        $propItems = $this->fetchCards((clone $base)->where('matching_listings_count', '>', 0)->orderByDesc('matching_listings_count'), $propLimit);
        $criticalItems = $this->fetchCards((clone $base)->where('urgency_bucket', 'critical')->orderByDesc('id'), $neglectLimit);
        $needsItems = $this->fetchCards((clone $base)->where('urgency_bucket', 'needs_attention')->orderByDesc('id'), $neglectLimit);
        $monitorItems = $this->fetchCards((clone $base)->where('urgency_bucket', 'monitor')->orderByDesc('id'), $neglectLimit);
        $todayCards = $this->fetchCards((clone $base)->where('requires_action_today', true)->orderByDesc('is_high_priority')->orderByDesc('id'), $todayLimit);
        $todayActions = $this->buildTodayActions(collect($todayCards));

        return [
            'phase' => 'phase_3',
            'analysis_status' => $analysisStatus,
            'run_status' => $run->status,
            'last_updated_at' => optional($run->finished_at ?? $run->updated_at)->toIso8601String(),
            'message' => $run->status === AiLeadIntelligenceRun::STATUS_PARTIAL
                ? 'Partial CRM aggregation completed; some leads failed to analyze.'
                : 'Deterministic CRM intelligence aggregation complete (Phase 3 — no LLM).',
            'brief' => [
                'summary' => $summary,
                'summary_status' => AiLeadIntelligenceContract::STATUS_READY,
                'cards' => [
                    AiLeadIntelligenceContract::metricCardReady('high_priority', 'High Priority', 'priority', $overviewCounts['high_priority']),
                    AiLeadIntelligenceContract::metricCardReady('at_risk', 'At Risk', 'risk', $overviewCounts['at_risk']),
                    AiLeadIntelligenceContract::metricCardReady('property_opportunities', 'Property Opportunities', 'property', $overviewCounts['property_opportunities']),
                    AiLeadIntelligenceContract::metricCardReady('neglected', 'Neglected', 'neglected', $overviewCounts['neglected']),
                ],
                'extra' => [
                    'high_intent' => $overviewCounts['high_intent'],
                    'critical' => $overviewCounts['critical'],
                    'needs_attention' => $overviewCounts['needs_attention'],
                    'monitor' => $overviewCounts['monitor'],
                    'actions_today' => $overviewCounts['actions_today'],
                    'analyzed_leads' => $analyzed,
                    'total_eligible' => $eligible,
                ],
            ],
            'priority_leads' => [
                'status' => $overviewCounts['high_priority'] === 0 ? AiLeadIntelligenceContract::STATUS_EMPTY : $sectionStatus,
                'items' => $highPriorityItems,
                'meta' => ['total' => $overviewCounts['high_priority']],
            ],
            'at_risk' => [
                'status' => $overviewCounts['at_risk'] === 0 ? AiLeadIntelligenceContract::STATUS_EMPTY : $sectionStatus,
                'items' => $atRiskItems,
                'meta' => ['total' => $overviewCounts['at_risk']],
            ],
            'property_opportunities' => [
                'status' => $overviewCounts['property_opportunities'] === 0 ? AiLeadIntelligenceContract::STATUS_EMPTY : $sectionStatus,
                'items' => $propItems,
                'gaps' => [],
                'meta' => ['total' => $overviewCounts['property_opportunities'], 'gap_total' => 0],
            ],
            'neglected' => [
                'status' => $overviewCounts['neglected'] === 0 ? AiLeadIntelligenceContract::STATUS_EMPTY : $sectionStatus,
                'groups' => [
                    'critical' => [
                        'status' => $overviewCounts['critical'] === 0 ? AiLeadIntelligenceContract::STATUS_EMPTY : AiLeadIntelligenceContract::STATUS_READY,
                        'count' => $overviewCounts['critical'],
                        'items' => $criticalItems,
                    ],
                    'needs_attention' => [
                        'status' => $overviewCounts['needs_attention'] === 0 ? AiLeadIntelligenceContract::STATUS_EMPTY : AiLeadIntelligenceContract::STATUS_READY,
                        'count' => $overviewCounts['needs_attention'],
                        'items' => $needsItems,
                    ],
                    'monitor' => [
                        'status' => $overviewCounts['monitor'] === 0 ? AiLeadIntelligenceContract::STATUS_EMPTY : AiLeadIntelligenceContract::STATUS_READY,
                        'count' => $overviewCounts['monitor'],
                        'items' => $monitorItems,
                    ],
                ],
            ],
            'actions_today' => [
                'status' => $todayActions === [] ? AiLeadIntelligenceContract::STATUS_EMPTY : $sectionStatus,
                'items' => $todayActions,
                'meta' => ['total' => $overviewCounts['actions_today']],
            ],
            'insight_preview' => [
                'status' => AiLeadIntelligenceContract::STATUS_PREVIEW,
                'text' => $summary,
                'disclaimer' => 'Phase 3 CRM Intelligence Brief — deterministic metrics only. Phase 4 will add structured AI insights.',
            ],
            'overview_counts' => $overviewCounts,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function fetchCards($query, int $limit): array
    {
        return $query->limit($limit)->get(['dashboard_card'])
            ->map(fn (AiLeadIntelligenceLeadContext $c) => $c->dashboard_card)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string,mixed>>  $cards
     * @return list<array<string,mixed>>
     */
    protected function buildTodayActions(Collection $cards): array
    {
        $actions = [];
        $rank = 1;

        foreach ($cards as $card) {
            $label = $card['recommended_action']
                ?? $card['next_action']
                ?? 'Follow up with this lead';
            $reason = $card['why_text']
                ?? $card['risk_reason']
                ?? 'Deterministic CRM signal requires attention today.';

            $actions[] = [
                'id' => 'lead-'.$card['id'].'-'.$rank,
                'rank' => $rank,
                'label' => $label,
                'reason' => $reason,
                'lead_id' => $card['id'],
                'lead_name' => $card['lead_name'] ?? 'Unnamed lead',
            ];
            $rank++;
        }

        return $actions;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function buildLeadIntelligence(Lead $lead, array $meta, bool $matchEnabled): array
    {
        $comments = $this->recentComments((int) $lead->id, $meta);
        $activities = $this->recentActivities((int) $lead->id, $meta);
        $histories = $this->recentHistories((int) $lead->id, $meta);

        $lastMeaningful = $this->resolveLastMeaningfulAt($lead, $meta);
        $daysInactive = $lastMeaningful
            ? (int) $lastMeaningful->diffInDays(now())
            : (int) Carbon::parse($lead->created_at)->diffInDays(now());

        $urgency = $this->urgencyBucket($daysInactive);
        $isNeglected = $urgency !== null;

        $priority = strtolower((string) ($lead->priority ?? ''));
        $score = $lead->score !== null ? (float) $lead->score : null;
        $scoreMin = (float) config('ai_lead_intelligence.priority_score_min', 80);
        $isHighPriority = $priority === 'hot' || ($score !== null && $score >= $scoreMin);

        $overdueFollowups = (int) ($meta['overdue_activities'] ?? 0);
        $intent = strtolower((string) ($lead->intent ?? ''));
        $highIntentValues = array_map('strtolower', config('ai_lead_intelligence.high_intent_values', ['high']));
        $isHighIntent = in_array($intent, $highIntentValues, true);

        $isAtRisk = $urgency === 'critical'
            || $urgency === 'needs_attention'
            || $overdueFollowups > 0
            || ($isHighPriority && $daysInactive >= (int) config('ai_lead_intelligence.inactivity.needs_attention_days', 7));

        $requiresActionToday = $isHighPriority
            || $urgency === 'critical'
            || $overdueFollowups > 0
            || ($isHighIntent && $daysInactive >= (int) config('ai_lead_intelligence.inactivity.monitor_days', 3));

        $matches = ['count' => 0, 'listings' => [], 'has_criteria' => false];
        if ($matchEnabled) {
            $matches = $this->matching->forLead($lead);
        }

        $budgetDisplay = $this->formatBudget($lead);
        $lastContactLabel = $lastMeaningful
            ? $lastMeaningful->diffForHumans()
            : 'No activity';

        $why = [];
        if ($isHighPriority) {
            $why[] = $priority === 'hot'
                ? 'Existing lead priority is hot'
                : 'Existing lead score is '.$score;
        }
        if ($isNeglected) {
            $why[] = "No meaningful activity for {$daysInactive} day(s) ({$urgency})";
        }
        if ($overdueFollowups > 0) {
            $why[] = "{$overdueFollowups} overdue follow-up(s)";
        }
        if ($matches['count'] > 0) {
            $why[] = $matches['count'].' matching listing(s) available';
        }
        if ($lead->next_action) {
            $why[] = 'Existing next action: '.$lead->next_action;
        }

        $riskReason = null;
        if ($isAtRisk) {
            $parts = [];
            if ($isNeglected) {
                $parts[] = "Inactive {$daysInactive} day(s)";
            }
            if ($overdueFollowups > 0) {
                $parts[] = 'Overdue follow-ups';
            }
            if ($isHighPriority && $daysInactive >= 7) {
                $parts[] = 'High priority without recent contact';
            }
            $riskReason = implode(' · ', $parts) ?: 'CRM risk signals detected';
        }

        $stageDurationDays = null;
        if ($lead->last_stage_change_at) {
            $stageDurationDays = (int) Carbon::parse($lead->last_stage_change_at)->diffInDays(now());
        }

        $dashboardCard = [
            'id' => $lead->id,
            'lead_name' => $lead->lead_name ?: ($lead->deal_name ?: 'Unnamed lead'),
            'intent' => $lead->intent,
            'priority' => $lead->priority,
            'score' => $lead->score,
            'next_action' => $lead->next_action,
            'recommended_action' => $lead->next_action,
            'stage_name' => $lead->stage?->name,
            'stage_id' => $lead->stage_id,
            'area' => $lead->area?->name,
            'property_type' => $lead->propertyType?->name,
            'budget_display' => $budgetDisplay,
            'last_contact_label' => $lastContactLabel,
            'matching_listings_count' => $matches['count'],
            'matching_listings' => $matches['listings'],
            'responsible_person' => $lead->responsiblePerson
                ? ['id' => $lead->responsiblePerson->id, 'name' => $lead->responsiblePerson->name]
                : null,
            'why' => $why,
            'why_text' => $why !== [] ? implode('. ', $why).'.' : null,
            'risk_reason' => $riskReason,
            'risk_level' => $urgency ?? ($isAtRisk ? 'elevated' : null),
            'urgency_bucket' => $urgency,
            'days_inactive' => $daysInactive,
            'is_high_priority' => $isHighPriority,
            'is_at_risk' => $isAtRisk,
            'is_neglected' => $isNeglected,
            'requires_action_today' => $requiresActionToday,
            'status_lead' => $lead->status_lead,
            'interaction_result' => $lead->interaction_result,
        ];

        $context = [
            'lead' => [
                'id' => $lead->id,
                'lead_name' => $lead->lead_name,
                'lead_source' => $lead->lead_source,
                'stage_id' => $lead->stage_id,
                'stage_name' => $lead->stage?->name,
                'status_lead' => $lead->status_lead,
                'responsible_person_id' => $lead->responsible_person_id,
                'responsible_person_name' => $lead->responsiblePerson?->name,
                'created_at' => optional($lead->created_at)?->toIso8601String(),
                'updated_at' => optional($lead->updated_at)?->toIso8601String(),
                'converted_at' => optional($lead->converted_at)?->toIso8601String(),
                'score' => $lead->score,
                'intent' => $lead->intent,
                'priority' => $lead->priority,
                'next_action' => $lead->next_action,
                'interaction_result' => $lead->interaction_result,
                'bitrix24_last_activity_at' => optional($lead->bitrix24_last_activity_at)?->toIso8601String(),
                'last_stage_change_at' => optional($lead->last_stage_change_at)?->toIso8601String(),
                'stage_duration_days' => $stageDurationDays,
            ],
            'requirements' => [
                'property_type_id' => $lead->property_type_id,
                'property_type' => $lead->propertyType?->name,
                'area_id' => $lead->area_id,
                'area' => $lead->area?->name,
                'project_id' => $lead->project_id ?? $lead->integration?->project_id,
                'bedrooms' => $lead->bedrooms,
                'budget' => $lead->budget,
                'budget_from' => $lead->budget_from,
                'budget_to' => $lead->budget_to,
                'lead_type' => $lead->lead_type,
                'property_status' => $lead->property_status,
                'extra_client_requirements' => $lead->extra_client_requirements,
                'whatsapp_qualification' => $lead->whatsapp_qualification,
            ],
            'comments' => [
                'count' => (int) ($meta['comment_count'] ?? 0),
                'latest_at' => $meta['comment_latest_at'] ?? null,
                'recent' => $comments,
            ],
            'activities' => [
                'count' => (int) ($meta['activity_count'] ?? 0),
                'latest_at' => $meta['activity_latest_at'] ?? null,
                'overdue_count' => $overdueFollowups,
                'pending_count' => (int) ($meta['pending_activities'] ?? 0),
                'recent' => $activities,
            ],
            'history' => [
                'count' => (int) ($meta['history_count'] ?? 0),
                'latest_at' => $meta['history_latest_at'] ?? null,
                'recent_stage_changes' => $histories['stage_changes'],
                'recent_assignments' => $histories['assignments'],
                'recent' => $histories['recent'],
            ],
            'inactivity' => [
                'last_meaningful_at' => $lastMeaningful?->toIso8601String(),
                'days_since_last_activity' => $daysInactive,
                'is_inactive' => $isNeglected,
                'urgency_bucket' => $urgency,
                'thresholds' => config('ai_lead_intelligence.inactivity'),
            ],
            'property_matches' => $matches,
        ];

        return [
            'urgency_bucket' => $urgency,
            'is_high_priority' => $isHighPriority,
            'is_at_risk' => $isAtRisk,
            'is_neglected' => $isNeglected,
            'requires_action_today' => $requiresActionToday,
            'matching_listings_count' => $matches['count'],
            'dashboard_card' => $dashboardCard,
            'context' => $context,
        ];
    }

    /**
     * @param  list<int>  $leadIds
     * @return array<int, array<string, mixed>>
     */
    protected function batchMeta(array $leadIds): array
    {
        $meta = [];
        foreach ($leadIds as $id) {
            $meta[$id] = [
                'comment_count' => 0,
                'comment_latest_at' => null,
                'activity_count' => 0,
                'activity_latest_at' => null,
                'history_count' => 0,
                'history_latest_at' => null,
                'overdue_activities' => 0,
                'pending_activities' => 0,
            ];
        }

        $commentAgg = LeadComment::query()
            ->whereIn('lead_id', $leadIds)
            ->selectRaw('lead_id, COUNT(*) as c, MAX(updated_at) as latest_at')
            ->groupBy('lead_id')
            ->get();
        foreach ($commentAgg as $row) {
            $meta[(int) $row->lead_id]['comment_count'] = (int) $row->c;
            $meta[(int) $row->lead_id]['comment_latest_at'] = $row->latest_at;
        }

        $activityAgg = LeadActivity::query()
            ->whereIn('lead_id', $leadIds)
            ->selectRaw('lead_id, COUNT(*) as c, MAX(updated_at) as latest_at')
            ->groupBy('lead_id')
            ->get();
        foreach ($activityAgg as $row) {
            $meta[(int) $row->lead_id]['activity_count'] = (int) $row->c;
            $meta[(int) $row->lead_id]['activity_latest_at'] = $row->latest_at;
        }

        $overdueAgg = LeadActivity::query()
            ->whereIn('lead_id', $leadIds)
            ->where('is_completed', false)
            ->where('reminder_date', '<', now())
            ->selectRaw('lead_id, COUNT(*) as c')
            ->groupBy('lead_id')
            ->get();
        foreach ($overdueAgg as $row) {
            $meta[(int) $row->lead_id]['overdue_activities'] = (int) $row->c;
        }

        $pendingAgg = LeadActivity::query()
            ->whereIn('lead_id', $leadIds)
            ->where('is_completed', false)
            ->selectRaw('lead_id, COUNT(*) as c')
            ->groupBy('lead_id')
            ->get();
        foreach ($pendingAgg as $row) {
            $meta[(int) $row->lead_id]['pending_activities'] = (int) $row->c;
        }

        $historyAgg = LeadHistory::query()
            ->whereIn('lead_id', $leadIds)
            ->selectRaw('lead_id, COUNT(*) as c, MAX(updated_at) as latest_at')
            ->groupBy('lead_id')
            ->get();
        foreach ($historyAgg as $row) {
            $meta[(int) $row->lead_id]['history_count'] = (int) $row->c;
            $meta[(int) $row->lead_id]['history_latest_at'] = $row->latest_at;
        }

        return $meta;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function fingerprint(Lead $lead, array $meta): string
    {
        $payload = [
            'u' => optional($lead->updated_at)?->timestamp,
            's' => $lead->stage_id,
            'r' => $lead->responsible_person_id,
            'sc' => $lead->score,
            'pr' => $lead->priority,
            'in' => $lead->intent,
            'na' => $lead->next_action,
            'ir' => $lead->interaction_result,
            'ba' => (string) $lead->bitrix24_last_activity_at,
            'ls' => (string) $lead->last_stage_change_at,
            'pt' => $lead->property_type_id,
            'ar' => $lead->area_id,
            'pj' => $lead->project_id,
            'bd' => $lead->bedrooms,
            'bf' => $lead->budget_from,
            'bt' => $lead->budget_to,
            'bg' => $lead->budget,
            'lt' => $lead->lead_type,
            'ps' => $lead->property_status,
            'ex' => $lead->extra_client_requirements,
            'wq' => $lead->whatsapp_qualification,
            'cc' => $meta['comment_count'] ?? 0,
            'cl' => $meta['comment_latest_at'] ?? null,
            'ac' => $meta['activity_count'] ?? 0,
            'al' => $meta['activity_latest_at'] ?? null,
            'hc' => $meta['history_count'] ?? 0,
            'hl' => $meta['history_latest_at'] ?? null,
            'ov' => $meta['overdue_activities'] ?? 0,
            'cfg' => config('ai_lead_intelligence.inactivity'),
        ];

        return hash('sha256', json_encode($payload));
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function resolveLastMeaningfulAt(Lead $lead, array $meta): ?Carbon
    {
        $candidates = [];

        if (!empty($lead->bitrix24_last_activity_at)) {
            $candidates[] = Carbon::parse($lead->bitrix24_last_activity_at);
        }
        if (!empty($meta['comment_latest_at'])) {
            $candidates[] = Carbon::parse($meta['comment_latest_at']);
        }
        if (!empty($meta['activity_latest_at'])) {
            $candidates[] = Carbon::parse($meta['activity_latest_at']);
        }
        if (!empty($lead->last_stage_change_at)) {
            $candidates[] = Carbon::parse($lead->last_stage_change_at);
        }
        if (!empty($lead->updated_at)) {
            $candidates[] = Carbon::parse($lead->updated_at);
        }

        if ($candidates === []) {
            return null;
        }

        return collect($candidates)->sortByDesc(fn (Carbon $c) => $c->timestamp)->first();
    }

    protected function urgencyBucket(int $daysInactive): ?string
    {
        $critical = (int) config('ai_lead_intelligence.inactivity.critical_days', 14);
        $needs = (int) config('ai_lead_intelligence.inactivity.needs_attention_days', 7);
        $monitor = (int) config('ai_lead_intelligence.inactivity.monitor_days', 3);

        if ($daysInactive >= $critical) {
            return 'critical';
        }
        if ($daysInactive >= $needs) {
            return 'needs_attention';
        }
        if ($daysInactive >= $monitor) {
            return 'monitor';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return list<array<string, mixed>>
     */
    protected function recentComments(int $leadId, array $meta): array
    {
        $limit = (int) config('ai_lead_intelligence.comments.recent_limit', 10);
        $maxChars = (int) config('ai_lead_intelligence.comments.max_chars_per_comment', 500);

        if (((int) ($meta['comment_count'] ?? 0)) === 0) {
            return [];
        }

        return LeadComment::query()
            ->with('user:id,name')
            ->where('lead_id', $leadId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'lead_id', 'user_id', 'comment', 'created_at', 'updated_at'])
            ->map(function (LeadComment $c) use ($maxChars) {
                $text = (string) ($c->comment ?? '');
                if (mb_strlen($text) > $maxChars) {
                    $text = mb_substr($text, 0, $maxChars).'…';
                }

                return [
                    'id' => $c->id,
                    'user_id' => $c->user_id,
                    'author' => $c->user?->name,
                    'comment' => $text,
                    'created_at' => optional($c->created_at)?->toIso8601String(),
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return list<array<string, mixed>>
     */
    protected function recentActivities(int $leadId, array $meta): array
    {
        $limit = (int) config('ai_lead_intelligence.activities.recent_limit', 10);
        if (((int) ($meta['activity_count'] ?? 0)) === 0) {
            return [];
        }

        return LeadActivity::query()
            ->with('user:id,name')
            ->where('lead_id', $leadId)
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get(['id', 'lead_id', 'user_id', 'title', 'reminder_date', 'is_completed', 'created_at', 'updated_at'])
            ->map(function (LeadActivity $a) {
                return [
                    'id' => $a->id,
                    'title' => $a->title,
                    'user_id' => $a->user_id,
                    'author' => $a->user?->name,
                    'reminder_date' => optional($a->reminder_date)?->toIso8601String(),
                    'is_completed' => (bool) $a->is_completed,
                    'is_overdue' => !$a->is_completed && $a->reminder_date && $a->reminder_date->isPast(),
                    'updated_at' => optional($a->updated_at)?->toIso8601String(),
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{recent:list<array<string,mixed>>,stage_changes:list<array<string,mixed>>,assignments:list<array<string,mixed>>}
     */
    protected function recentHistories(int $leadId, array $meta): array
    {
        $limit = (int) config('ai_lead_intelligence.histories.recent_limit', 15);
        $empty = ['recent' => [], 'stage_changes' => [], 'assignments' => []];
        if (((int) ($meta['history_count'] ?? 0)) === 0) {
            return $empty;
        }

        $rows = LeadHistory::query()
            ->where('lead_id', $leadId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'lead_id', 'user_id', 'changes', 'created_at']);

        $recent = [];
        $stageChanges = [];
        $assignments = [];

        foreach ($rows as $row) {
            $changes = is_array($row->changes) ? $row->changes : [];
            $action = $changes['action'] ?? null;
            $entry = [
                'id' => $row->id,
                'action' => $action,
                'created_at' => optional($row->created_at)?->toIso8601String(),
                'user_id' => $row->user_id,
            ];

            if ($action === 'stage_changed') {
                $entry['old_stage'] = $changes['old_stage'] ?? null;
                $entry['new_stage'] = $changes['new_stage'] ?? null;
                $stageChanges[] = $entry;
            }

            if ($action === 'updated' && isset($changes['fields']['responsible_person_id'])) {
                $entry['assignment'] = $changes['fields']['responsible_person_id'];
                $assignments[] = $entry;
            }

            // Compact: drop heavy field dumps from recent list
            if ($action === 'updated') {
                $entry['changed_keys'] = array_keys($changes['fields'] ?? []);
            } elseif ($action === 'stage_changed') {
                // already set
            } else {
                $entry['action'] = $action;
            }

            $recent[] = $entry;
        }

        return [
            'recent' => $recent,
            'stage_changes' => array_slice($stageChanges, 0, 8),
            'assignments' => array_slice($assignments, 0, 8),
        ];
    }

    protected function formatBudget(Lead $lead): ?string
    {
        $from = $lead->budget_from;
        $to = $lead->budget_to;
        $single = $lead->budget;

        if ($from || $to) {
            $parts = [];
            if ($from) {
                $parts[] = number_format((float) $from);
            }
            if ($to) {
                $parts[] = number_format((float) $to);
            }

            return implode(' – ', $parts).' AED';
        }

        if ($single) {
            return number_format((float) $single).' AED';
        }

        return null;
    }
}
