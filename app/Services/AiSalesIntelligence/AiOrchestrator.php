<?php

namespace App\Services\AiSalesIntelligence;

use App\Models\AiSalesIntelligence\AiAgentAlert;
use App\Models\AiSalesIntelligence\AiAgentDailyStat;
use App\Models\AiSalesIntelligence\AiAgentMetric;
use App\Models\AiSalesIntelligence\AiAgentObservation;
use App\Models\AiSalesIntelligence\AiAgentScore;
use App\Models\AiSalesIntelligence\AiAgentSnapshot;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class AiOrchestrator
{
    public const CACHE_DASHBOARD = 'ai_sales_intelligence:dashboard:v1';

    public function __construct(
        protected AiMetricsAggregator $aggregator,
        protected AiScoringEngine $scoring,
        protected AiObservationEngine $observations,
        protected AiCoachingEngine $coaching,
        protected AiExecutiveSummaryEngine $executiveSummary,
        protected AiAlertEngine $alerts,
        protected AiRankingService $rankings,
        protected AiAgentUserResolver $userResolver,
    ) {}

    public function recalculateAll(?array $userIds = null): int
    {
        $ids = $userIds ?? $this->userResolver->scoredUserIds();
        foreach ($ids as $id) {
            $this->recalculateUser((int) $id);
        }

        $this->finalizeRankings($ids);

        return count($ids);
    }

    /**
     * @param  list<int>|null  $userIds
     */
    public function finalizeRankings(?array $userIds = null): void
    {
        $query = AiAgentMetric::query();
        if ($userIds !== null && $userIds !== []) {
            $query->whereIn('user_id', $userIds);
        }

        $this->rankings->persistRankings($query->get());
        Cache::forget(self::CACHE_DASHBOARD);
    }

    public function recalculateUser(int $userId): AiAgentMetric
    {
        $user = User::query()->findOrFail($userId);
        $metrics = $this->aggregator->computeForUser($userId);
        $scores = $this->scoring->score($metrics);

        $teamAvgResponse = $this->teamAverageResponse($userId);
        $obsList = $this->observations->generate($userId, $metrics, $scores, $teamAvgResponse);
        $coachingCards = $this->coaching->generate($metrics, $scores, $obsList);
        $summary = $this->executiveSummary->generate($user, $metrics, $scores, $obsList);
        $alertList = $this->alerts->generate($userId, $metrics, $scores);

        $payload = array_merge($scores, [
            'pipeline_metrics' => $metrics['pipeline_metrics'],
            'response_metrics' => $metrics['response_metrics'],
            'followup_metrics' => $metrics['followup_metrics'],
            'qualification_metrics' => $metrics['qualification_metrics'],
            'communication_metrics' => $metrics['communication_metrics'],
            'neglect_metrics' => $metrics['neglect_metrics'],
            'daily_performance' => $metrics['daily_performance'],
            'weekly_trends' => $metrics['weekly_trends'],
            'coaching_cards' => $coachingCards,
            'executive_summary' => $summary,
            'computed_at' => now(),
        ]);

        $metric = AiAgentMetric::query()->updateOrCreate(
            ['user_id' => $userId],
            $payload
        );

        AiAgentScore::query()->create([
            'user_id' => $userId,
            'overall_ai_score' => $scores['overall_ai_score'],
            'status' => $scores['status'],
            'risk_level' => $scores['risk_level'],
            'breakdown' => $scores,
            'calculated_at' => now(),
        ]);

        $this->persistObservations($userId, $obsList);
        $this->persistAlerts($userId, $alertList);
        $this->persistDailyStat($userId, $metrics['daily_performance'] ?? []);
        $this->persistSnapshot($userId, $metrics, $scores);

        Cache::forget(self::CACHE_DASHBOARD);

        return $metric;
    }

    /**
     * @param  list<array<string, mixed>>  $obsList
     */
    protected function persistObservations(int $userId, array $obsList): void
    {
        AiAgentObservation::query()
            ->where('user_id', $userId)
            ->where('generated_at', '>=', now()->subDay())
            ->delete();

        foreach ($obsList as $obs) {
            AiAgentObservation::query()->create([
                'user_id' => $userId,
                'category' => $obs['category'],
                'severity' => $obs['severity'],
                'observation' => $obs['observation'],
                'meta' => $obs['meta'] ?? null,
                'generated_at' => now(),
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $alertList
     */
    protected function persistAlerts(int $userId, array $alertList): void
    {
        foreach ($alertList as $alert) {
            $exists = AiAgentAlert::query()
                ->where('user_id', $userId)
                ->where('alert_type', $alert['alert_type'])
                ->where('is_read', false)
                ->where('created_at', '>=', now()->subDays(3))
                ->exists();

            if (!$exists) {
                AiAgentAlert::query()->create($alert);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $daily
     */
    protected function persistDailyStat(int $userId, array $daily): void
    {
        AiAgentDailyStat::query()->updateOrCreate(
            ['user_id' => $userId, 'stat_date' => now()->toDateString()],
            [
                'assignments' => (int) ($daily['assignments'] ?? 0),
                'contacts' => (int) ($daily['contacts'] ?? 0),
                'comments' => (int) ($daily['comments'] ?? 0),
                'follow_ups_created' => (int) ($daily['follow_ups_created'] ?? 0),
                'reminders_completed' => (int) ($daily['reminders_completed'] ?? 0),
                'qualified' => (int) ($daily['qualified'] ?? 0),
                'converted' => (int) ($daily['converted'] ?? 0),
                'lost' => (int) ($daily['lost'] ?? 0),
                'avg_response_minutes' => $daily['response_time_today'] ?? null,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @param  array<string, mixed>  $scores
     */
    protected function persistSnapshot(int $userId, array $metrics, array $scores): void
    {
        AiAgentSnapshot::query()->create([
            'user_id' => $userId,
            'snapshot_type' => 'daily',
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
            'payload' => ['metrics' => $metrics, 'scores' => $scores],
        ]);
    }

    protected function teamAverageResponse(int $excludeUserId): ?float
    {
        $values = AiAgentMetric::query()
            ->where('user_id', '!=', $excludeUserId)
            ->pluck('response_metrics')
            ->map(fn ($metrics) => is_array($metrics) ? ($metrics['avg_minutes_to_first_activity'] ?? null) : null)
            ->filter(fn ($v) => $v !== null && is_numeric($v))
            ->map(fn ($v) => (float) $v);

        if ($values->isEmpty()) {
            return null;
        }

        return round((float) $values->avg(), 2);
    }
}
