<?php

namespace Tests\Unit\AiSalesIntelligence;

use App\Services\AiSalesIntelligence\AiScoringEngine;
use PHPUnit\Framework\TestCase;

class AiScoringEngineTest extends TestCase
{
    public function test_scores_are_deterministic_and_bounded(): void
    {
        $engine = new AiScoringEngine();

        $metrics = [
            'pipeline_metrics' => [
                'pipeline_cleanliness_score' => 80,
                'forward_movement_rate' => 70,
                'backward_movement_rate' => 10,
                'stuck_leads' => 1,
                'inactive_qualified' => 0,
            ],
            'response_metrics' => [
                'avg_minutes_to_first_activity' => 20,
                'not_contacted_count' => 1,
            ],
            'followup_metrics' => [
                'reminder_completion_rate' => 75,
                'overdue_followups' => 2,
                'abandoned_after_no_answer' => 0,
            ],
            'qualification_metrics' => [
                'qualified_rate' => 60,
                'qualified_to_deal_rate' => 40,
                'qualified_then_inactive' => 1,
            ],
            'communication_metrics' => [
                'comments_per_lead' => 2,
                'activities_per_lead' => 1.5,
                'answered_rate' => 55,
                'leads_with_zero_comments' => 2,
            ],
            'neglect_metrics' => ['neglect_count' => 3],
            'conversion' => ['conversion_rate' => 65],
        ];

        $config = \App\Models\AiSalesIntelligence\AiScoringRule::defaultConfig();
        $scores = $engine->score($metrics, $config);

        $this->assertGreaterThanOrEqual(0, $scores['overall_ai_score']);
        $this->assertLessThanOrEqual(100, $scores['overall_ai_score']);
        $this->assertContains($scores['status'], ['excellent', 'good', 'needs_attention', 'critical']);
        $this->assertContains($scores['risk_level'], ['low', 'medium', 'high']);
        $this->assertSame($scores, $engine->score($metrics, $config));
    }

    public function test_slow_response_lowers_response_score(): void
    {
        $engine = new AiScoringEngine();

        $config = \App\Models\AiSalesIntelligence\AiScoringRule::defaultConfig();

        $fast = $engine->score([
            'pipeline_metrics' => [],
            'response_metrics' => ['avg_minutes_to_first_activity' => 10],
            'followup_metrics' => [],
            'qualification_metrics' => [],
            'communication_metrics' => [],
            'neglect_metrics' => ['neglect_count' => 0],
            'conversion' => ['conversion_rate' => 0],
        ], $config);

        $slow = $engine->score([
            'pipeline_metrics' => [],
            'response_metrics' => ['avg_minutes_to_first_activity' => 300],
            'followup_metrics' => [],
            'qualification_metrics' => [],
            'communication_metrics' => [],
            'neglect_metrics' => ['neglect_count' => 0],
            'conversion' => ['conversion_rate' => 0],
        ], $config);

        $this->assertGreaterThan($slow['response_score'], $fast['response_score']);
    }
}
