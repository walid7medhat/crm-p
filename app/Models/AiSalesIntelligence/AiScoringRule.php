<?php

namespace App\Models\AiSalesIntelligence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AiScoringRule extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'weight' => 'float',
            'thresholds' => 'array',
        ];
    }

    public static function resolved(): array
    {
        return Cache::remember('ai_sales_intelligence:scoring_rules', 300, function () {
            $rows = self::query()->orderBy('sort_order')->get();
            if ($rows->isEmpty()) {
                return self::defaultConfig();
            }

            return self::rowsToConfig($rows);
        });
    }

    public static function clearCache(): void
    {
        Cache::forget('ai_sales_intelligence:scoring_rules');
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultConfig(): array
    {
        return [
            'overall' => [
                'behavior' => 0.35,
                'pipeline' => 0.15,
                'followup' => 0.15,
                'qualification' => 0.10,
                'communication' => 0.10,
                'conversion' => 0.10,
                'neglect' => 0.05,
            ],
            'behavior' => [
                'response' => 0.20,
                'followup' => 0.20,
                'pipeline' => 0.15,
                'communication' => 0.15,
                'qualification' => 0.15,
                'neglect' => 0.15,
            ],
            'status' => [
                'excellent' => 85,
                'good' => 70,
                'needs_attention' => 50,
            ],
            'risk' => [
                'high' => 70,
                'medium' => 40,
            ],
            'response_sla' => [
                ['minutes' => 15, 'score' => 95],
                ['minutes' => 30, 'score' => 85],
                ['minutes' => 60, 'score' => 75],
                ['minutes' => 120, 'score' => 60],
                ['minutes' => 240, 'score' => 45],
                ['minutes' => 1440, 'score' => 30],
                ['minutes' => 99999, 'score' => 15],
            ],
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, self>  $rows
     * @return array<string, mixed>
     */
    public static function rowsToConfig($rows): array
    {
        $config = self::defaultConfig();
        $map = [
            'overall_behavior' => ['overall', 'behavior'],
            'overall_pipeline' => ['overall', 'pipeline'],
            'overall_followup' => ['overall', 'followup'],
            'overall_qualification' => ['overall', 'qualification'],
            'overall_communication' => ['overall', 'communication'],
            'overall_conversion' => ['overall', 'conversion'],
            'overall_neglect' => ['overall', 'neglect'],
            'behavior_response' => ['behavior', 'response'],
            'behavior_followup' => ['behavior', 'followup'],
            'behavior_pipeline' => ['behavior', 'pipeline'],
            'behavior_communication' => ['behavior', 'communication'],
            'behavior_qualification' => ['behavior', 'qualification'],
            'behavior_neglect' => ['behavior', 'neglect'],
            'status_excellent' => ['status', 'excellent'],
            'status_good' => ['status', 'good'],
            'status_needs_attention' => ['status', 'needs_attention'],
            'risk_high' => ['risk', 'high'],
            'risk_medium' => ['risk', 'medium'],
        ];

        foreach ($rows as $row) {
            if ($row->factor_key === 'response_sla' && is_array($row->thresholds)) {
                $config['response_sla'] = $row->thresholds;
                continue;
            }
            if (isset($map[$row->factor_key])) {
                [$group, $key] = $map[$row->factor_key];
                $config[$group][$key] = (float) $row->weight;
            }
        }

        return $config;
    }
}
