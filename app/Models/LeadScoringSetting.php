<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class LeadScoringSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'weights' => 'array',
        'thresholds' => 'array',
        'automation_flags' => 'array',
        'is_active' => 'boolean',
    ];

    public static function resolved(): array
    {
        $defaults = [
            'weights' => config('lead_scoring.weights', []),
            'thresholds' => config('lead_scoring.thresholds', []),
            'automation_flags' => config('lead_scoring.automation', []),
            'ai_mode' => config('lead_scoring.ai_mode', 'fallback'),
        ];

        if (!Schema::hasTable('lead_scoring_settings')) {
            return $defaults;
        }

        return Cache::remember('lead_scoring_settings_resolved_v1', 60, function () use ($defaults) {
            $row = self::query()->where('is_active', true)->latest('id')->first();
            if (!$row) {
                return $defaults;
            }

            return [
                'weights' => array_merge($defaults['weights'], (array) $row->weights),
                'thresholds' => array_merge($defaults['thresholds'], (array) $row->thresholds),
                'automation_flags' => array_merge($defaults['automation_flags'], (array) $row->automation_flags),
                'ai_mode' => $row->ai_mode ?: $defaults['ai_mode'],
            ];
        });
    }

    public static function clearResolvedCache(): void
    {
        Cache::forget('lead_scoring_settings_resolved_v1');
    }
}
