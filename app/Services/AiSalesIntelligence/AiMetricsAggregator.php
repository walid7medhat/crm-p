<?php

namespace App\Services\AiSalesIntelligence;

use App\Models\AiSalesIntelligence\AiSalesIntelligenceSetting;
use App\Services\AiSalesIntelligence\Repositories\AiLeadBehaviorRepository;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AiMetricsAggregator
{
    public function __construct(
        protected AiLeadBehaviorRepository $repository,
        protected AiStageCatalog $stages,
    ) {}

    public function lookbackDays(): int
    {
        try {
            return max(7, (int) AiSalesIntelligenceSetting::current()->metrics_lookback_days);
        } catch (\Throwable) {
            return 90;
        }
    }

    public function since(): Carbon
    {
        return now()->subDays($this->lookbackDays());
    }

    /**
     * @return array<string, mixed>
     */
    public function computeForUser(int $userId): array
    {
        $since = $this->since();
        $leads = $this->repository->leadsForAgent($userId);
        $assignments = $this->repository->assignmentTimesForAgent($userId, $since);
        $leadIds = array_keys($assignments);
        $firstEvents = $this->repository->firstEventTimes($userId, $leadIds);
        $stageMoves = $this->repository->stageMovements($userId, $since);
        $activityStats = $this->repository->activityStats($userId, $since);
        $conversion = $this->repository->conversionStats($userId, $since);
        $settings = AiSalesIntelligenceSetting::current();

        $pipeline = $this->pipelineDiscipline($leads, $stageMoves);
        $response = $this->assignmentResponse($assignments, $firstEvents, $leads, $settings);
        $followup = $this->followUpDiscipline($leads, $activityStats, $userId, $since);
        $qualification = $this->qualificationQuality($leads, $since);
        $communication = $this->communicationQuality($leads, $userId, $since);
        $neglect = $this->neglectDetection($leads, $assignments, $firstEvents, $settings);
        $daily = $this->dailyPerformance($userId);
        $weekly = $this->weeklyTrends($userId);

        return [
            'user_id' => $userId,
            'pipeline_metrics' => $pipeline,
            'response_metrics' => $response,
            'followup_metrics' => $followup,
            'qualification_metrics' => $qualification,
            'communication_metrics' => $communication,
            'neglect_metrics' => $neglect,
            'daily_performance' => $daily,
            'weekly_trends' => $weekly,
            'conversion' => [
                'deals_won' => (int) ($conversion->won ?? 0),
                'deals_lost' => (int) ($conversion->lost ?? 0),
                'revenue' => (float) ($conversion->revenue ?? 0),
                'conversion_rate' => $this->conversionRate((int) ($conversion->won ?? 0), (int) ($conversion->lost ?? 0)),
            ],
        ];
    }

    /**
     * @param  Collection<int, object>  $leads
     * @return array<string, mixed>
     */
    protected function pipelineDiscipline(Collection $leads, Collection $stageMoves): array
    {
        $byOrder = fn (int $order) => $leads->where('stage_order', $order)->count();

        $forward = 0;
        $backward = 0;
        $stageDurations = [];

        foreach ($stageMoves as $move) {
            $changes = json_decode($move->changes, true) ?: [];
            $oldId = (int) ($changes['old_stage_id'] ?? 0);
            $newId = (int) ($changes['new_stage_id'] ?? 0);
            $oldOrder = $this->stages->orderForStageId($oldId) ?? 0;
            $newOrder = $this->stages->orderForStageId($newId) ?? 0;
            if ($newOrder > $oldOrder) {
                $forward++;
            } elseif ($newOrder < $oldOrder && $oldOrder > 0) {
                $backward++;
            }
        }

        $totalMoves = max(1, $forward + $backward);
        $stuckDays = (int) AiSalesIntelligenceSetting::current()->stuck_follow_up_days;
        $stuck = $leads->filter(function ($lead) use ($stuckDays) {
            if ((int) $lead->stage_order !== 3) {
                return false;
            }
            $changed = $lead->last_stage_change_at ? Carbon::parse($lead->last_stage_change_at) : null;

            return $changed && $changed->diffInDays(now()) >= $stuckDays;
        })->count();

        $inactiveQualified = $leads->filter(function ($lead) {
            if ((int) $lead->stage_order !== 4) {
                return false;
            }

            return Carbon::parse($lead->updated_at)->diffInDays(now()) >= 7;
        })->count();

        $cleanliness = 100;
        $cleanliness -= min(40, $stuck * 4);
        $cleanliness -= min(30, $inactiveQualified * 3);
        $cleanliness -= min(20, $backward * 2);
        $cleanliness = max(0, round($cleanliness, 2));

        return [
            'assigned_leads' => $leads->count(),
            'active_leads' => $leads->filter(fn ($l) => !in_array((int) $l->stage_order, [6, 8, 9, 10], true))->count(),
            'qualified_leads' => $byOrder(4),
            'future_leads' => $byOrder(5),
            'lost_leads' => $byOrder(8),
            'lead_pool_leads' => $byOrder(9),
            'unqualified_leads' => $byOrder(10),
            'converted_leads' => $byOrder(6),
            'avg_days_per_stage' => $stageDurations,
            'forward_movement_rate' => round(100 * $forward / $totalMoves, 2),
            'backward_movement_rate' => round(100 * $backward / $totalMoves, 2),
            'stuck_leads' => $stuck,
            'inactive_qualified' => $inactiveQualified,
            'pipeline_cleanliness_score' => $cleanliness,
        ];
    }

    /**
     * @param  array<int, Carbon>  $assignments
     * @param  array<int, array<string, mixed>>  $firstEvents
     * @param  Collection<int, object>  $leads
     * @return array<string, mixed>
     */
    protected function assignmentResponse(array $assignments, array $firstEvents, Collection $leads, AiSalesIntelligenceSetting $settings): array
    {
        $slaMinutes = $settings->response_sla_minutes ?? [15, 30, 60, 120, 240, 1440];
        $slaFlags = array_fill_keys(array_map('strval', $slaMinutes), 0);

        $activityMinutes = [];
        $commentMinutes = [];
        $moveMinutes = [];
        $contactMinutes = [];

        foreach ($assignments as $leadId => $assignedAt) {
            $events = $firstEvents[$leadId] ?? [];
            foreach (['activity' => &$activityMinutes, 'comment' => &$commentMinutes, 'stage_move' => &$moveMinutes] as $key => &$bucket) {
                if (!empty($events[$key])) {
                    $mins = $assignedAt->diffInMinutes(Carbon::parse($events[$key]));
                    $bucket[] = $mins;
                    foreach ($slaMinutes as $sla) {
                        if ($key === 'activity' && $mins > $sla) {
                            $slaFlags[(string) $sla]++;
                        }
                    }
                }
            }
            unset($bucket);

            $lead = $leads->firstWhere('id', $leadId);
            if ($lead && $lead->first_contacted_at) {
                $contactMinutes[] = $assignedAt->diffInMinutes(Carbon::parse($lead->first_contacted_at));
            }
        }

        $avg = fn (array $vals) => $vals === [] ? null : round(array_sum($vals) / count($vals), 2);

        $notContacted = collect($assignments)->filter(function ($assignedAt, $leadId) use ($firstEvents, $leads) {
            $events = $firstEvents[$leadId] ?? [];
            $lead = $leads->firstWhere('id', $leadId);

            return empty($events['activity']) && empty($events['comment']) && empty($lead?->first_contacted_at);
        })->count();

        return [
            'avg_minutes_to_first_activity' => $avg($activityMinutes),
            'avg_minutes_to_first_comment' => $avg($commentMinutes),
            'avg_minutes_to_first_stage_move' => $avg($moveMinutes),
            'avg_minutes_to_first_contact' => $avg($contactMinutes),
            'sla_breaches' => $slaFlags,
            'not_contacted_count' => $notContacted,
            'assignments_in_window' => count($assignments),
        ];
    }

    /**
     * @param  Collection<int, object>  $leads
     * @return array<string, mixed>
     */
    protected function followUpDiscipline(Collection $leads, object $activityStats, int $userId, Carbon $since): array
    {
        $total = (int) ($activityStats->total ?? 0);
        $completed = (int) ($activityStats->completed ?? 0);
        $overdue = (int) ($activityStats->overdue ?? 0);
        $onTime = (int) ($activityStats->on_time ?? 0);
        $avgDelay = $activityStats->avg_delay_minutes ?? null;

        $noFutureFollowUp = $leads->filter(function ($lead) use ($userId) {
            if (in_array((int) $lead->stage_order, [6, 8, 9, 10], true)) {
                return false;
            }

            return !DB::table('lead_activities')
                ->where('lead_id', $lead->id)
                ->where('user_id', $userId)
                ->where('is_completed', false)
                ->whereNull('deleted_at')
                ->exists();
        })->count();

        $abandonedNoAnswer = $leads->filter(function ($lead) use ($userId) {
            if ($lead->interaction_result !== 'no_answer') {
                return false;
            }

            return !DB::table('lead_activities')
                ->where('lead_id', $lead->id)
                ->where('user_id', $userId)
                ->where('created_at', '>', $lead->updated_at)
                ->whereNull('deleted_at')
                ->exists();
        })->count();

        $noActivityAfterAssignment = $leads->filter(function ($lead) use ($userId) {
            return !DB::table('lead_activities')
                ->where('lead_id', $lead->id)
                ->where('user_id', $userId)
                ->whereNull('deleted_at')
                ->exists();
        })->count();

        return [
            'followups_created' => $total,
            'followups_completed' => $completed,
            'overdue_followups' => $overdue,
            'missed_followups' => max(0, $overdue - $completed),
            'avg_followup_delay_minutes' => $avgDelay !== null ? round((float) $avgDelay, 2) : null,
            'reminder_completion_rate' => $completed > 0 ? round(100 * $onTime / $completed, 2) : null,
            'leads_without_future_followup' => $noFutureFollowUp,
            'abandoned_after_no_answer' => $abandonedNoAnswer,
            'leads_no_activity_after_assignment' => $noActivityAfterAssignment,
        ];
    }

    /**
     * @param  Collection<int, object>  $leads
     * @return array<string, mixed>
     */
    protected function qualificationQuality(Collection $leads, Carbon $since): array
    {
        $assigned = $leads->filter(fn ($l) => $l->created_at >= $since || $l->updated_at >= $since);
        $qualified = $assigned->where('stage_order', '>=', 4)->where('stage_order', '!=', 10);
        $qualifiedCount = $qualified->count();
        $assignedCount = max(1, $assigned->count());

        $qualifiedToDeal = $qualified->filter(fn ($l) => !empty($l->converted_to_deal_id))->count();
        $qualifiedToLost = $qualified->where('stage_order', 8)->count();

        $qualifiedWithoutComment = $qualified->filter(fn ($l) => !DB::table('lead_comments')
            ->where('lead_id', $l->id)->whereNull('deleted_at')->exists())->count();

        $qualifiedWithoutFollowup = $qualified->filter(fn ($l) => !DB::table('lead_activities')
            ->where('lead_id', $l->id)->whereNull('deleted_at')->exists())->count();

        $qualifiedInactive = $qualified->filter(fn ($l) => Carbon::parse($l->updated_at)->diffInDays(now()) >= 7)->count();

        return [
            'qualified_rate' => round(100 * $qualifiedCount / $assignedCount, 2),
            'assigned_to_qualified_rate' => round(100 * $qualifiedCount / $assignedCount, 2),
            'qualified_to_deal_rate' => $qualifiedCount > 0 ? round(100 * $qualifiedToDeal / $qualifiedCount, 2) : 0,
            'qualified_to_lost_rate' => $qualifiedCount > 0 ? round(100 * $qualifiedToLost / $qualifiedCount, 2) : 0,
            'avg_qualification_days' => null,
            'qualified_without_comment' => $qualifiedWithoutComment,
            'qualified_without_followup' => $qualifiedWithoutFollowup,
            'qualified_then_inactive' => $qualifiedInactive,
        ];
    }

    /**
     * @param  Collection<int, object>  $leads
     * @return array<string, mixed>
     */
    protected function communicationQuality(Collection $leads, int $userId, Carbon $since): array
    {
        $leadCount = max(1, $leads->count());
        $comments = (int) DB::table('lead_comments')
            ->where('user_id', $userId)
            ->where('created_at', '>=', $since)
            ->whereNull('deleted_at')
            ->count();
        $activities = (int) DB::table('lead_activities')
            ->where('user_id', $userId)
            ->where('created_at', '>=', $since)
            ->whereNull('deleted_at')
            ->count();

        $answered = $leads->where('interaction_result', 'answered')->count();
        $noAnswer = $leads->where('interaction_result', 'no_answer')->count();
        $interactionTotal = max(1, $answered + $noAnswer);

        $zeroComments = $leads->filter(fn ($l) => !DB::table('lead_comments')
            ->where('lead_id', $l->id)->whereNull('deleted_at')->exists())->count();

        $noActivity = $leads->filter(fn ($l) => !DB::table('lead_activities')
            ->where('lead_id', $l->id)->whereNull('deleted_at')->exists())->count();

        return [
            'comments_per_lead' => round($comments / $leadCount, 2),
            'activities_per_lead' => round($activities / $leadCount, 2),
            'answered_rate' => round(100 * $answered / $interactionTotal, 2),
            'no_answer_rate' => round(100 * $noAnswer / $interactionTotal, 2),
            'leads_with_zero_comments' => $zeroComments,
            'leads_with_no_activity' => $noActivity,
            'long_silent_periods' => $leads->filter(fn ($l) => Carbon::parse($l->updated_at)->diffInDays(now()) >= 14)->count(),
        ];
    }

    /**
     * @param  array<int, Carbon>  $assignments
     * @param  array<int, array<string, mixed>>  $firstEvents
     * @param  Collection<int, object>  $leads
     * @return array<string, mixed>
     */
    protected function neglectDetection(Collection $leads, array $assignments, array $firstEvents, AiSalesIntelligenceSetting $settings): array
    {
        $inactiveDays = (int) $settings->neglect_inactive_days;
        $items = [];

        foreach ($leads as $lead) {
            $leadId = (int) $lead->id;
            $events = $firstEvents[$leadId] ?? [];
            $reasons = [];

            if (isset($assignments[$leadId])) {
                if (empty($events['activity']) && empty($events['comment']) && empty($lead->first_contacted_at)) {
                    $reasons[] = 'untouched';
                }
                if (empty($events['comment'])) {
                    $reasons[] = 'no_comment';
                }
                if (empty($events['activity'])) {
                    $reasons[] = 'no_activity';
                }
                if (empty($events['stage_move'])) {
                    $reasons[] = 'no_stage_movement';
                }
            }

            $hasFutureFollowup = DB::table('lead_activities')
                ->where('lead_id', $leadId)
                ->where('is_completed', false)
                ->where('reminder_date', '>', now())
                ->whereNull('deleted_at')
                ->exists();
            if (!$hasFutureFollowup && !in_array((int) $lead->stage_order, [6, 8, 9, 10], true)) {
                $reasons[] = 'no_followup_scheduled';
            }

            if (Carbon::parse($lead->updated_at)->diffInDays(now()) >= $inactiveDays) {
                $reasons[] = 'inactive';
            }

            if ((int) $lead->stage_order === 4 && Carbon::parse($lead->updated_at)->diffInDays(now()) >= $inactiveDays) {
                $reasons[] = 'inactive_qualified';
            }

            if ((int) $lead->stage_order === 5 && $lead->available_date && Carbon::parse($lead->available_date)->isPast()) {
                $reasons[] = 'future_expired';
            }

            if ($lead->revert) {
                $reasons[] = 'reverted_inactivity';
            }

            if ($reasons !== []) {
                $items[] = [
                    'lead_id' => $leadId,
                    'lead_name' => $lead->lead_name,
                    'lead_number' => $lead->lead_number,
                    'stage_order' => (int) $lead->stage_order,
                    'stage_name' => $lead->stage_name,
                    'reasons' => array_values(array_unique($reasons)),
                    'updated_at' => $lead->updated_at,
                ];
            }
        }

        $grouped = [
            'needs_contact' => [],
            'needs_followup' => [],
            'needs_qualification' => [],
            'inactive' => [],
            'overdue' => [],
            'future_expired' => [],
            'lost_recently' => [],
        ];

        foreach ($items as $item) {
            if (in_array('untouched', $item['reasons'], true) || in_array('no_comment', $item['reasons'], true)) {
                $grouped['needs_contact'][] = $item;
            }
            if (in_array('no_followup_scheduled', $item['reasons'], true)) {
                $grouped['needs_followup'][] = $item;
            }
            if ((int) $item['stage_order'] === 3) {
                $grouped['needs_qualification'][] = $item;
            }
            if (in_array('inactive', $item['reasons'], true) || in_array('inactive_qualified', $item['reasons'], true)) {
                $grouped['inactive'][] = $item;
            }
            if (in_array('future_expired', $item['reasons'], true)) {
                $grouped['future_expired'][] = $item;
            }
            if ((int) $item['stage_order'] === 8) {
                $grouped['lost_recently'][] = $item;
            }
        }

        return [
            'neglected_leads' => $items,
            'neglect_count' => count($items),
            'drilldown' => $grouped,
        ];
    }

  /** @return array<string, mixed> */
    protected function dailyPerformance(int $userId): array
    {
        $today = now()->startOfDay();

        $assignments = DB::table('lead_histories')
            ->where('user_id', $userId)
            ->where('created_at', '>=', $today)
            ->whereNull('deleted_at')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(changes, '$.action')) = 'assigned'")
            ->count();

        $comments = DB::table('lead_comments')
            ->where('user_id', $userId)->where('created_at', '>=', $today)->whereNull('deleted_at')->count();

        $activities = DB::table('lead_activities')
            ->where('user_id', $userId)->where('created_at', '>=', $today)->whereNull('deleted_at')->count();

        $completed = DB::table('lead_activities')
            ->where('user_id', $userId)->where('is_completed', true)
            ->where('updated_at', '>=', $today)->whereNull('deleted_at')->count();

        $qualified = DB::table('lead_histories as lh')
            ->join('stages as s', 's.id', '=', DB::raw("CAST(JSON_UNQUOTE(JSON_EXTRACT(lh.changes, '$.new_stage_id')) AS UNSIGNED)"))
            ->where('lh.user_id', $userId)
            ->where('lh.created_at', '>=', $today)
            ->where('s.order', 4)
            ->whereNull('lh.deleted_at')
            ->count();

        $converted = DB::table('lead_histories as lh')
            ->join('stages as s', 's.id', '=', DB::raw("CAST(JSON_UNQUOTE(JSON_EXTRACT(lh.changes, '$.new_stage_id')) AS UNSIGNED)"))
            ->where('lh.user_id', $userId)
            ->where('lh.created_at', '>=', $today)
            ->where('s.order', 6)
            ->whereNull('lh.deleted_at')
            ->count();

        $lost = DB::table('lead_histories as lh')
            ->join('stages as s', 's.id', '=', DB::raw("CAST(JSON_UNQUOTE(JSON_EXTRACT(lh.changes, '$.new_stage_id')) AS UNSIGNED)"))
            ->where('lh.user_id', $userId)
            ->where('lh.created_at', '>=', $today)
            ->where('s.order', 8)
            ->whereNull('lh.deleted_at')
            ->count();

        $contacts = DB::table('leads')
            ->where('responsible_person_id', $userId)
            ->where('first_contacted_at', '>=', $today)
            ->count();

        return [
            'assignments' => $assignments,
            'contacts' => $contacts,
            'comments' => $comments,
            'follow_ups_created' => $activities,
            'reminders_completed' => $completed,
            'qualified' => $qualified,
            'converted' => $converted,
            'lost' => $lost,
            'response_time_today' => null,
        ];
    }

  /** @return array<string, mixed> */
    protected function weeklyTrends(int $userId): array
    {
        $weeks = [];
        for ($i = 3; $i >= 0; $i--) {
            $start = now()->subWeeks($i)->startOfWeek();
            $end = now()->subWeeks($i)->endOfWeek();
            $comments = DB::table('lead_comments')
                ->where('user_id', $userId)
                ->whereBetween('created_at', [$start, $end])
                ->whereNull('deleted_at')
                ->count();
            $activities = DB::table('lead_activities')
                ->where('user_id', $userId)
                ->whereBetween('created_at', [$start, $end])
                ->whereNull('deleted_at')
                ->count();
            $completed = DB::table('lead_activities')
                ->where('user_id', $userId)
                ->where('is_completed', true)
                ->whereBetween('updated_at', [$start, $end])
                ->whereNull('deleted_at')
                ->count();

            $weeks[] = [
                'week' => $start->toDateString(),
                'comments' => $comments,
                'activities' => $activities,
                'followups_completed' => $completed,
            ];
        }

        return ['weeks' => $weeks];
    }

    protected function conversionRate(int $won, int $lost): float
    {
        $total = $won + $lost;

        return $total > 0 ? round(100 * $won / $total, 2) : 0.0;
    }
}
