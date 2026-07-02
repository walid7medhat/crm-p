<?php

namespace App\Services\AiSalesIntelligence;

use App\Models\AiSalesIntelligence\AiAgentMetric;
use App\Models\AiSalesIntelligence\AiAgentRanking;
use Illuminate\Support\Collection;

class AiRankingService
{
    /**
     * @param  Collection<int, AiAgentMetric>  $metrics
     */
    public function persistRankings(Collection $metrics): void
    {
        $date = now()->toDateString();
        $ranked = fn (string $column) => $metrics->sortByDesc($column)->values();

        $overall = $ranked('overall_ai_score');
        $behavior = $ranked('behavior_score');
        $pipeline = $ranked('pipeline_score');
        $followup = $ranked('followup_score');
        $qualification = $ranked('qualification_score');
        $communication = $ranked('communication_score');
        $conversion = $ranked('conversion_score');

        $rankMap = function (Collection $sorted, string $scoreKey) {
            $map = [];
            foreach ($sorted as $i => $metric) {
                $map[$metric->user_id] = [
                    'rank' => $i + 1,
                    'score' => (float) $metric->{$scoreKey},
                ];
            }

            return $map;
        };

        $maps = [
            'overall' => $rankMap($overall, 'overall_ai_score'),
            'behavior' => $rankMap($behavior, 'behavior_score'),
            'pipeline' => $rankMap($pipeline, 'pipeline_score'),
            'followup' => $rankMap($followup, 'followup_score'),
            'qualification' => $rankMap($qualification, 'qualification_score'),
            'communication' => $rankMap($communication, 'communication_score'),
            'conversion' => $rankMap($conversion, 'conversion_score'),
        ];

        foreach ($metrics as $metric) {
            $uid = $metric->user_id;
            AiAgentRanking::query()->updateOrCreate(
                ['snapshot_date' => $date, 'user_id' => $uid],
                [
                    'overall_rank' => $maps['overall'][$uid]['rank'] ?? 0,
                    'behavior_rank' => $maps['behavior'][$uid]['rank'] ?? 0,
                    'pipeline_rank' => $maps['pipeline'][$uid]['rank'] ?? 0,
                    'followup_rank' => $maps['followup'][$uid]['rank'] ?? 0,
                    'qualification_rank' => $maps['qualification'][$uid]['rank'] ?? 0,
                    'communication_rank' => $maps['communication'][$uid]['rank'] ?? 0,
                    'conversion_rank' => $maps['conversion'][$uid]['rank'] ?? 0,
                    'scores' => [
                        'overall_ai_score' => $metric->overall_ai_score,
                        'behavior_score' => $metric->behavior_score,
                        'pipeline_score' => $metric->pipeline_score,
                        'followup_score' => $metric->followup_score,
                        'qualification_score' => $metric->qualification_score,
                        'communication_score' => $metric->communication_score,
                        'conversion_score' => $metric->conversion_score,
                    ],
                ]
            );
        }
    }
}
