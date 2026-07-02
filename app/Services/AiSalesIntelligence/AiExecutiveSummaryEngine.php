<?php

namespace App\Services\AiSalesIntelligence;

use App\Models\User;

class AiExecutiveSummaryEngine
{
    /**
     * @param  array<string, mixed>  $metrics
     * @param  array<string, mixed>  $scores
     * @param  list<array<string, mixed>>  $observations
     */
    public function generate(User $user, array $metrics, array $scores, array $observations): string
    {
        $name = User::shortName($user->name) ?? $user->name;
        $status = str_replace('_', ' ', $scores['status'] ?? 'needs attention');
        $overall = $scores['overall_ai_score'] ?? 0;

        $strengths = [];
        $weaknesses = [];
        $priorities = [];

        if (($scores['pipeline_score'] ?? 0) >= 75) {
            $strengths[] = 'excellent pipeline discipline';
        } elseif (($scores['pipeline_score'] ?? 0) < 55) {
            $weaknesses[] = 'pipeline hygiene needs work';
        }

        if (($scores['response_score'] ?? 0) < 60) {
            $weaknesses[] = 'slow first response';
        } elseif (($scores['response_score'] ?? 0) >= 80) {
            $strengths[] = 'fast first contact';
        }

        if (($scores['followup_score'] ?? 0) < 60) {
            $weaknesses[] = 'weak follow-up';
        }

        if (($scores['conversion_score'] ?? 0) >= 70) {
            $strengths[] = 'conversion remains strong';
        }

        $neglectedStage = $this->mostNeglectedStage($metrics);
        if ($neglectedStage) {
            $weaknesses[] = "most neglected stage is {$neglectedStage}";
        }

        if (((int) ($metrics['response_metrics']['not_contacted_count'] ?? 0)) > 0) {
            $priorities[] = 'Reduce untouched assignments';
        }
        if (((int) ($metrics['followup_metrics']['abandoned_after_no_answer'] ?? 0)) > 0) {
            $priorities[] = 'Improve follow-up after No Answer';
        }
        if (((int) ($metrics['pipeline_metrics']['inactive_qualified'] ?? 0)) > 0) {
            $priorities[] = 'Move stale qualified leads';
        }

        $parts = [];
        $parts[] = "{$name} has an overall AI score of {$overall} ({$status}).";

        if ($strengths !== []) {
            $parts[] = ucfirst(implode(' but ', $strengths)).'.';
        }
        if ($weaknesses !== []) {
            $parts[] = 'Areas of concern: '.implode(', ', $weaknesses).'.';
        }
        if ($priorities !== []) {
            $parts[] = 'Priority this week: '.implode('; ', $priorities).'.';
        }

        if (count($parts) === 1 && $observations !== []) {
            $parts[] = $observations[0]['observation'];
        }

        return implode(' ', $parts);
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    protected function mostNeglectedStage(array $metrics): ?string
    {
        $drill = $metrics['neglect_metrics']['drilldown'] ?? [];
        $counts = [
            'Contacted' => count($drill['needs_contact'] ?? []),
            'Follow Up' => count($drill['needs_followup'] ?? []),
            'Qualified' => count($drill['inactive'] ?? []),
        ];
        arsort($counts);
        $top = array_key_first($counts);

        return ($counts[$top] ?? 0) > 0 ? $top : null;
    }
}
