<?php

namespace App\Models\AiSalesIntelligence;

use App\Support\Brand\BrandIsolation;
use App\Support\Brand\Concerns\BrandOwned;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AiSalesIntelligenceSetting extends Model
{
    use BrandOwned;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'response_sla_minutes' => 'array',
            'automation_flags' => 'array',
        ];
    }

    public static function current(): self
    {
        $cacheKey = 'ai_sales_intelligence:settings:'.BrandIsolation::cacheSegment();

        return Cache::remember($cacheKey, 300, function () {
            $row = self::query()->latest('id')->first();
            if ($row) {
                return $row;
            }

            return self::create([
                'metrics_lookback_days' => 90,
                'neglect_inactive_days' => 7,
                'stuck_follow_up_days' => 10,
                'response_sla_minutes' => [15, 30, 60, 120, 240, 1440],
                'automation_flags' => [
                    'recalculate_on_lead_change' => true,
                    'recalculate_on_comment' => true,
                    'recalculate_on_activity' => true,
                    'recalculate_on_deal_close' => true,
                ],
            ]);
        });
    }

    public static function clearCache(): void
    {
        Cache::forget('ai_sales_intelligence:settings:'.BrandIsolation::cacheSegment());
        // Legacy key (pre-isolation) — safe to forget on current brand clears.
        Cache::forget('ai_sales_intelligence:settings');
    }
}
