<?php

namespace App\Models\AiSalesIntelligence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AiSalesIntelligenceSetting extends Model
{
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
        return Cache::remember('ai_sales_intelligence:settings', 300, function () {
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
        Cache::forget('ai_sales_intelligence:settings');
    }
}
