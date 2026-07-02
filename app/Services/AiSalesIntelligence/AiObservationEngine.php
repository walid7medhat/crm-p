<?php

namespace App\Services\AiSalesIntelligence;

class AiObservationEngine
{
    /**
     * @param  array<string, mixed>  $metrics
     * @param  array<string, mixed>  $scores
     * @return list<array<string, mixed>>
     */
    public function generate(int $userId, array $metrics, array $scores, ?float $teamAvgResponse = null): array
    {
        $observations = [];
        $response = $metrics['response_metrics'] ?? [];
        $followup = $metrics['followup_metrics'] ?? [];
        $pipeline = $metrics['pipeline_metrics'] ?? [];
        $qualification = $metrics['qualification_metrics'] ?? [];
        $communication = $metrics['communication_metrics'] ?? [];

        $avgResponse = $response['avg_minutes_to_first_activity'] ?? null;
        if ($avgResponse !== null && $avgResponse > 60) {
            $observations[] = $this->obs(
                'response',
                'warning',
                'Agent waits too long before contacting new leads.',
                ['avg_minutes' => $avgResponse]
            );
        }

        if (((int) ($qualification['qualified_without_followup'] ?? 0)) > 2) {
            $observations[] = $this->obs(
                'followup',
                'warning',
                'Most qualified leads receive no follow-up.',
                ['count' => $qualification['qualified_without_followup']]
            );
        }

        if (((int) ($followup['abandoned_after_no_answer'] ?? 0)) > 1) {
            $observations[] = $this->obs(
                'communication',
                'warning',
                'High number of unanswered calls without second attempt.',
                ['count' => $followup['abandoned_after_no_answer']]
            );
        }

        if (((int) ($pipeline['stuck_leads'] ?? 0)) > 0) {
            $observations[] = $this->obs(
                'pipeline',
                'info',
                'Large amount of leads stay in Follow Up for more than 10 days.',
                ['stuck_leads' => $pipeline['stuck_leads']]
            );
        }

        if (((float) ($followup['reminder_completion_rate'] ?? 100)) < 60) {
            $observations[] = $this->obs(
                'followup',
                'warning',
                'Agent frequently forgets to schedule or complete reminders.',
                ['completion_rate' => $followup['reminder_completion_rate']]
            );
        }

        if (((int) ($pipeline['inactive_qualified'] ?? 0)) > 2) {
            $observations[] = $this->obs(
                'pipeline',
                'warning',
                'Pipeline contains many stale qualified leads.',
                ['inactive_qualified' => $pipeline['inactive_qualified']]
            );
        }

        if ($teamAvgResponse !== null && $avgResponse !== null && $avgResponse > $teamAvgResponse * 1.5) {
            $observations[] = $this->obs(
                'response',
                'warning',
                'Average response time is worse than team average.',
                ['agent_avg' => $avgResponse, 'team_avg' => $teamAvgResponse]
            );
        }

        if (((int) ($communication['leads_with_zero_comments'] ?? 0)) > 5) {
            $observations[] = $this->obs(
                'communication',
                'info',
                'Many assigned leads have zero comments on record.',
                ['count' => $communication['leads_with_zero_comments']]
            );
        }

        if (((int) ($response['not_contacted_count'] ?? 0)) > 3) {
            $observations[] = $this->obs(
                'neglect',
                'critical',
                'Multiple assigned leads have not been contacted.',
                ['count' => $response['not_contacted_count']]
            );
        }

        return $observations;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function obs(string $category, string $severity, string $text, array $meta = []): array
    {
        return [
            'category' => $category,
            'severity' => $severity,
            'observation' => $text,
            'meta' => $meta,
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
