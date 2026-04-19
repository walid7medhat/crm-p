<?php

namespace App\Services\SalesIntelligence;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class SalesIntelligenceOrchestrator
{
    public const CACHE_OVERVIEW = 'sales_intelligence:overview:v1';

    public function __construct(
        protected AgentMetricAggregator $aggregator,
        protected AgentScoringEngine $scoring,
    ) {}

    public function recalculateAll(?array $userIds = null): int
    {
        $ids = $userIds ?? $this->aggregator->defaultScoredUserIds();
        $count = 0;
        foreach ($ids as $id) {
            $this->recalculateUser((int) $id);
            $count++;
        }
        Cache::forget(self::CACHE_OVERVIEW);

        return $count;
    }

    public function recalculateUser(int $userId): void
    {
        $metric = $this->aggregator->persistForUser($userId);
        $row = $metric->only([
            'conversion_rate',
            'avg_response_time',
            'revenue',
            'deals_won',
            'deals_lost',
            'activity_count',
            'follow_up_score',
            'closing_speed',
        ]);
        $this->scoring->persistScore($userId, $row);
        Cache::forget(self::CACHE_OVERVIEW);
        Cache::forget("sales_intelligence:preview:{$userId}");
    }
}
