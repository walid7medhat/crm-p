<?php

namespace App\Services\AiSalesIntelligence;

class AiCoachingEngine
{
    /**
     * @param  array<string, mixed>  $metrics
     * @param  array<string, mixed>  $scores
     * @param  list<array<string, mixed>>  $observations
     * @return list<array<string, string>>
     */
    public function generate(array $metrics, array $scores, array $observations): array
    {
        $cards = [];

        if (($scores['response_score'] ?? 100) < 70) {
            $cards[] = [
                'title' => 'Improve first response speed',
                'body' => 'Contact new assignments within 30 minutes. Set a reminder for every untouched lead.',
                'priority' => 'high',
            ];
        }

        if (((int) ($metrics['pipeline_metrics']['inactive_qualified'] ?? 0)) > 0) {
            $cards[] = [
                'title' => 'Reduce inactive qualified leads',
                'body' => 'Review qualified leads with no update in 7+ days and schedule next action or move stage.',
                'priority' => 'high',
            ];
        }

        if (((int) ($metrics['followup_metrics']['abandoned_after_no_answer'] ?? 0)) > 0) {
            $cards[] = [
                'title' => 'Schedule follow-up after No Answer',
                'body' => 'Every no-answer call should get a follow-up activity within the same day.',
                'priority' => 'high',
            ];
        }

        if (((int) ($metrics['pipeline_metrics']['stuck_leads'] ?? 0)) > 0) {
            $cards[] = [
                'title' => 'Move inactive leads from Contacted',
                'body' => 'Leads stuck in Follow Up need qualification progress or a lost/pool decision.',
                'priority' => 'medium',
            ];
        }

        if (($scores['communication_score'] ?? 100) < 65) {
            $cards[] = [
                'title' => 'Increase comment quality',
                'body' => 'Log call outcomes and client requirements in comments after every interaction.',
                'priority' => 'medium',
            ];
        }

        if (((int) ($metrics['response_metrics']['not_contacted_count'] ?? 0)) > 0) {
            $cards[] = [
                'title' => 'Reduce abandoned assignments',
                'body' => 'Work untouched assignments first — add activity, comment, or stage move today.',
                'priority' => 'critical',
            ];
        }

        foreach ($observations as $obs) {
            if ($obs['severity'] === 'critical' && count($cards) < 8) {
                $cards[] = [
                    'title' => 'Address risk alert',
                    'body' => $obs['observation'],
                    'priority' => 'critical',
                ];
            }
        }

        return array_slice($cards, 0, 8);
    }
}
