<?php

namespace App\Services\AiSalesIntelligence\Repositories;

use App\Services\AiSalesIntelligence\AiStageCatalog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AiLeadBehaviorRepository
{
    public function __construct(protected AiStageCatalog $stages) {}

    public function leadsForAgent(int $userId): Collection
    {
        return DB::table('leads as l')
            ->leftJoin('stages as s', 's.id', '=', 'l.stage_id')
            ->where('l.responsible_person_id', $userId)
            ->select([
                'l.id',
                'l.lead_name',
                'l.lead_number',
                'l.stage_id',
                'l.responsible_person_id',
                'l.interaction_result',
                'l.first_contacted_at',
                'l.last_stage_change_at',
                'l.available_date',
                'l.revert',
                'l.converted_to_deal_id',
                'l.created_at',
                'l.updated_at',
                's.order as stage_order',
                's.name as stage_name',
            ])
            ->get();
    }

    /**
     * @return array<int, Carbon>
     */
    public function assignmentTimesForAgent(int $userId, Carbon $since): array
    {
        $rows = DB::table('lead_histories as lh')
            ->join('leads as l', 'l.id', '=', 'lh.lead_id')
            ->where('lh.user_id', $userId)
            ->where('lh.created_at', '>=', $since)
            ->whereNull('lh.deleted_at')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(lh.changes, '$.action')) = 'assigned'")
            ->select('lh.lead_id', DB::raw('MIN(lh.created_at) as assigned_at'))
            ->groupBy('lh.lead_id')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->lead_id] = Carbon::parse($row->assigned_at);
        }

        $fallback = DB::table('leads')
            ->where('responsible_person_id', $userId)
            ->where('created_at', '>=', $since)
            ->select('id', 'created_at')
            ->get();

        foreach ($fallback as $lead) {
            $id = (int) $lead->id;
            if (!isset($map[$id])) {
                $map[$id] = Carbon::parse($lead->created_at);
            }
        }

        return $map;
    }

    public function stageMovements(int $userId, Carbon $since): Collection
    {
        return DB::table('lead_histories as lh')
            ->where('lh.user_id', $userId)
            ->where('lh.created_at', '>=', $since)
            ->whereNull('lh.deleted_at')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(lh.changes, '$.action')) = 'stage_changed'")
            ->select('lh.lead_id', 'lh.changes', 'lh.created_at')
            ->orderBy('lh.created_at')
            ->get();
    }

    public function commentCountByLead(int $userId, Carbon $since): Collection
    {
        return DB::table('lead_comments')
            ->where('user_id', $userId)
            ->where('created_at', '>=', $since)
            ->whereNull('deleted_at')
            ->select('lead_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('lead_id')
            ->get()
            ->keyBy('lead_id');
    }

    public function activityStats(int $userId, Carbon $since): object
    {
        return DB::selectOne(
            '
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN is_completed = 0 AND reminder_date < NOW() THEN 1 ELSE 0 END) AS overdue,
                SUM(CASE WHEN is_completed = 0 AND reminder_date IS NULL THEN 1 ELSE 0 END) AS no_date,
                SUM(CASE WHEN is_completed = 1 AND updated_at <= reminder_date THEN 1 ELSE 0 END) AS on_time,
                AVG(CASE WHEN is_completed = 1 THEN TIMESTAMPDIFF(MINUTE, reminder_date, updated_at) END) AS avg_delay_minutes
            FROM lead_activities
            WHERE user_id = ?
              AND created_at >= ?
              AND deleted_at IS NULL
            ',
            [$userId, $since->toDateTimeString()]
        ) ?? (object) [];
    }

    public function firstEventTimes(int $userId, array $leadIds): array
    {
        if ($leadIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($leadIds), '?'));

        $activities = DB::select(
            "SELECT lead_id, MIN(created_at) as first_at FROM lead_activities
             WHERE user_id = ? AND lead_id IN ({$placeholders}) AND deleted_at IS NULL GROUP BY lead_id",
            array_merge([$userId], $leadIds)
        );

        $comments = DB::select(
            "SELECT lead_id, MIN(created_at) as first_at FROM lead_comments
             WHERE user_id = ? AND lead_id IN ({$placeholders}) AND deleted_at IS NULL GROUP BY lead_id",
            array_merge([$userId], $leadIds)
        );

        $moves = DB::select(
            "SELECT lead_id, MIN(created_at) as first_at FROM lead_histories
             WHERE user_id = ? AND lead_id IN ({$placeholders}) AND deleted_at IS NULL
             AND JSON_UNQUOTE(JSON_EXTRACT(changes, '$.action')) = 'stage_changed'
             GROUP BY lead_id",
            array_merge([$userId], $leadIds)
        );

        $result = [];
        foreach ($leadIds as $id) {
            $result[$id] = ['activity' => null, 'comment' => null, 'stage_move' => null];
        }

        foreach ($activities as $row) {
            $result[(int) $row->lead_id]['activity'] = $row->first_at;
        }
        foreach ($comments as $row) {
            $result[(int) $row->lead_id]['comment'] = $row->first_at;
        }
        foreach ($moves as $row) {
            $result[(int) $row->lead_id]['stage_move'] = $row->first_at;
        }

        return $result;
    }

    public function conversionStats(int $userId, Carbon $since): object
    {
        return DB::selectOne(
            '
            SELECT
                SUM(CASE WHEN d.status = ? THEN 1 ELSE 0 END) AS won,
                SUM(CASE WHEN d.status = ? THEN 1 ELSE 0 END) AS lost,
                COALESCE(SUM(CASE WHEN d.status = ? THEN d.deal_total_amount ELSE 0 END), 0) AS revenue
            FROM deals d
            WHERE d.responsible_person_id = ?
              AND d.updated_at >= ?
            ',
            ['completed', 'cancelled', 'completed', $userId, $since->toDateTimeString()]
        ) ?? (object) ['won' => 0, 'lost' => 0, 'revenue' => 0];
    }
}
