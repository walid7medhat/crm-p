<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesIntelligenceSetting extends Model
{
    protected $fillable = [
        'max_leads_per_agent_per_day',
        'distribution_mode',
        'ai_mode',
        'require_attendance',
        'metrics_lookback_days',
        'round_robin_last_user_id',
        'automation_flags',
    ];

    protected $casts = [
        'require_attendance' => 'boolean',
        'automation_flags' => 'array',
    ];

    public static function current(): self
    {
        return static::query()->orderBy('id')->firstOrFail();
    }
}
