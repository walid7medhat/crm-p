<?php

namespace App\Services\AiSalesIntelligence;

class AiAlertEngine
{
    /**
     * @param  array<string, mixed>  $metrics
     * @param  array<string, mixed>  $scores
     * @return list<array<string, mixed>>
     */
    public function generate(int $userId, array $metrics, array $scores): array
    {
        $alerts = [];
        $response = $metrics['response_metrics'] ?? [];
        $pipeline = $metrics['pipeline_metrics'] ?? [];
        $followup = $metrics['followup_metrics'] ?? [];
        $neglect = $metrics['neglect_metrics'] ?? [];

        $untouched = (int) ($response['not_contacted_count'] ?? 0);
        if ($untouched >= 3) {
            $alerts[] = $this->alert(
                $userId,
                'untouched_leads',
                $untouched >= 10 ? 'high' : 'medium',
                'Untouched leads',
                "Agent has {$untouched} untouched leads.",
                ['count' => $untouched]
            );
        }

        $inactiveQualified = (int) ($pipeline['inactive_qualified'] ?? 0);
        if ($inactiveQualified >= 3) {
            $alerts[] = $this->alert(
                $userId,
                'inactive_qualified',
                'high',
                'Inactive qualified leads',
                "{$inactiveQualified} qualified leads inactive for 7+ days.",
                ['count' => $inactiveQualified]
            );
        }

        $overdue = (int) ($followup['overdue_followups'] ?? 0);
        if ($overdue >= 5) {
            $alerts[] = $this->alert(
                $userId,
                'overdue_followups',
                'high',
                'Overdue follow-ups',
                "Follow-up overdue on {$overdue} leads.",
                ['count' => $overdue]
            );
        }

        if (((int) ($pipeline['lead_pool_leads'] ?? 0)) >= 5) {
            $alerts[] = $this->alert(
                $userId,
                'lead_pool_growth',
                'medium',
                'Lead Pool increasing',
                'Lead Pool contains multiple leads — review assignment flow.',
                ['count' => $pipeline['lead_pool_leads']]
            );
        }

        $reverted = collect($neglect['neglected_leads'] ?? [])
            ->filter(fn ($l) => in_array('reverted_inactivity', $l['reasons'] ?? [], true))
            ->count();
        if ($reverted >= 2) {
            $alerts[] = $this->alert(
                $userId,
                'high_revert_rate',
                'medium',
                'High revert rate',
                "{$reverted} leads reverted due to inactivity.",
                ['count' => $reverted]
            );
        }

        if (($scores['risk_level'] ?? '') === 'high') {
            $alerts[] = $this->alert(
                $userId,
                'high_risk_agent',
                'high',
                'Agent risk elevated',
                'Overall risk score indicates immediate manager review.',
                ['risk_score' => $scores['risk_score'] ?? null]
            );
        }

        return $alerts;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function alert(int $userId, string $type, string $severity, string $title, string $message, array $meta = []): array
    {
        return [
            'user_id' => $userId,
            'alert_type' => $type,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'meta' => $meta,
        ];
    }
}
