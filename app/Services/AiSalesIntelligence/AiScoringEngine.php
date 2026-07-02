<?php

namespace App\Services\AiSalesIntelligence;

use App\Models\AiSalesIntelligence\AiScoringRule;

class AiScoringEngine
{
    /** @var array<string, mixed>|null */
    protected ?array $config = null;

  /**
   * @param  array<string, mixed>  $metrics
   * @return array<string, mixed>
   */
    public function score(array $metrics, ?array $config = null): array
    {
        $cfg = $config ?? $this->config ?? AiScoringRule::resolved();
        $this->config = $cfg;

        $pipeline = $metrics['pipeline_metrics'] ?? [];
        $response = $metrics['response_metrics'] ?? [];
        $followup = $metrics['followup_metrics'] ?? [];
        $qualification = $metrics['qualification_metrics'] ?? [];
        $communication = $metrics['communication_metrics'] ?? [];
        $neglect = $metrics['neglect_metrics'] ?? [];
        $conversion = $metrics['conversion'] ?? [];

        $pipelineScore = $this->clamp(
            (float) ($pipeline['pipeline_cleanliness_score'] ?? 50)
            + ((float) ($pipeline['forward_movement_rate'] ?? 0) * 0.2)
            - ((float) ($pipeline['backward_movement_rate'] ?? 0) * 0.3)
            - min(25, ((int) ($pipeline['stuck_leads'] ?? 0)) * 3)
        );

        $responseScore = $this->scoreResponse($response, $cfg['response_sla'] ?? []);
        $followupScore = $this->clamp(
            (float) ($followup['reminder_completion_rate'] ?? 50)
            - min(30, ((int) ($followup['overdue_followups'] ?? 0)) * 2)
            - min(20, ((int) ($followup['abandoned_after_no_answer'] ?? 0)) * 4)
        );

        $qualificationScore = $this->clamp(
            (float) ($qualification['qualified_rate'] ?? 0) * 0.5
            + (float) ($qualification['qualified_to_deal_rate'] ?? 0) * 0.5
            - min(25, ((int) ($qualification['qualified_then_inactive'] ?? 0)) * 3)
        );

        $communicationScore = $this->clamp(
            min(40, ((float) ($communication['comments_per_lead'] ?? 0)) * 15)
            + min(30, ((float) ($communication['activities_per_lead'] ?? 0)) * 10)
            + ((float) ($communication['answered_rate'] ?? 0) * 0.3)
            - min(30, ((int) ($communication['leads_with_zero_comments'] ?? 0)) * 2)
        );

        $neglectCount = (int) ($neglect['neglect_count'] ?? 0);
        $neglectScore = $this->clamp(100 - min(80, $neglectCount * 4));
        $conversionScore = $this->clamp((float) ($conversion['conversion_rate'] ?? 0));

        $bw = $cfg['behavior'] ?? [];
        $behaviorScore = $this->weightedSum([
            [$responseScore, $bw['response'] ?? 0.20],
            [$followupScore, $bw['followup'] ?? 0.20],
            [$pipelineScore, $bw['pipeline'] ?? 0.15],
            [$communicationScore, $bw['communication'] ?? 0.15],
            [$qualificationScore, $bw['qualification'] ?? 0.15],
            [$neglectScore, $bw['neglect'] ?? 0.15],
        ]);

        $disciplineScore = $this->clamp(($pipelineScore * 0.4) + ($followupScore * 0.6));
        $engagementScore = $this->clamp(($communicationScore * 0.5) + ($responseScore * 0.5));

        $riskScore = $this->clamp(
            min(100, $neglectCount * 5)
            + min(30, ((int) ($response['not_contacted_count'] ?? 0)) * 3)
            + min(20, ((int) ($pipeline['inactive_qualified'] ?? 0)) * 4)
        );

        $ow = $cfg['overall'] ?? [];
        $overall = $this->weightedSum([
            [$behaviorScore, $ow['behavior'] ?? 0.35],
            [$pipelineScore, $ow['pipeline'] ?? 0.15],
            [$followupScore, $ow['followup'] ?? 0.15],
            [$qualificationScore, $ow['qualification'] ?? 0.10],
            [$communicationScore, $ow['communication'] ?? 0.10],
            [$conversionScore, $ow['conversion'] ?? 0.10],
            [$neglectScore, $ow['neglect'] ?? 0.05],
        ]);

        $statusCfg = $cfg['status'] ?? [];
        $riskCfg = $cfg['risk'] ?? [];
        $status = $this->statusFromScore($overall, $statusCfg);
        $riskLevel = $this->riskFromScore($riskScore, $riskCfg);

        return [
            'overall_ai_score' => round($overall, 2),
            'status' => $status,
            'risk_level' => $riskLevel,
            'pipeline_score' => round($pipelineScore, 2),
            'response_score' => round($responseScore, 2),
            'followup_score' => round($followupScore, 2),
            'qualification_score' => round($qualificationScore, 2),
            'communication_score' => round($communicationScore, 2),
            'discipline_score' => round($disciplineScore, 2),
            'engagement_score' => round($engagementScore, 2),
            'neglect_score' => round($neglectScore, 2),
            'risk_score' => round($riskScore, 2),
            'behavior_score' => round($behaviorScore, 2),
            'conversion_score' => round($conversionScore, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  list<array{minutes: int|float, score: int|float}>  $sla
     */
    protected function scoreResponse(array $response, array $sla): float
    {
        $avg = $response['avg_minutes_to_first_activity'] ?? null;
        if ($avg === null) {
            return 45.0;
        }

        if ($sla === []) {
            $sla = AiScoringRule::defaultConfig()['response_sla'];
        }

        usort($sla, fn ($a, $b) => ($a['minutes'] ?? 0) <=> ($b['minutes'] ?? 0));
        foreach ($sla as $tier) {
            if ($avg <= (float) ($tier['minutes'] ?? 0)) {
                return (float) ($tier['score'] ?? 45);
            }
        }

        return 15.0;
    }

    /**
     * @param  array<string, float|int>  $thresholds
     */
    protected function statusFromScore(float $score, array $thresholds): string
    {
        if ($score >= (float) ($thresholds['excellent'] ?? 85)) {
            return 'excellent';
        }
        if ($score >= (float) ($thresholds['good'] ?? 70)) {
            return 'good';
        }
        if ($score >= (float) ($thresholds['needs_attention'] ?? 50)) {
            return 'needs_attention';
        }

        return 'critical';
    }

    /**
     * @param  array<string, float|int>  $thresholds
     */
    protected function riskFromScore(float $riskScore, array $thresholds): string
    {
        if ($riskScore >= (float) ($thresholds['high'] ?? 70)) {
            return 'high';
        }
        if ($riskScore >= (float) ($thresholds['medium'] ?? 40)) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * @param  list<array{0: float, 1: float}>  $parts
     */
    protected function weightedSum(array $parts): float
    {
        $sum = 0.0;
        foreach ($parts as [$value, $weight]) {
            $sum += $value * (float) $weight;
        }

        return $this->clamp($sum);
    }

    protected function clamp(float $value): float
    {
        return round(max(0, min(100, $value)), 2);
    }
}
