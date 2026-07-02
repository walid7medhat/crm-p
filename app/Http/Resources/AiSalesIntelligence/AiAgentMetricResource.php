<?php

namespace App\Http\Resources\AiSalesIntelligence;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiAgentMetricResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'overall_ai_score' => $this->overall_ai_score,
            'status' => $this->status,
            'risk_level' => $this->risk_level,
            'scores' => [
                'pipeline' => $this->pipeline_score,
                'response' => $this->response_score,
                'followup' => $this->followup_score,
                'qualification' => $this->qualification_score,
                'communication' => $this->communication_score,
                'discipline' => $this->discipline_score,
                'engagement' => $this->engagement_score,
                'neglect' => $this->neglect_score,
                'risk' => $this->risk_score,
                'behavior' => $this->behavior_score,
                'conversion' => $this->conversion_score,
            ],
            'pipeline_metrics' => $this->pipeline_metrics,
            'response_metrics' => $this->response_metrics,
            'followup_metrics' => $this->followup_metrics,
            'qualification_metrics' => $this->qualification_metrics,
            'communication_metrics' => $this->communication_metrics,
            'neglect_metrics' => $this->neglect_metrics,
            'daily_performance' => $this->daily_performance,
            'weekly_trends' => $this->weekly_trends,
            'coaching_cards' => $this->coaching_cards,
            'executive_summary' => $this->executive_summary,
            'computed_at' => $this->computed_at?->toIso8601String(),
        ];
    }
}
