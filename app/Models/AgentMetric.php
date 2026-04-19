<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentMetric extends Model
{
    protected $fillable = [
        'user_id',
        'conversion_rate',
        'avg_response_time',
        'revenue',
        'deals_won',
        'deals_lost',
        'activity_count',
        'follow_up_score',
        'closing_speed',
        'computed_at',
    ];

    protected $casts = [
        'conversion_rate' => 'float',
        'avg_response_time' => 'float',
        'revenue' => 'float',
        'follow_up_score' => 'float',
        'closing_speed' => 'float',
        'computed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
