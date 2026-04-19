<?php

namespace App\Services\SalesIntelligence;

use App\Models\AgentMetric;
use App\Models\Deal;
use App\Models\SalesIntelligenceSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AgentMetricAggregator
{
    public function lookbackDays(): int
    {
        try {
            return max(7, (int) SalesIntelligenceSetting::current()->metrics_lookback_days);
        } catch (\Throwable) {
            return 90;
        }
    }

    public function since(): Carbon
    {
        return now()->subDays($this->lookbackDays());
    }

    /**
     * @return array<string, float|int|null>
     */
    public function computeForUser(int $userId): array
    {
        $since = $this->since();

        $won = Deal::query()
            ->where('responsible_person_id', $userId)
            ->where('status', 'completed')
            ->where(function ($q) use ($since) {
                $q->where('updated_at', '>=', $since)->orWhere('created_at', '>=', $since);
            })
            ->count();

        $lost = Deal::query()
            ->where('responsible_person_id', $userId)
            ->where('status', 'cancelled')
            ->where(function ($q) use ($since) {
                $q->where('updated_at', '>=', $since)->orWhere('created_at', '>=', $since);
            })
            ->count();

        $closed = $won + $lost;
        $conversionRate = $closed > 0 ? round(100 * $won / $closed, 4) : 0.0;

        $revenue = (float) Deal::query()
            ->where('responsible_person_id', $userId)
            ->where('status', 'completed')
            ->where(function ($q) use ($since) {
                $q->where('updated_at', '>=', $since)->orWhere('created_at', '>=', $since);
            })
            ->sum('deal_total_amount');

        $avgResponseHours = $this->averageFirstResponseHours($userId, $since);

        $activityCount = (int) DB::table('lead_activities')
            ->where('user_id', $userId)
            ->where('created_at', '>=', $since)
            ->count();

        $followUpScore = $this->followUpQualityScore($userId, $since);

        $closingSpeedDays = $this->averageClosingDays($userId, $since);

        return [
            'user_id' => $userId,
            'conversion_rate' => $conversionRate,
            'avg_response_time' => $avgResponseHours,
            'revenue' => $revenue,
            'deals_won' => $won,
            'deals_lost' => $lost,
            'activity_count' => $activityCount,
            'follow_up_score' => $followUpScore,
            'closing_speed' => $closingSpeedDays,
        ];
    }

    public function persistForUser(int $userId): AgentMetric
    {
        $payload = $this->computeForUser($userId);
        unset($payload['user_id']);

        $payload['computed_at'] = now();

        return AgentMetric::query()->updateOrCreate(
            ['user_id' => $userId],
            $payload
        );
    }

    /**
     * All users that should participate in intelligence (sales-facing roles).
     *
     * @return list<int>
     */
    public function defaultScoredUserIds(): array
    {
        return User::query()
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereHas('roles', fn ($r) => $r->whereIn('name', ['sales', 'team_lead']))
                    ->orWhereHas('roles', fn ($r) => $r->where('name', 'manager'));
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    protected function averageFirstResponseHours(int $userId, Carbon $since): ?float
    {
        $row = DB::selectOne(
            '
            SELECT AVG(sub.minutes / 60.0) AS avg_hours
            FROM (
                SELECT MIN(TIMESTAMPDIFF(MINUTE, l.created_at, la.created_at)) AS minutes
                FROM leads l
                INNER JOIN lead_activities la ON la.lead_id = l.id AND la.user_id = l.responsible_person_id
                WHERE l.responsible_person_id = ?
                  AND l.created_at >= ?
                GROUP BY l.id
            ) AS sub
            ',
            [$userId, $since->toDateTimeString()]
        );

        $v = $row->avg_hours ?? null;

        return $v !== null ? round((float) $v, 4) : null;
    }

    protected function followUpQualityScore(int $userId, Carbon $since): float
    {
        $stats = DB::selectOne(
            '
            SELECT
                SUM(CASE WHEN la.is_completed = 1 AND la.updated_at <= la.reminder_date THEN 1 ELSE 0 END) AS on_time,
                SUM(CASE WHEN la.is_completed = 1 THEN 1 ELSE 0 END) AS completed,
                COUNT(*) AS total
            FROM lead_activities la
            INNER JOIN leads l ON l.id = la.lead_id
            WHERE la.user_id = ?
              AND la.created_at >= ?
              AND l.responsible_person_id = ?
            ',
            [$userId, $since->toDateTimeString(), $userId]
        );

        $total = (int) ($stats->total ?? 0);
        if ($total === 0) {
            return 50.0;
        }

        $completed = (int) ($stats->completed ?? 0);
        if ($completed === 0) {
            return 35.0;
        }

        $onTime = (int) ($stats->on_time ?? 0);

        return round(100 * $onTime / max(1, $completed), 4);
    }

    protected function averageClosingDays(int $userId, Carbon $since): ?float
    {
        $row = DB::selectOne(
            '
            SELECT AVG(DATEDIFF(d.updated_at, l.created_at)) AS avg_days
            FROM deals d
            INNER JOIN leads l ON l.id = d.lead_id
            WHERE d.responsible_person_id = ?
              AND d.status = ?
              AND d.updated_at >= ?
            ',
            [$userId, 'completed', $since->toDateTimeString()]
        );

        $v = $row->avg_days ?? null;

        return $v !== null ? round((float) $v, 4) : null;
    }
}
