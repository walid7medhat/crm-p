<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Services\LeadIntelligenceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ProcessLeadIntelligenceJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $leadId)
    {
    }

    public function handle(LeadIntelligenceService $service): void
    {
        foreach (['score', 'priority', 'intent', 'next_action', 'last_scored_at', 'score_breakdown'] as $column) {
            if (!Schema::hasColumn('leads', $column)) {
                return;
            }
        }

        $lead = Lead::find($this->leadId);
        if (!$lead) {
            return;
        }

        $minMinutesBetweenScoring = (int) config('lead_intelligence.min_minutes_between_scoring', 5);
        if ($lead->last_scored_at && $lead->last_scored_at->gt(now()->subMinutes($minMinutesBetweenScoring))) {
            return;
        }

        $result = $service->generateRecommendation($lead);

        $lead->forceFill([
            'score' => $result['score'],
            'priority' => $result['priority'],
            'intent' => $result['intent'],
            'next_action' => $result['next_action'],
            'last_scored_at' => now(),
            'score_breakdown' => $result['score_breakdown'] ?? null,
        ])->saveQuietly();

        if (config('lead_intelligence.debug', false)) {
            Log::info('lead_intelligence.scored', [
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
    }
}
