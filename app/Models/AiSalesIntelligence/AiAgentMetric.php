<?php

namespace App\Models\AiSalesIntelligence;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAgentMetric extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'overall_ai_score' => 'float',
            'pipeline_score' => 'float',
            'response_score' => 'float',
            'followup_score' => 'float',
            'qualification_score' => 'float',
            'communication_score' => 'float',
            'discipline_score' => 'float',
            'engagement_score' => 'float',
            'neglect_score' => 'float',
            'risk_score' => 'float',
            'behavior_score' => 'float',
            'conversion_score' => 'float',
            'pipeline_metrics' => 'array',
            'response_metrics' => 'array',
            'followup_metrics' => 'array',
            'qualification_metrics' => 'array',
            'communication_metrics' => 'array',
            'neglect_metrics' => 'array',
            'daily_performance' => 'array',
            'weekly_trends' => 'array',
            'coaching_cards' => 'array',
            'computed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
