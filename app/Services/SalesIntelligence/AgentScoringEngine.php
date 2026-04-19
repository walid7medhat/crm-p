<?php

namespace App\Services\SalesIntelligence;

use App\Models\AgentMetric;
use App\Models\AgentScore;
use App\Models\ScoringRule;
use Illuminate\Support\Facades\Cache;

class AgentScoringEngine
{
    public const CACHE_RULES_KEY = 'sales_intelligence:scoring_rules';

    public const CACHE_RULES_TTL = 600;

    /**
     * @param  array<string, float|int|null>  $metricRow  keyed like AgentMetric columns
     * @param  list<array<string, mixed>>|null  $draftRules  optional override for live preview
     * @return array{score: float, tier: string, breakdown: array<int, array<string, mixed>>}
     */
    public function scoreFromMetrics(array $metricRow, ?array $draftRules = null): array
    {
        $rules = $draftRules ?? $this->rulesCollection();

        $breakdown = [];
        $weighted = 0.0;
        $weightSum = 0.0;

        foreach ($rules as $rule) {
            $factor = (string) $rule['factor_name'];
            $weight = (float) $rule['weight'];
            $low = isset($rule['low_value']) ? (float) $rule['low_value'] : 0.0;
            $med = isset($rule['medium_value']) ? (float) $rule['medium_value'] : 0.0;
            $high = isset($rule['high_value']) ? (float) $rule['high_value'] : 0.0;
            $direction = (string) ($rule['direction'] ?? 'higher_better');

            $raw = $this->resolveRawMetric($factor, $metricRow);
            $factorScore = $this->normalizeFactorScore($raw, $low, $med, $high, $direction);

            $weighted += $factorScore * $weight;
            $weightSum += $weight;

            $breakdown[] = [
                'factor' => $factor,
                'weight' => $weight,
                'raw' => $raw,
                'normalized' => round($factorScore, 2),
                'direction' => $direction,
            ];
        }

        $score = $weightSum > 0 ? round($weighted / $weightSum, 2) : 0.0;
        $score = max(0, min(100, $score));

        $tier = $this->tierFromScore($score);

        return [
            'score' => $score,
            'tier' => $tier,
            'breakdown' => $breakdown,
        ];
    }

    public function persistScore(int $userId, array $metricRow, ?array $draftRules = null): AgentScore
    {
        $result = $this->scoreFromMetrics($metricRow, $draftRules);

        return AgentScore::query()->create([
            'user_id' => $userId,
            'score' => $result['score'],
            'tier' => $result['tier'],
            'breakdown' => $result['breakdown'],
            'calculated_at' => now(),
        ]);
    }

    public function forgetRulesCache(): void
    {
        Cache::forget(self::CACHE_RULES_KEY);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function rulesCollection(): array
    {
        return Cache::remember(self::CACHE_RULES_KEY, self::CACHE_RULES_TTL, function () {
            return ScoringRule::query()
                ->orderBy('id')
                ->get()
                ->map(fn (ScoringRule $r) => [
                    'factor_name' => $r->factor_name,
                    'weight' => (float) $r->weight,
                    'low_value' => $r->low_value,
                    'medium_value' => $r->medium_value,
                    'high_value' => $r->high_value,
                    'direction' => $r->direction,
                ])
                ->all();
        });
    }

    /**
     * @param  array<string, float|int|null>  $metricRow
     */
    protected function resolveRawMetric(string $factor, array $metricRow): ?float
    {
        return match ($factor) {
            'conversion_rate' => isset($metricRow['conversion_rate']) ? (float) $metricRow['conversion_rate'] : null,
            'response_speed' => isset($metricRow['avg_response_time']) ? (float) $metricRow['avg_response_time'] : null,
            'revenue_performance' => isset($metricRow['revenue']) ? (float) $metricRow['revenue'] : null,
            'activity_level' => isset($metricRow['activity_count']) ? (float) $metricRow['activity_count'] : null,
            'follow_up_discipline' => isset($metricRow['follow_up_score']) ? (float) $metricRow['follow_up_score'] : null,
            'closing_efficiency' => isset($metricRow['closing_speed']) ? (float) $metricRow['closing_speed'] : null,
            default => null,
        };
    }

    protected function normalizeFactorScore(?float $value, float $low, float $med, float $high, string $direction): float
    {
        if ($value === null) {
            return 45.0;
        }

        if ($direction === 'lower_better') {
            return $this->scoreLowerIsBetter($value, $low, $med, $high);
        }

        return $this->scoreHigherIsBetter($value, $low, $med, $high);
    }

    protected function scoreHigherIsBetter(float $v, float $low, float $med, float $high): float
    {
        if ($high <= $low) {
            return 55.0;
        }

        if ($v >= $high) {
            return 100.0;
        }
        if ($v >= $med) {
            return 60.0 + 40.0 * ($v - $med) / max($high - $med, 1e-6);
        }
        if ($v >= $low) {
            return 35.0 + 25.0 * ($v - $low) / max($med - $low, 1e-6);
        }

        return max(5.0, 35.0 * $v / max($low, 1e-6));
    }

    /**
     * Thresholds: low = worst (high numbers), high = best (low numbers).
     */
    protected function scoreLowerIsBetter(float $v, float $low, float $med, float $high): float
    {
        if ($v <= $high) {
            return 100.0;
        }
        if ($v <= $med) {
            return 60.0 + 40.0 * ($med - $v) / max($med - $high, 1e-6);
        }
        if ($v <= $low) {
            return 35.0 + 25.0 * ($low - $v) / max($low - $med, 1e-6);
        }

        return max(5.0, 35.0 - 10.0 * ($v - $low) / max($low, 1e-6));
    }

    protected function tierFromScore(float $score): string
    {
        if ($score >= 80) {
            return 'hot';
        }
        if ($score >= 50) {
            return 'warm';
        }

        return 'cold';
    }
}
