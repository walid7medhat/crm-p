<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Support\Brand\Concerns\BrandOwned;

class LeadScoringSetting extends Model
{
    use BrandOwned;

    protected $guarded = [];

    protected $casts = [
        'weights' => 'array',
        'thresholds' => 'array',
        'automation_flags' => 'array',
        'is_active' => 'boolean',
    ];

    /** In-process copy: resolved() runs several times per lead (scoring, every lead save). */
    private static ?array $memo = null;

    private static int $memoAt = 0;

    private const MEMO_SECONDS = 60;

    public static function resolved(): array
    {
        if (self::$memo !== null && (time() - self::$memoAt) < self::MEMO_SECONDS) {
            return self::$memo;
        }

        $defaults = [
            'weights' => config('lead_scoring.weights', []),
            'thresholds' => config('lead_scoring.thresholds', []),
            'automation_flags' => config('lead_scoring.automation', []),
            'ai_mode' => config('lead_scoring.ai_mode', 'fallback'),
        ];

        self::$memo = self::loadResolved($defaults);
        self::$memoAt = time();

        return self::$memo;
    }

    private static function loadResolved(array $defaults): array
    {
        // The table check is an information_schema query: keep it inside the cache
        // so it runs once a minute, not on every call.
        return Cache::remember('lead_scoring_settings_resolved_v1', 60, function () use ($defaults) {
            if (!Schema::hasTable('lead_scoring_settings')) {
                return $defaults;
            }

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
        self::$memo = null;
    }
}
