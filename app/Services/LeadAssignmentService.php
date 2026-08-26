<?php

namespace App\Services;

use App\Events\LeadAssignmentBroadcast;
use App\Events\LeadUpdated;
use App\Helpers\LeadHistoryHelper;
use App\Models\AssignmentPattern;
use App\Models\Attendance;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\LeadAssignmentLog;
use App\Models\LeadAssignmentSetting;
use App\Models\SalesPerformance;
use App\Models\SalesTemporalStat;
use App\Models\Stage;
use App\Models\User;
use App\Models\UserSkill;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeadAssignmentService
{
    /**
     * @return array<string, mixed>
     */
    protected function learningMaps(Lead $lead, Collection $candidates): array
    {
        $settings = LeadAssignmentSetting::current();
        $learning = app(LeadAssignmentLearningService::class);
        $fp = $learning->contextFingerprint($lead);
        $ids = $candidates->pluck('id')->filter()->values()->all();
        $globalPatternAvg = $learning->globalAverageSuccessForContext($fp);
        $patterns = $ids === []
            ? collect()
            : AssignmentPattern::query()
                ->where('context_fingerprint', $fp)
                ->whereIn('sales_id', $ids)
                ->get()
                ->keyBy('sales_id');

        $globalPerfAvg = 0.35;
        if ($ids !== []) {
            $avgSp = SalesPerformance::query()->whereIn('sales_id', $ids)->avg('score');
            if ($avgSp !== null) {
                $globalPerfAvg = max(0.05, min(1.0, (float) $avgSp / 100));
            }
        }

        $tz = (string) ($settings->working_hours['timezone'] ?? 'Asia/Dubai');
        $at = Carbon::now($tz);
        $hr = (int) $at->hour;
        $wd = (int) $at->dayOfWeek;
        $temporalBoost = [];
        if ($ids !== []) {
            $trows = SalesTemporalStat::query()
                ->whereIn('sales_id', $ids)
                ->where('hour', $hr)
                ->where('weekday', $wd)
                ->get()
                ->keyBy('sales_id');
            foreach ($ids as $sid) {
                $tr = $trows->get($sid);
                $temporalBoost[$sid] = $tr && (int) $tr->assignments_count > 0
                    ? min(0.065, ((int) $tr->wins_count / max(1, (int) $tr->assignments_count)) * 0.09)
                    : 0.0;
            }
        }

        $skillsByUser = $ids === []
            ? collect()
            : UserSkill::query()->whereIn('user_id', $ids)->get()->groupBy('user_id');

        return [
            'learning' => $learning,
            'patterns' => $patterns,
            'skillsByUser' => $skillsByUser,
            'context_fingerprint' => $fp,
            'global_pattern_avg' => $globalPatternAvg,
            'global_perf_avg' => $globalPerfAvg,
            'temporal_boost' => $temporalBoost,
            'cold_max_samples' => max(1, (int) ($settings->cold_start_max_samples ?? 8)),
        ];
    }

    /**
     * Monitoring snapshot for dashboards.
     *
     * @return array<string, mixed>
     */
    public function dashboardStats(): array
    {
        $tz = (string) (LeadAssignmentSetting::current()->working_hours['timezone'] ?? 'Asia/Dubai');
        $today = Carbon::now($tz)->toDateString();

        $logsToday = LeadAssignmentLog::query()
            ->whereDate('created_at', $today)
            ->get(['id', 'lead_id', 'assigned_to', 'created_at']);

        $totalAssignedToday = $logsToday->count();

        $avgAssignMinutes = null;
        if ($logsToday->isNotEmpty()) {
            $leadIds = $logsToday->pluck('lead_id')->unique()->filter()->values()->all();
            $created = Lead::query()->whereIn('id', $leadIds)->pluck('created_at', 'id');
            $deltas = $logsToday->map(function (LeadAssignmentLog $log) use ($created) {
                $lc = $created[$log->lead_id] ?? null;
                if (!$lc) {
                    return null;
                }

                return $lc->diffInMinutes($log->created_at);
            })->filter();

            $avgAssignMinutes = $deltas->isNotEmpty() ? round($deltas->avg(), 2) : null;
        }

        $topSales = LeadAssignmentLog::query()
            ->selectRaw('assigned_to, COUNT(*) as c')
            ->whereDate('created_at', $today)
            ->groupBy('assigned_to')
            ->orderByDesc('c')
            ->limit(8)
            ->get();

        $topPayload = $topSales->map(function ($row) {
            $u = User::query()->find($row->assigned_to);

            return [
                'user_id' => (int) $row->assigned_to,
                'name' => $u?->name,
                'assignments_today' => (int) $row->c,
            ];
        });

        $salesIds = User::query()->whereHas('roles', fn ($q) => $q->where('name', 'sales'))->pluck('id')->all();
        $terminal = $this->terminalStageIds();
        $loadQ = Lead::query()
            ->selectRaw('responsible_person_id as uid, COUNT(*) as c')
            ->whereIn('responsible_person_id', $salesIds);
        if ($terminal !== []) {
            $loadQ->whereNotIn('stage_id', $terminal);
        }
        $load = $loadQ->groupBy('responsible_person_id')->pluck('c', 'uid')->all();

        $loadDistribution = collect($salesIds)->map(function ($sid) use ($load) {
            $u = User::query()->find($sid);

            return [
                'user_id' => (int) $sid,
                'name' => $u?->name,
                'open_leads' => (int) ($load[$sid] ?? 0),
            ];
        })->sortByDesc('open_leads')->values()->take(20);

        return [
            'timezone' => $tz,
            'date' => $today,
            'total_assigned_today' => $totalAssignedToday,
            'avg_minutes_lead_create_to_assignment' => $avgAssignMinutes,
            'top_sales_today' => $topPayload,
            'load_distribution' => $loadDistribution,
        ];
    }

    public function refreshSalesPerformanceSnapshot(): void
    {
        $salesIds = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'sales'))
            ->pluck('id');

        foreach ($salesIds as $sid) {
            $sid = (int) $sid;

            $dealsClosed = Deal::query()
                ->where('responsible_person_id', $sid)
                ->where('status', 'completed')
                ->count();

            $dealsTotal = Deal::query()
                ->where('responsible_person_id', $sid)
                ->count();

            $cancelled = Deal::query()
                ->where('responsible_person_id', $sid)
                ->where('status', 'cancelled')
                ->count();

            $denom = max(1, $dealsClosed + $cancelled);
            $conversionRate = round(100 * ($dealsClosed / $denom), 2);

            $avgResponse = $this->averageFirstResponseMinutes($sid);

            $score = min(100, $dealsClosed * 4 + ($avgResponse !== null ? max(0, 40 - min(40, $avgResponse)) : 20) + min(20, $conversionRate * 0.2));

            SalesPerformance::query()->updateOrCreate(
                ['sales_id' => $sid],
                [
                    'deals_closed' => $dealsClosed,
                    'deals_total' => $dealsTotal,
                    'conversion_rate' => $conversionRate,
                    'response_time' => $avgResponse,
                    'score' => $score,
                ]
            );
        }
    }

    /**
     * @return array{assigned: int, skipped: int, errors: int, details: list<array<string, mixed>>}
     */
    public function assignQueuedLeads(bool $respectAutoGate = true, ?LeadAssignmentSetting $settings = null): array
    {
        $settings ??= LeadAssignmentSetting::current();
        if ($settings->system_disabled) {
            return ['assigned' => 0, 'skipped' => 0, 'errors' => 0, 'details' => [['message' => 'System disabled']]];
        }

        if ($respectAutoGate && !$settings->auto_assign) {
            return ['assigned' => 0, 'skipped' => 0, 'errors' => 0, 'details' => [['message' => 'Auto assign off']]];
        }

        $this->refreshSalesPerformanceSnapshot();

        $newStageId = $this->resolveNewStageId();
        $assignedStageId = $this->resolveAssignedStageId($settings);
        if (!$newStageId || !$assignedStageId) {
            return ['assigned' => 0, 'skipped' => 0, 'errors' => 1, 'details' => [['message' => 'Stages not configured']]];
        }

        $leadIds = Lead::query()
            ->where('stage_id', $newStageId)
            ->orderBy('id')
            ->pluck('id');

        $stats = ['assigned' => 0, 'skipped' => 0, 'errors' => 0, 'details' => []];

        $force = !$respectAutoGate;

        foreach ($leadIds as $leadId) {
            try {
                $log = $this->assignLeadById((int) $leadId, 'auto', $settings, $newStageId, $assignedStageId, $force);
                if ($log) {
                    $stats['assigned']++;
                    $stats['details'][] = ['lead_id' => $leadId, 'assigned_to' => $log->assigned_to];
                } else {
                    $stats['skipped']++;
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                $stats['details'][] = ['lead_id' => $leadId, 'error' => $e->getMessage()];
            }
        }

        return $stats;
    }

    public const REALTIME_BATCH_MAX_NORMAL = 10;

    public const REALTIME_BATCH_MAX_HIGH_LOAD = 25;

    public const REALTIME_HIGH_LOAD_QUEUE_THRESHOLD = 50;

    /**
     * Pending leads in the resolved "New" stage (for adaptive interval / metrics).
     */
    public function countPendingNewLeads(): int
    {
        $newStageId = $this->resolveNewStageId();
        if (!$newStageId) {
            return 0;
        }

        return (int) Lead::query()->where('stage_id', $newStageId)->count();
    }

    /**
     * Dynamic sleep between realtime ticks: large queue → fast loop, tiny queue → slow loop.
     */
    public static function adaptiveRealtimeSleepSeconds(int $queueDepth, int $configuredInterval): int
    {
        $configuredInterval = max(2, min(30, $configuredInterval));
        if ($queueDepth > 50) {
            return 1;
        }
        if ($queueDepth < 10) {
            return 5;
        }

        return $configuredInterval;
    }

    /**
     * @return array{batch_limit: int, high_load: bool}
     */
    protected function resolveRealtimeBatchPlan(int $queueDepth): array
    {
        $normal = self::REALTIME_BATCH_MAX_NORMAL;
        if ($queueDepth > self::REALTIME_HIGH_LOAD_QUEUE_THRESHOLD) {
            return [
                'batch_limit' => min(30, self::REALTIME_BATCH_MAX_HIGH_LOAD),
                'high_load' => true,
            ];
        }

        return ['batch_limit' => $normal, 'high_load' => false];
    }

    /**
     * New-stage queue: highest lead score first, then oldest (created_at, id).
     * Eligible sales: present + check-in, circular fair rotation via rotation index.
     *
     * @return array{
     *   assigned: int,
     *   skipped: int,
     *   errors: int,
     *   details: list<array<string, mixed>>,
     *   queue_depth?: int,
     *   batch_limit?: int,
     *   high_load?: bool,
     *   active_sales_count?: int,
     *   duration_ms?: float
     * }
     */
    public function runRealtimeAssignmentBatch(): array
    {
        $t0 = microtime(true);
        $empty = ['assigned' => 0, 'skipped' => 0, 'errors' => 0, 'details' => []];

        $lock = Cache::lock('lead-assignment:realtime-batch', 120);
        if (!$lock->get()) {
            return array_merge($empty, [
                'details' => [['message' => 'Realtime batch lock busy']],
                'duration_ms' => round((microtime(true) - $t0) * 1000, 2),
            ]);
        }

        try {
            $settings = LeadAssignmentSetting::current();
            if ($settings->system_disabled || !$settings->realtime_assignment_enabled) {
                return array_merge($empty, [
                    'details' => [['message' => 'Realtime assignment disabled']],
                    'duration_ms' => round((microtime(true) - $t0) * 1000, 2),
                ]);
            }

            if (!$this->isWithinWorkingHours($settings)) {
                return array_merge($empty, [
                    'details' => [['message' => 'Outside working hours']],
                    'duration_ms' => round((microtime(true) - $t0) * 1000, 2),
                ]);
            }

            $newStageId = $this->resolveNewStageId();
            $assignedStageId = $this->resolveAssignedStageId($settings);
            if (!$newStageId || !$assignedStageId) {
                return array_merge($empty, [
                    'errors' => 1,
                    'details' => [['message' => 'Stages not configured']],
                    'duration_ms' => round((microtime(true) - $t0) * 1000, 2),
                ]);
            }

            $queueDepth = (int) Lead::query()->where('stage_id', $newStageId)->count();
            $plan = $this->resolveRealtimeBatchPlan($queueDepth);
            $batchLimit = $plan['batch_limit'];
            $highLoad = $plan['high_load'];

            if ($highLoad) {
                Log::info('lead_assignment.realtime.high_load', [
                    'queue_depth' => $queueDepth,
                    'batch_limit' => $batchLimit,
                    'message' => 'High load mode: increased realtime batch size.',
                ]);
            }

            LeadAssignmentSetting::query()->whereKey($settings->id)->update(['realtime_status' => 'running']);

            $this->refreshSalesPerformanceSnapshot();

            $leadIds = Lead::query()
                ->where('stage_id', $newStageId)
                ->orderByRaw('CASE WHEN score IS NULL THEN 1 ELSE 0 END')
                ->orderByDesc('score')
                ->orderBy('created_at')
                ->orderBy('id')
                ->limit(max(1, min(50, $batchLimit)))
                ->pluck('id')
                ->all();

            if ($leadIds === []) {
                $durationMs = round((microtime(true) - $t0) * 1000, 2);
                $this->persistRealtimeBatchMetrics(
                    $settings->id,
                    $queueDepth,
                    $batchLimit,
                    $highLoad,
                    0,
                    0,
                    0,
                    0,
                    0,
                    $durationMs
                );

                return array_merge($empty, [
                    'queue_depth' => $queueDepth,
                    'batch_limit' => $batchLimit,
                    'high_load' => $highLoad,
                    'active_sales_count' => 0,
                    'duration_ms' => $durationMs,
                ]);
            }

            $attendanceByUserId = $this->todayAttendanceByUserId();
            $salesUsers = $this->baseSalesQuery()->get()->keyBy('id');
            $allSalesIds = $salesUsers->keys()->map(fn ($id) => (int) $id)->all();
            $openCounts = $this->openLeadCountsByUser($allSalesIds);

            $baseOrder = $this->realtimeEligibleSalesSortedByCheckIn($salesUsers, $attendanceByUserId);

            if ($baseOrder === []) {
                $durationMs = round((microtime(true) - $t0) * 1000, 2);
                $skippedAll = count($leadIds);
                $this->persistRealtimeBatchMetrics(
                    $settings->id,
                    $queueDepth,
                    $batchLimit,
                    $highLoad,
                    0,
                    0,
                    $skippedAll,
                    0,
                    $durationMs
                );

                return array_merge($empty, [
                    'details' => [['message' => 'No eligible sales (present today with check-in, not on vacation)']],
                    'queue_depth' => $queueDepth,
                    'batch_limit' => $batchLimit,
                    'high_load' => $highLoad,
                    'active_sales_count' => 0,
                    'duration_ms' => $durationMs,
                ]);
            }

            $activeSalesCount = count($baseOrder);
            $stats = ['assigned' => 0, 'skipped' => 0, 'errors' => 0, 'details' => []];

            foreach ($leadIds as $leadId) {
                try {
                    $log = DB::transaction(function () use (
                        $leadId,
                        $settings,
                        $newStageId,
                        $assignedStageId,
                        &$openCounts,
                        $baseOrder,
                        $salesUsers,
                        $attendanceByUserId,
                    ) {
                        /** @var LeadAssignmentSetting|null $lockedSettings */
                        $lockedSettings = LeadAssignmentSetting::query()->whereKey($settings->id)->lockForUpdate()->first();
                        if (!$lockedSettings || $lockedSettings->system_disabled || !$lockedSettings->realtime_assignment_enabled) {
                            return null;
                        }

                        /** @var Lead|null $lead */
                        $lead = Lead::query()->lockForUpdate()->find($leadId);
                        if (!$lead || (int) $lead->stage_id !== (int) $newStageId) {
                            return null;
                        }

                        $underCap = [];
                        foreach ($baseOrder as $uid) {
                            $uid = (int) $uid;
                            if (((int) ($openCounts[$uid] ?? 0)) < (int) $lockedSettings->max_leads_per_user) {
                                $underCap[] = $uid;
                            }
                        }

                        if ($underCap === []) {
                            return null;
                        }

                        $underCap = $this->applyPriorityFilter($underCap, $lockedSettings);

                        $n = count($underCap);
                        $slot = ((int) $lockedSettings->realtime_rotation_index) % $n;
                        $pickId = (int) $underCap[$slot];

                        /** @var User|null $agent */
                        $agent = $salesUsers->get($pickId);
                        if (!$agent) {
                            return null;
                        }

                        $lockedSettings->realtime_rotation_index = ((int) $lockedSettings->realtime_rotation_index) + 1;
                        $lockedSettings->save();

                        $ctx = $this->learningMaps($lead, collect([$agent]));
                        $oc = $this->openLeadCountsByUser([(int) $agent->id]);
                        [$wa, $wp, $wv, $wf] = $this->dynamicWeights($lead, $lockedSettings);
                        $winner = $this->computeAgentScoreBreakdown($agent, $lead, $lockedSettings, $attendanceByUserId, $oc, $wa, $wp, $wv, $wf, $ctx);
                        $winner['reason'] = sprintf(
                            'Realtime priority queue (score desc → oldest); circular rotation slot %d/%d. Assigned to %s. %s',
                            $slot + 1,
                            $n,
                            $agent->name,
                            $winner['reason']
                        );
                        $winner['explanation']['reason'] = sprintf(
                            '%s receives this lead via fair circular rotation among present reps (check-in order); higher-scored leads are processed first.',
                            $agent->name
                        );
                        $winner['was_exploration'] = false;
                        $winner['context_fingerprint'] = (string) ($ctx['context_fingerprint'] ?? '');
                        $this->attachPredictionMeta($winner, $lead, $lockedSettings, $ctx);

                        $oldPersonId = $lead->responsible_person_id;
                        $oldPerson = $lead->responsiblePerson;

                        $lead->update([
                            'responsible_person_id' => $agent->id,
                            'stage_id' => $assignedStageId,
                            'last_stage_change_at' => now(),
                            'assignment_hold' => false,
                            'assignment_hold_reason' => null,
                        ]);
                        $lead->refresh();

                        $changes = [
                            'action' => 'assigned',
                            'old_person_id' => $oldPersonId,
                            'old_person' => $oldPerson?->name,
                            'new_person' => $agent->name,
                        ];

                        LeadHistoryHelper::log($lead->id, [
                            'action' => 'assigned',
                            'old_person_id' => $oldPersonId,
                            'old_person' => $oldPerson?->name,
                            'new_person' => $agent->name,
                            'engine' => 'lead_assignment',
                            'reason' => $winner['reason'],
                        ]);

                        broadcast(new LeadUpdated($lead, 'assigned', null, $changes));

                        $log = $this->persistAssignmentLog($lead, $agent, $winner, 'realtime', $oldPersonId);

                        $openCounts[$pickId] = ((int) ($openCounts[$pickId] ?? 0)) + 1;

                        $this->touchAssigneeStats($agent);

                        broadcast(new LeadAssignmentBroadcast($lead, $agent->id, $oldPersonId, [
                            'type' => 'assigned',
                            'log_id' => $log->id,
                            'method' => 'realtime',
                            'explanation' => $winner['explanation'] ?? [],
                        ]));

                        return $log;
                    });

                    if ($log) {
                        $stats['assigned']++;
                        $stats['details'][] = ['lead_id' => $leadId, 'assigned_to' => $log->assigned_to];
                    } else {
                        $stats['skipped']++;
                    }
                } catch (\Throwable $e) {
                    $stats['errors']++;
                    $stats['details'][] = ['lead_id' => $leadId, 'error' => $e->getMessage()];
                }
            }

            $durationMs = round((microtime(true) - $t0) * 1000, 2);

            $this->persistRealtimeBatchMetrics(
                $settings->id,
                $queueDepth,
                $batchLimit,
                $highLoad,
                $activeSalesCount,
                $stats['assigned'],
                $stats['skipped'],
                $stats['errors'],
                $durationMs
            );

            return array_merge($stats, [
                'queue_depth' => $queueDepth,
                'batch_limit' => $batchLimit,
                'high_load' => $highLoad,
                'active_sales_count' => $activeSalesCount,
                'duration_ms' => $durationMs,
            ]);
        } finally {
            $lock->release();
        }
    }

    /**
     * Persist worker-visible health metrics after each realtime batch.
     */
    protected function persistRealtimeBatchMetrics(
        int $settingsId,
        int $queueDepth,
        int $batchLimit,
        bool $highLoad,
        int $activeSalesCount,
        int $assigned,
        int $skipped,
        int $errors,
        float $durationMs,
    ): void {
        $status = 'idle';
        if ($highLoad) {
            $status = 'high_load';
        } elseif ($assigned > 0) {
            $status = 'running';
        } elseif ($errors > 0) {
            $status = 'running';
        }

        LeadAssignmentSetting::query()->whereKey($settingsId)->update([
            'realtime_last_run_at' => now(),
            'realtime_status' => $status,
            'realtime_last_tick_assigned' => min(65535, $assigned),
            'realtime_last_tick_duration_ms' => (int) round($durationMs),
            'realtime_last_queue_depth' => $queueDepth,
            'realtime_active_sales_count' => min(65535, $activeSalesCount),
        ]);
    }

    /**
     * Attendance-only sequential round-robin for the New-stage queue (earliest check-in defines rotation order).
     *
     * @return array{assigned: int, skipped: int, errors: int, details: list<array<string, mixed>>, duration_ms?: float}
     */
    public function assignLeadsByAttendanceSimple(): array
    {
        $t0 = microtime(true);
        $empty = ['assigned' => 0, 'skipped' => 0, 'errors' => 0, 'details' => []];

        $lock = Cache::lock('lead-assignment:simple-batch', 120);
        if (!$lock->get()) {
            return array_merge($empty, ['details' => [['message' => 'Simple batch lock busy']], 'duration_ms' => round((microtime(true) - $t0) * 1000, 2)]);
        }

        try {
            $settings = LeadAssignmentSetting::current();
            if ($settings->system_disabled || !$settings->auto_assign || !$settings->simple_mode_enabled) {
                return array_merge($empty, ['duration_ms' => round((microtime(true) - $t0) * 1000, 2)]);
            }

            if (!$settings->simple_mode_enabled && !$this->isWithinWorkingHours($settings)) {
                return array_merge($empty, ['details' => [['message' => 'Outside working hours']], 'duration_ms' => round((microtime(true) - $t0) * 1000, 2)]);
            }

            $newStageId = $this->resolveNewStageId();
            $assignedStageId = $this->resolveAssignedStageId($settings);
            if (!$newStageId || !$assignedStageId) {
                return array_merge($empty, ['errors' => 1, 'details' => [['message' => 'Stages not configured']], 'duration_ms' => round((microtime(true) - $t0) * 1000, 2)]);
            }

            $queueDepth = (int) Lead::query()->where('stage_id', $newStageId)->count();

            LeadAssignmentSetting::query()->whereKey($settings->id)->update(['realtime_status' => 'running']);

            $batchSize = max(1, min(500, (int) ($settings->simple_mode_batch_size ?? 25)));

            $leadIds = Lead::query()
                ->where('stage_id', $newStageId)
                ->orderBy('id')
                ->limit($batchSize)
                ->pluck('id')
                ->all();

            $stats = ['assigned' => 0, 'skipped' => 0, 'errors' => 0, 'details' => []];

            foreach ($leadIds as $leadId) {
                try {
                    $log = DB::transaction(fn () => $this->performSimpleAttendanceAssignment((int) $leadId));
                    if ($log) {
                        $stats['assigned']++;
                        $stats['details'][] = ['lead_id' => $leadId, 'assigned_to' => $log->assigned_to];
                    } else {
                        $stats['skipped']++;
                    }
                } catch (\Throwable $e) {
                    $stats['errors']++;
                    $stats['details'][] = ['lead_id' => $leadId, 'error' => $e->getMessage()];
                }
            }

            $durationMs = round((microtime(true) - $t0) * 1000, 2);
            $attendanceByUserId = $this->todayAttendanceByUserId();
            $salesUsers = $this->baseSalesQuery()->get()->keyBy('id');
            $orderedEligible = $this->simpleEligibleSalesSortedByPresenceTime($salesUsers, $attendanceByUserId);

            LeadAssignmentSetting::query()->whereKey($settings->id)->update([
                'realtime_last_run_at' => now(),
                'realtime_status' => $stats['assigned'] > 0 ? 'running' : 'idle',
                'realtime_last_tick_assigned' => min(65535, $stats['assigned']),
                'realtime_last_tick_duration_ms' => (int) round($durationMs),
                'realtime_last_queue_depth' => $queueDepth,
                'realtime_active_sales_count' => min(65535, count($orderedEligible)),
            ]);

            return array_merge($stats, ['duration_ms' => $durationMs]);
        } finally {
            $lock->release();
        }
    }

    /**
     * Single-lead simple assignment (e.g. immediate dispatch after lead create).
     */
    public function assignSingleLeadSimple(int $leadId): void
    {
        $settings = LeadAssignmentSetting::current();
        if ($settings->system_disabled || !$settings->auto_assign || !$settings->simple_mode_enabled) {
            return;
        }

        if (!$settings->simple_mode_enabled && !$this->isWithinWorkingHours($settings)) {
            return;
        }

        try {
            DB::transaction(fn () => $this->performSimpleAttendanceAssignment($leadId));
        } catch (\Throwable) {
            // non-fatal — cron will retry
        }
    }

    /**
     * Core transaction: lock settings + lead, rotate across present sales under capacity.
     */
    protected function performSimpleAttendanceAssignment(int $leadId): ?LeadAssignmentLog
    {
        /** @var LeadAssignmentSetting|null $lockedSettings */
        $lockedSettings = LeadAssignmentSetting::query()->orderBy('id')->lockForUpdate()->first();
        if (!$lockedSettings || $lockedSettings->system_disabled || !$lockedSettings->auto_assign || !$lockedSettings->simple_mode_enabled) {
            return null;
        }

        $newStageId = $this->resolveNewStageId();
        $assignedStageId = $this->resolveAssignedStageId($lockedSettings);
        if (!$newStageId || !$assignedStageId) {
            return null;
        }

        /** @var Lead|null $lead */
        $lead = Lead::query()->lockForUpdate()->find($leadId);
        if (!$lead || (int) $lead->stage_id !== (int) $newStageId) {
            return null;
        }

        $attendanceByUserId = $this->todayAttendanceByUserId();
        $salesUsers = $this->baseSalesQuery()->get()->keyBy('id');
        $orderedIds = $this->simpleEligibleSalesSortedByPresenceTime($salesUsers, $attendanceByUserId);
        if ($orderedIds === []) {
            $lead->update([
                'assignment_hold' => true,
                'assignment_hold_reason' => 'simple_no_present_sales',
            ]);

            return null;
        }

        $allSalesIds = $salesUsers->keys()->map(fn ($id) => (int) $id)->all();
        $openCounts = $this->openLeadCountsByUser($allSalesIds);

        $underCap = [];
        foreach ($orderedIds as $uid) {
            $uid = (int) $uid;
            if (((int) ($openCounts[$uid] ?? 0)) < (int) $lockedSettings->max_leads_per_user) {
                $underCap[] = $uid;
            }
        }

        if ($underCap === []) {
            return null;
        }

        $underCap = $this->applyPriorityFilter($underCap, $lockedSettings);

        $n = count($underCap);
        $slot = ((int) $lockedSettings->simple_rotation_index) % $n;
        $pickId = (int) $underCap[$slot];

        /** @var User|null $agent */
        $agent = $salesUsers->get($pickId);
        if (!$agent) {
            return null;
        }

        $lockedSettings->simple_rotation_index = ((int) $lockedSettings->simple_rotation_index) + 1;

        $label = sprintf('%s → %s', $agent->name, $lead->lead_number ? '#'.$lead->lead_number : 'Lead #'.$lead->id);
        $lockedSettings->simple_last_assignment_label = $label;
        $lockedSettings->save();

        $winner = [
            'user' => $agent,
            'final_score' => 1.0,
            'attendance_score' => 1.0,
            'performance_score' => 0.0,
            'load_score' => 0.0,
            'fairness_score' => 0.0,
            'reason' => sprintf('Simple attendance mode: round-robin index slot %d/%d (earliest check-in order among present reps).', $slot + 1, $n),
            'explanation' => [
                'reason' => 'Assignment uses only today’s attendance (present + check-in), ordered by check-in time; reps rotate strictly in that order.',
            ],
            'was_exploration' => false,
            'dominant_factor' => 'attendance',
            'probability_of_close' => null,
            'context_fingerprint' => app(LeadAssignmentLearningService::class)->contextFingerprint($lead),
        ];

        $oldPersonId = $lead->responsible_person_id;
        $oldPerson = $lead->responsiblePerson;

        $lead->update([
            'responsible_person_id' => $agent->id,
            'stage_id' => $assignedStageId,
            'last_stage_change_at' => now(),
            'assignment_hold' => false,
            'assignment_hold_reason' => null,
        ]);
        $lead->refresh();

        $changes = [
            'action' => 'assigned',
            'old_person_id' => $oldPersonId,
            'old_person' => $oldPerson?->name,
            'new_person' => $agent->name,
        ];

        LeadHistoryHelper::log($lead->id, [
            'action' => 'assigned',
            'old_person_id' => $oldPersonId,
            'old_person' => $oldPerson?->name,
            'new_person' => $agent->name,
            'engine' => 'lead_assignment_simple',
            'reason' => $winner['reason'],
        ]);

        try {
            broadcast(new LeadUpdated($lead, 'assigned', null, $changes));
        } catch (\Throwable $e) {
            Log::warning('lead_assignment.simple.broadcast_failed', [
                'event' => 'LeadUpdated',
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
            ]);
        }

        $log = $this->persistAssignmentLog($lead, $agent, $winner, 'simple_attendance', $oldPersonId);

        $this->touchAssigneeStats($agent);

        try {
            broadcast(new LeadAssignmentBroadcast($lead, $agent->id, $oldPersonId, [
                'type' => 'assigned',
                'log_id' => $log->id,
                'method' => 'simple_attendance',
                'explanation' => $winner['explanation'] ?? [],
            ]));
        } catch (\Throwable $e) {
            Log::warning('lead_assignment.simple.broadcast_failed', [
                'event' => 'LeadAssignmentBroadcast',
                'lead_id' => $lead->id,
                'log_id' => $log->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $log;
    }

    /**
     * Present-only sales reps with a check-in timestamp today, earliest first (sales role, not on vacation).
     *
     * @param Collection<int, User> $salesUsers
     * @param array<int, array<string, mixed>> $attendanceByUserId
     * @return list<int>
     */
    protected function realtimeEligibleSalesSortedByCheckIn(Collection $salesUsers, array $attendanceByUserId): array
    {
        $rows = [];
        foreach ($salesUsers as $user) {
            if ($user->on_vacation) {
                continue;
            }
            $row = $attendanceByUserId[$user->id] ?? null;
            if (!$row) {
                continue;
            }
            if (strtolower((string) ($row['status'] ?? '')) !== 'present') {
                continue;
            }
            $checkIn = $row['check_in'] ?? null;
            if (!$checkIn) {
                continue;
            }
            try {
                $ts = Carbon::parse((string) $checkIn)->getTimestamp();
            } catch (\Throwable) {
                continue;
            }
            $rows[] = ['id' => (int) $user->id, 'ts' => $ts];
        }

        usort($rows, fn (array $a, array $b) => $a['ts'] <=> $b['ts']);

        return array_column($rows, 'id');
    }

    /**
     * SIMPLE MODE only:
     * - eligibility = attendance record marked present
     * - ordering = earliest timestamp first (check_in preferred; created_at fallback)
     * - no lead category/scoring/SLA bias, only fair rotation
     *
     * @param Collection<int, User> $salesUsers
     * @param array<int, array<string, mixed>> $attendanceByUserId
     * @return list<int>
     */
    protected function simpleEligibleSalesSortedByPresenceTime(Collection $salesUsers, array $attendanceByUserId): array
    {
        $rows = [];
        foreach ($salesUsers as $user) {
            if ($user->on_vacation) {
                continue;
            }
            $row = $attendanceByUserId[$user->id] ?? null;
            if (!$row || !$this->isSimpleModePresentAttendance($row)) {
                continue;
            }

            $time = $row['check_in'] ?? $row['created_at'] ?? null;
            if (!$time) {
                continue;
            }

            try {
                $ts = Carbon::parse((string) $time)->getTimestamp();
            } catch (\Throwable) {
                continue;
            }

            $rows[] = ['id' => (int) $user->id, 'ts' => $ts];
        }

        usort($rows, fn (array $a, array $b) => $a['ts'] <=> $b['ts']);

        return array_column($rows, 'id');
    }

    /**
     * SIMPLE MODE presence gate: presence=true or status indicates present.
     *
     * @param array<string, mixed> $row
     */
    protected function isSimpleModePresentAttendance(array $row): bool
    {
        if (array_key_exists('present', $row) && $row['present'] !== null) {
            return (bool) $row['present'] === true;
        }

        $status = strtolower(trim((string) ($row['status'] ?? '')));
        if ($status === '') {
            return !empty($row['check_in']);
        }

        return in_array($status, ['present', 'present_attendance', 'present-hybrid', 'hybrid_present'], true)
            || str_contains($status, 'present');
    }

    /**
     * Recover leads sitting in the assigned stage without updates (stale ownership).
     *
     * @return int Number of leads reassigned
     */
    public function recoverStuckLeads(?LeadAssignmentSetting $settings = null): int
    {
        $settings ??= LeadAssignmentSetting::current();
        if ($settings->system_disabled || !$settings->stuck_recovery_enabled) {
            return 0;
        }

        $assignedStageId = $this->resolveAssignedStageId($settings);
        if (!$assignedStageId) {
            return 0;
        }

        $minutes = max(15, (int) $settings->stuck_lead_minutes);
        $cutoff = now()->subMinutes($minutes);

        $ids = Lead::query()
            ->where('stage_id', $assignedStageId)
            ->where('updated_at', '<', $cutoff)
            ->orderBy('id')
            ->pluck('id');

        $count = 0;
        foreach ($ids as $leadId) {
            $log = $this->reassignStuckLeadById((int) $leadId, $settings, $assignedStageId);
            if ($log) {
                $count++;
            }
        }

        return $count;
    }

    public function reassignStuckLeadById(int $leadId, ?LeadAssignmentSetting $settings = null, ?int $assignedStageId = null): ?LeadAssignmentLog
    {
        $settings ??= LeadAssignmentSetting::current();
        $assignedStageId ??= $this->resolveAssignedStageId($settings);

        return DB::transaction(function () use ($leadId, $settings, $assignedStageId) {
            /** @var Lead|null $lead */
            $lead = Lead::query()->lockForUpdate()->find($leadId);
            if (!$lead || (int) $lead->stage_id !== (int) $assignedStageId) {
                return null;
            }

            if ($settings->system_disabled) {
                return null;
            }

            $this->refreshSalesPerformanceSnapshot();

            $winner = $this->pickBestAgent($lead, $settings, (int) $lead->responsible_person_id);
            if (!$winner) {
                return null;
            }

            /** @var User $agent */
            $agent = $winner['user'];
            if ($agent->id === (int) $lead->responsible_person_id) {
                return null;
            }

            $oldPersonId = $lead->responsible_person_id;
            $oldPerson = $lead->responsiblePerson;

            $lead->update([
                'responsible_person_id' => $agent->id,
                'last_stage_change_at' => now(),
            ]);
            $lead->refresh();

            $changes = [
                'action' => 'assigned',
                'old_person_id' => $oldPersonId,
                'old_person' => $oldPerson?->name,
                'new_person' => $agent->name,
            ];

            LeadHistoryHelper::log($lead->id, [
                'action' => 'auto_recovery',
                'old_person_id' => $oldPersonId,
                'old_person' => $oldPerson?->name,
                'new_person' => $agent->name,
                'engine' => 'lead_assignment',
                'reason' => $winner['reason'],
            ]);

            broadcast(new LeadUpdated($lead, 'assigned', null, $changes));

            $log = $this->persistAssignmentLog($lead, $agent, $winner, 'auto_recovery', $oldPersonId);

            $this->touchAssigneeStats($agent);
            broadcast(new LeadAssignmentBroadcast($lead, $agent->id, $oldPersonId, [
                'type' => 'auto_recovery',
                'log_id' => $log->id,
                'explanation' => $winner['explanation'] ?? [],
            ]));

            return $log;
        });
    }

    public function assignLeadById(
        int $leadId,
        string $method = 'auto',
        ?LeadAssignmentSetting $settings = null,
        ?int $newStageId = null,
        ?int $assignedStageId = null,
        bool $force = false,
    ): ?LeadAssignmentLog {
        $settings ??= LeadAssignmentSetting::current();

        return DB::transaction(function () use ($leadId, $method, $settings, $newStageId, $assignedStageId, $force) {
            /** @var Lead|null $lead */
            $lead = Lead::query()->lockForUpdate()->find($leadId);
            if (!$lead) {
                return null;
            }

            $newStageId ??= $this->resolveNewStageId();
            $assignedStageId ??= $this->resolveAssignedStageId($settings);
            if (!$newStageId || !$assignedStageId) {
                return null;
            }

            if ((int) $lead->stage_id !== (int) $newStageId) {
                return null;
            }

            if ($settings->system_disabled && !in_array($method, ['manual', 'admin_override'], true)) {
                return null;
            }

            if (!$force && $method === 'auto' && !$settings->auto_assign) {
                return null;
            }

            $this->refreshSalesPerformanceSnapshot();

            $winner = $this->pickBestAgent($lead, $settings);
            if (!$winner) {
                $winner = $this->resolveFallbackAssignment($lead, $settings);
            }
            if (!$winner) {
                $lead->update([
                    'assignment_hold' => true,
                    'assignment_hold_reason' => 'no_eligible_sales_or_hours',
                ]);

                return null;
            }

            /** @var User $agent */
            $agent = $winner['user'];
            $oldPersonId = $lead->responsible_person_id;
            $oldPerson = $lead->responsiblePerson;

            $lead->update([
                'responsible_person_id' => $agent->id,
                'stage_id' => $assignedStageId,
                'last_stage_change_at' => now(),
                'assignment_hold' => false,
                'assignment_hold_reason' => null,
            ]);

            $lead->refresh();

            $changes = [
                'action' => 'assigned',
                'old_person_id' => $oldPersonId,
                'old_person' => $oldPerson?->name,
                'new_person' => $agent->name,
            ];

            LeadHistoryHelper::log($lead->id, [
                'action' => 'assigned',
                'old_person_id' => $oldPersonId,
                'old_person' => $oldPerson?->name,
                'new_person' => $agent->name,
                'engine' => 'lead_assignment',
                'reason' => $winner['reason'],
            ]);

            broadcast(new LeadUpdated($lead, 'assigned', null, $changes));

            $log = $this->persistAssignmentLog($lead, $agent, $winner, $method, $oldPersonId);

            if ($settings->strategy === 'round_robin') {
                $settings->round_robin_cursor_user_id = $agent->id;
                $settings->save();
            }

            $this->touchAssigneeStats($agent);

            broadcast(new LeadAssignmentBroadcast($lead, $agent->id, $oldPersonId, [
                'type' => 'assigned',
                'log_id' => $log->id,
                'method' => $method,
                'explanation' => $winner['explanation'] ?? [],
            ]));

            return $log;
        });
    }

    /**
     * Simulate best assignee without persisting (dry run).
     *
     * @return array<string, mixed>|null
     */
    public function simulateAssignment(int $leadId, ?LeadAssignmentSetting $settings = null): ?array
    {
        $settings ??= LeadAssignmentSetting::current();

        $this->refreshSalesPerformanceSnapshot();

        /** @var Lead|null $lead */
        $lead = Lead::query()->find($leadId);
        if (!$lead) {
            return null;
        }

        $newStageId = $this->resolveNewStageId();
        if ((int) $lead->stage_id !== (int) $newStageId) {
            return [
                'error' => 'Lead is not in the New stage; simulation only runs for the New queue.',
                'lead_id' => $lead->id,
                'current_stage_id' => $lead->stage_id,
                'system_disabled' => (bool) $settings->system_disabled,
            ];
        }

        $winner = $this->pickBestAgent($lead, $settings);
        $usedFallback = false;
        if (!$winner) {
            $winner = $this->resolveFallbackAssignment($lead, $settings);
            $usedFallback = (bool) $winner;
        }
        if (!$winner) {
            return [
                'lead_id' => $lead->id,
                'winner' => null,
                'message' => 'No eligible agent (attendance, capacity, working hours, or cooldown rules).',
                'system_disabled' => (bool) $settings->system_disabled,
                'used_fallback' => false,
            ];
        }

        return [
            'lead_id' => $lead->id,
            'used_fallback' => $usedFallback,
            'winner' => [
                'user_id' => $winner['user']->id,
                'name' => $winner['user']->name,
            ],
            'final_score' => $winner['final_score'],
            'attendance_score' => $winner['attendance_score'],
            'performance_score' => $winner['performance_score'],
            'load_score' => $winner['load_score'],
            'fairness_score' => $winner['fairness_score'],
            'explanation' => $winner['explanation'] ?? [],
            'reason' => $winner['reason'],
            'priority_tier' => $this->priorityTier($lead, $settings),
            'system_disabled' => (bool) $settings->system_disabled,
            'probability_of_close' => $winner['probability_of_close'] ?? null,
            'dominant_factor' => $winner['dominant_factor'] ?? null,
            'was_exploration' => (bool) ($winner['was_exploration'] ?? false),
        ];
    }

    /**
     * @return array{user: User, final_score: float, reason: string, attendance_score: float, performance_score: float, load_score: float, fairness_score: float, explanation: array<string, string>}|null
     */
    public function pickBestAgent(Lead $lead, LeadAssignmentSetting $settings, ?int $excludeUserId = null): ?array
    {
        if (!$this->isWithinWorkingHours($settings)) {
            \Log::info("isWithinWorkingHours");
            return null;
        }

        $candidates = $this->baseSalesQuery()->get();
        if ($candidates->isEmpty()) {
            return null;
        }

        $ctx = $this->learningMaps($lead, $candidates);

        $attendanceByUserId = $this->todayAttendanceByUserId();
        $openCounts = $this->openLeadCountsByUser($candidates->pluck('id')->all());

        $eligible = $candidates->filter(function (User $user) use ($settings, $attendanceByUserId, $openCounts, $excludeUserId) {
            if ($excludeUserId !== null && (int) $user->id === $excludeUserId) {
                return false;
            }
            if ($user->on_vacation) {
                return false;
            }
            $open = (int) ($openCounts[$user->id] ?? 0);
            if ($open >= $settings->max_leads_per_user) {
                return false;
            }

            if ($settings->require_attendance) {
                $row = $attendanceByUserId[$user->id] ?? null;
                if (!$row) {
                    return false;
                }
                $st = strtolower((string) ($row['status'] ?? ''));
                if (!in_array($st, ['present', 'late'], true)) {
                    return false;
                }
            }

            return true;
        })->values();

        if ($eligible->isEmpty() && $excludeUserId !== null) {
            return $this->pickBestAgent($lead, $settings, null);
        }

        if ($eligible->isEmpty()) {
            return null;
        }

        $priorityIds = $this->priorityUserIds($settings);
        if ($priorityIds !== []) {
            $priorityEligible = $eligible->filter(fn (User $user) => in_array((int) $user->id, $priorityIds, true))->values();
            if ($priorityEligible->isNotEmpty()) {
                $eligible = $priorityEligible;
            }
        }

        if ($settings->strategy === 'round_robin') {
            return $this->pickRoundRobin($lead, $eligible, $settings, $attendanceByUserId, $openCounts, $ctx);
        }

        [$wa, $wp, $wv, $wf] = $this->dynamicWeights($lead, $settings);

        $rows = collect();
        foreach ($eligible as $user) {
            $rows->push($this->computeAgentScoreBreakdown($user, $lead, $settings, $attendanceByUserId, $openCounts, $wa, $wp, $wv, $wf, $ctx));
        }

        if ($rows->isEmpty()) {
            return null;
        }

        $rankFn = function (array $row) use ($lead, $settings) {
            return $this->isLeadHighPriority($lead, $settings)
                ? $row['final_score'] + ($row['performance_raw'] ?? 0) * 0.35
                : $row['final_score'];
        };

        $best = $rows->sortByDesc($rankFn)->first();
        $chosen = $best;
        $wasExploration = false;

        if ($rows->count() > 1) {
            $epsilon = max(0.0, min(0.45, (float) ($settings->exploration_epsilon ?? 0.1)));
            if ((mt_rand() / mt_getrandmax()) < $epsilon) {
                $chosen = $rows->random();
                $wasExploration = true;
            } else {
                $coldMax = max(1, (int) ($settings->cold_start_max_samples ?? 8));
                $coldR = max(0.0, min(0.5, (float) ($settings->cold_start_explore_ratio ?? 0.15)));
                $pat = ($ctx['patterns'] ?? collect())->get($best['user']->id);
                if ($pat && (int) $pat->samples < $coldMax && (mt_rand() / mt_getrandmax()) < $coldR) {
                    $others = $rows->filter(fn (array $r) => $r['user']->id !== $best['user']->id);
                    if ($others->isNotEmpty()) {
                        $chosen = $others->random();
                        $wasExploration = true;
                    }
                }
            }
        }

        $chosen['was_exploration'] = $wasExploration;
        $chosen['context_fingerprint'] = (string) ($ctx['context_fingerprint'] ?? '');
        $this->attachPredictionMeta($chosen, $lead, $settings, $ctx);

        return $chosen;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $ctx
     */
    protected function attachPredictionMeta(array &$row, Lead $lead, LeadAssignmentSetting $settings, array $ctx): void
    {
        /** @var LeadAssignmentLearningService $learning */
        $learning = $ctx['learning'];
        $patterns = $ctx['patterns'] ?? collect();
        $pat = $patterns->get($row['user']->id);
        $coldMax = (int) ($ctx['cold_max_samples'] ?? 8);
        $globalAvg = (float) ($ctx['global_pattern_avg'] ?? 0.5);
        $skillB = (float) ($row['skill_boost'] ?? 0.0);
        $patB = (float) ($row['pattern_boost'] ?? 0.0);
        $tb = (float) (($ctx['temporal_boost'] ?? [])[$row['user']->id] ?? 0.0);

        $row['dominant_factor'] = $this->inferDominantFactor(
            (float) $row['attendance_score'],
            (float) $row['performance_score'],
            $skillB,
            $patB
        );
        $row['probability_of_close'] = $learning->estimateCloseProbability(
            $pat,
            (float) ($row['performance_raw'] ?? 0.35),
            $skillB,
            $patB,
            $globalAvg,
            $coldMax,
            $tb
        );
    }

    protected function inferDominantFactor(float $wAtt, float $wPerf, float $skillBoost, float $patternBoost): string
    {
        $perfAxis = abs($wPerf) + abs($patternBoost);
        $skillAxis = abs($skillBoost) + 1e-8;
        $attAxis = abs($wAtt);

        if ($perfAxis >= $skillAxis && $perfAxis >= $attAxis) {
            return 'performance';
        }
        if ($skillAxis >= $attAxis) {
            return 'skill';
        }

        return 'attendance';
    }

    /**
     * @param Collection<int, User> $eligible
     */
    protected function pickRoundRobin(Lead $lead, Collection $eligible, LeadAssignmentSetting $settings, array $attendanceByUserId, array $openCounts, array $ctx = []): ?array
    {
        $ordered = $eligible->sortBy('id')->values();
        $ids = $ordered->pluck('id')->all();
        $cursor = $settings->round_robin_cursor_user_id;
        $startIdx = 0;
        if ($cursor && ($pos = array_search((int) $cursor, array_map('intval', $ids), true)) !== false) {
            $startIdx = ($pos + 1) % count($ids);
        }

        $n = $ordered->count();
        [$wa, $wp, $wv, $wf] = $this->dynamicWeights($lead, $settings);

        for ($i = 0; $i < $n; $i++) {
            /** @var User $user */
            $user = $ordered[($startIdx + $i) % $n];
            $row = $this->computeAgentScoreBreakdown($user, $lead, $settings, $attendanceByUserId, $openCounts, $wa, $wp, $wv, $wf, $ctx);
            $row['reason'] = 'Round robin rotation to '.$user->name.'. '.$row['reason'];
            $row['explanation']['reason'] = sprintf(
                '%s is next in the rotation among eligible, clocked-in agents (fair distribution).',
                $user->name
            );
            $row['was_exploration'] = false;
            $row['context_fingerprint'] = (string) ($ctx['context_fingerprint'] ?? '');
            $this->attachPredictionMeta($row, $lead, $settings, $ctx);

            return $row;
        }

        return null;
    }

    /**
     * @return array{user: User, final_score: float, reason: string, attendance_score: float, performance_score: float, load_score: float, fairness_score: float, explanation: array<string, string>, performance_raw: float}
     */
    protected function computeAgentScoreBreakdown(
        User $user,
        Lead $lead,
        LeadAssignmentSetting $settings,
        array $attendanceByUserId,
        array $openCounts,
        float $wa,
        float $wp,
        float $wv,
        float $wf,
        array $ctx = [],
    ): array {
        $attendanceRaw = $this->attendanceCompositeScore($user->id, $attendanceByUserId);
        $perfRow = SalesPerformance::query()->where('sales_id', $user->id)->first();
        $perfRaw = $perfRow ? min(1, max(0, (float) $perfRow->score / 100)) : 0.35;

        $coldMax = (int) ($ctx['cold_max_samples'] ?? 8);
        $globalPerfAvg = (float) ($ctx['global_perf_avg'] ?? 0.35);
        $patterns = $ctx['patterns'] ?? collect();
        $pat = $patterns->get($user->id);
        if ($pat && (int) $pat->samples < $coldMax) {
            $s = (int) $pat->samples;
            $perfRaw = ($s * $perfRaw + ($coldMax - $s) * $globalPerfAvg) / max(1, $coldMax);
        }

        $availabilityRaw = $this->availabilityScore($user->id, $attendanceByUserId);
        $open = (int) ($openCounts[$user->id] ?? 0);
        $fairnessBase = 1 - min(1, $open / max(1, $settings->max_leads_per_user));
        $dailyPenalty = $this->dailyAssignmentPenaltyFactor($user);
        $fairnessRaw = max(0, min(1, $fairnessBase * $dailyPenalty));

        $weightedAttendance = $wa * $attendanceRaw;
        $weightedPerformance = $wp * $perfRaw;
        $weightedLoad = $wv * $availabilityRaw;
        $weightedFairness = $wf * $fairnessRaw;

        $fa = (float) ($settings->factor_weight_attendance ?? 0.3333);
        $fp = (float) ($settings->factor_weight_performance ?? 0.3333);
        $fk = (float) ($settings->factor_weight_skill ?? 0.3334);
        $fsum = max(0.001, $fa + $fp + $fk);
        $fa /= $fsum;
        $fp /= $fsum;
        $fk /= $fsum;
        $m = ($fa + $fp + $fk) / 3;

        $adaptiveOn = (bool) ($settings->adaptive_weights_enabled ?? true);
        if ($adaptiveOn) {
            $weightedAttendance *= $fa / max(0.12, $m);
            $weightedPerformance *= $fp / max(0.12, $m);
        }

        $sum = $weightedAttendance + $weightedPerformance + $weightedLoad + $weightedFairness;
        $cooldown = $this->cooldownMultiplier($user, $settings);
        $final = max(0, $sum * $cooldown);

        $patternBoost = 0.0;
        $skillBoost = 0.0;
        if ($ctx !== []) {
            /** @var LeadAssignmentLearningService $learning */
            $learning = $ctx['learning'];
            $skillsByUser = $ctx['skillsByUser'] ?? collect();
            $globalAvg = (float) ($ctx['global_pattern_avg'] ?? 0.5);
            $patternBoost = $learning->patternBoost($pat, $globalAvg, $coldMax);
            $skillBoost = $learning->skillBoostForUser($user, $lead, $skillsByUser->get($user->id));
            if ($adaptiveOn) {
                $patternBoost *= $fp / max(0.12, $m);
                $skillBoost *= $fk / max(0.12, $m);
            }
            $tb = (float) (($ctx['temporal_boost'] ?? [])[$user->id] ?? 0.0);
            $final = max(0, $final + $patternBoost + $skillBoost + $tb);
        }

        $explanation = [
            'attendance' => sprintf(
                'Attendance composite %.2f (presence + check-in timing); weighted contribution %.3f.',
                $attendanceRaw,
                $weightedAttendance
            ),
            'performance' => sprintf(
                'Performance index %.2f from sales_performance (deals closed %s, conversion %.2f%%); weighted %.3f.',
                $perfRaw,
                $perfRow ? (string) $perfRow->deals_closed : 'n/a',
                $perfRow ? (float) ($perfRow->conversion_rate ?? 0) : 0.0,
                $weightedPerformance
            ),
            'load' => sprintf(
                'Availability / pipeline slot %.2f; open non-terminal leads: %d; weighted %.3f.',
                $availabilityRaw,
                $open,
                $weightedLoad
            ),
            'fairness' => sprintf(
                'Fairness base %.2f from capacity; daily-assign dampening ×%.2f; weighted %.3f.',
                $fairnessBase,
                $dailyPenalty,
                $weightedFairness
            ),
            'reason' => sprintf(
                '%s is the best match for this lead (tier: %s) with blended score %.4f after cooldown ×%.2f.',
                $user->name,
                $this->priorityTier($lead, $settings),
                $final,
                $cooldown
            ),
            'cooldown_detail' => sprintf(
                'Cooldown multiplier ×%.2f applied to weighted sum %.4f → base %.4f.',
                $cooldown,
                $sum,
                max(0, $sum * $cooldown)
            ),
            'memory' => sprintf(
                'Contextual memory (success prior for this lead profile) %+0.4f.',
                $patternBoost
            ),
            'skills' => sprintf(
                'Skill match boost %+0.4f vs lead profile text.',
                $skillBoost
            ),
        ];

        $reason = sprintf(
            'strategy=%s; final=%.4f (att_w=%.3f perf_w=%.3f load_w=%.3f fair_w=%.3f mem+%0.4f skill+%0.4f); open=%d; lead_score=%.1f; tier=%s',
            $settings->strategy,
            $final,
            $weightedAttendance,
            $weightedPerformance,
            $weightedLoad,
            $weightedFairness,
            $patternBoost,
            $skillBoost,
            $open,
            $lead->exists ? $lead->computed_assignment_score : 0.0,
            $this->priorityTier($lead, $settings)
        );

        return [
            'user' => $user,
            'final_score' => $final,
            'reason' => $reason,
            'attendance_score' => $weightedAttendance,
            'performance_score' => $weightedPerformance,
            'load_score' => $weightedLoad,
            'fairness_score' => $weightedFairness,
            'explanation' => $explanation,
            'performance_raw' => $perfRaw,
            'pattern_boost' => $patternBoost,
            'skill_boost' => $skillBoost,
        ];
    }

    protected function persistAssignmentLog(Lead $lead, User $agent, array $winner, string $method, ?int $oldPersonId): LeadAssignmentLog
    {
        $tz = (string) (LeadAssignmentSetting::current()->working_hours['timezone'] ?? 'Asia/Dubai');
        SalesTemporalStat::recordAssignment((int) $agent->id, Carbon::now($tz));

        return LeadAssignmentLog::query()->create([
            'lead_id' => $lead->id,
            'assigned_to' => $agent->id,
            'score_used' => $winner['final_score'],
            'attendance_score' => $winner['attendance_score'],
            'performance_score' => $winner['performance_score'],
            'load_score' => $winner['load_score'],
            'fairness_score' => $winner['fairness_score'],
            'explanation' => $winner['explanation'] ?? [],
            'reason' => $winner['reason'],
            'method' => $method,
            'dominant_factor' => $winner['dominant_factor'] ?? null,
            'probability_of_close' => $winner['probability_of_close'] ?? null,
            'was_exploration' => (bool) ($winner['was_exploration'] ?? false),
            'context_fingerprint' => $winner['context_fingerprint'] ?? app(LeadAssignmentLearningService::class)->contextFingerprint($lead),
        ]);
    }

    protected function touchAssigneeStats(User $agent): void
    {
        $today = now()->toDateString();
        $user = User::query()->lockForUpdate()->find($agent->id);
        if (!$user) {
            return;
        }
        if ($user->lead_assign_count_date?->toDateString() !== $today) {
            $user->lead_assign_daily_count = 0;
            $user->lead_assign_count_date = $today;
        }
        $user->lead_assign_daily_count = (int) $user->lead_assign_daily_count + 1;
        $user->last_lead_assigned_at = now();
        $user->save();
    }

    protected function cooldownMultiplier(User $user, LeadAssignmentSetting $settings): float
    {
        $minsCooldown = max(1, (int) $settings->assign_cooldown_minutes);
        if (!$user->last_lead_assigned_at) {
            return 1.0;
        }
        $elapsed = $user->last_lead_assigned_at->diffInSeconds(now());
        $need = $minsCooldown * 60;
        if ($elapsed >= $need) {
            return 1.0;
        }

        $t = $elapsed / max(1, $need);

        return round(0.18 + 0.82 * $t, 4);
    }

    protected function dailyAssignmentPenaltyFactor(User $user): float
    {
        $c = (int) ($user->lead_assign_daily_count ?? 0);

        return 1 / (1 + 0.08 * max(0, $c));
    }

    protected function attendanceCompositeScore(int $userId, array $attendanceByUserId): float
    {
        $row = $attendanceByUserId[$userId] ?? null;
        if (!$row) {
            return 0.0;
        }
        $st = strtolower((string) ($row['status'] ?? ''));
        $presence = 0.0;
        if ($st === 'present') {
            $presence = 0.92;
        } elseif ($st === 'late') {
            $presence = 0.55;
        } else {
            return 0.0;
        }

        $timingBonus = 0.0;
        if (!empty($row['check_in'])) {
            try {
                $ci = Carbon::parse($row['check_in']);
                $midnight = $ci->copy()->startOfDay();
                $mins = (float) $midnight->diffInMinutes($ci);
                $early = max(0, 120 - $mins);
                $timingBonus = min(0.18, $early / 120 * 0.18);
                if ($st === 'late') {
                    $timingBonus -= 0.12;
                }
            } catch (\Throwable) {
                $timingBonus = 0.0;
            }
        }

        return max(0, min(1, $presence + $timingBonus));
    }

    protected function availabilityScore(int $userId, array $attendanceByUserId): float
    {
        $row = $attendanceByUserId[$userId] ?? null;
        if (!$row || empty($row['check_in'])) {
            return 0.4;
        }
        try {
            $ci = Carbon::parse($row['check_in']);
            $midnight = $ci->copy()->startOfDay();
            $minsSinceMidnight = (float) $midnight->diffInMinutes($ci);
            $clamped = min(480.0, max(0.0, $minsSinceMidnight)) / 480.0;

            return 0.45 + 0.55 * $clamped;
        } catch (\Throwable) {
            return 0.55;
        }
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    protected function dynamicWeights(Lead $lead, LeadAssignmentSetting $settings): array
    {
        [$wa, $wp, $wv, $wf] = $this->normalizedWeights($settings);

        if ($this->isLeadHighPriority($lead, $settings)) {
            $wp += 0.12;
            $wa += 0.02;
            $wf -= 0.10;
            $wv -= 0.04;
        } elseif ($this->isLeadLowPriority($lead, $settings)) {
            $wf += 0.10;
            $wp -= 0.08;
            $wv -= 0.02;
        }

        $sum = $wa + $wp + $wv + $wf;

        return [$wa / $sum, $wp / $sum, $wv / $sum, $wf / $sum];
    }

    protected function isLeadHighPriority(Lead $lead, LeadAssignmentSetting $settings): bool
    {
        $threshold = (int) ($settings->high_priority_score_threshold ?? 70);
        $score = (float) $lead->computed_assignment_score;
        $p = strtolower((string) ($lead->priority ?? ''));

        return $score >= $threshold || str_contains($p, 'hot');
    }

    protected function isLeadLowPriority(Lead $lead, LeadAssignmentSetting $settings): bool
    {
        if ($this->isLeadHighPriority($lead, $settings)) {
            return false;
        }
        $score = (float) $lead->computed_assignment_score;
        $p = strtolower((string) ($lead->priority ?? ''));

        return $score < 42 && !str_contains($p, 'warm') && !str_contains($p, 'hot');
    }

    protected function priorityTier(Lead $lead, LeadAssignmentSetting $settings): string
    {
        if ($this->isLeadHighPriority($lead, $settings)) {
            return 'high';
        }
        if ($this->isLeadLowPriority($lead, $settings)) {
            return 'low';
        }

        return 'normal';
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    protected function normalizedWeights(LeadAssignmentSetting $settings): array
    {
        $map = [
            'ai_hybrid' => [
                (float) $settings->weight_attendance,
                (float) $settings->weight_performance,
                (float) $settings->weight_availability,
                (float) $settings->weight_fairness,
            ],
            'attendance_priority' => [0.62, 0.18, 0.12, 0.08],
            'performance' => [0.12, 0.58, 0.15, 0.15],
            'round_robin' => [0.25, 0.25, 0.25, 0.25],
        ];

        $w = $map[$settings->strategy] ?? $map['ai_hybrid'];
        $sum = array_sum($w) ?: 1.0;

        return [$w[0] / $sum, $w[1] / $sum, $w[2] / $sum, $w[3] / $sum];
    }

    protected function isWithinWorkingHours(LeadAssignmentSetting $settings): bool
    {
        $wh = $settings->working_hours ?? [];
        $tz = (string) ($wh['timezone'] ?? 'Asia/Dubai');
        $start = (string) ($wh['start'] ?? '00:00');
        $end = (string) ($wh['end'] ?? '23:59');

        try {
            $now = Carbon::now($tz);
            $today = $now->toDateString();
            $startAt = Carbon::parse($today.' '.$start, $tz);
            $endAt = Carbon::parse($today.' '.$end, $tz);

            return $now->greaterThanOrEqualTo($startAt) && $now->lessThanOrEqualTo($endAt);
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function todayAttendanceByUserId(): array
    {
        $tz = (string) (LeadAssignmentSetting::current()->working_hours['timezone'] ?? 'Asia/Dubai');
        $date = Carbon::now($tz)->toDateString();

        $rows = Attendance::query()
            ->whereDate('date', $date)
            ->whereNotNull('user_id')
            ->get(['user_id', 'status', 'check_in', 'check_out', 'created_at']);

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->user_id] = [
                'status' => $row->status,
                'check_in' => $row->check_in,
                'check_out' => $row->check_out,
                'created_at' => $row->created_at,
            ];
        }

        return $map;
    }

    /**
     * @param list<int> $userIds
     * @return array<int, int>
     */
    protected function openLeadCountsByUser(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $terminal = $this->terminalStageIds();

        $q = Lead::query()
            ->selectRaw('responsible_person_id as uid, COUNT(*) as c')
            ->whereIn('responsible_person_id', $userIds);
        if ($terminal !== []) {
            $q->whereNotIn('stage_id', $terminal);
        }

        return $q->groupBy('responsible_person_id')
            ->pluck('c', 'uid')
            ->all();
    }

    /**
     * @return list<int>
     */
    protected function terminalStageIds(): array
    {
        return Stage::query()
            ->where('stage_type', 'lead')
            ->whereRaw('LOWER(name) in (?, ?)', ['lost', 'converted'])
            ->pluck('id')
            ->all();
    }

    public function resolveNewStageId(): ?int
    {
        $byName = Stage::query()->where('stage_type', 'lead')->whereRaw('LOWER(name) = ?', ['new'])->value('id');
        if ($byName) {
            return (int) $byName;
        }

        return Stage::query()->where('stage_type', 'lead')->orderBy('order')->value('id');
    }

    public function resolveAssignedStageId(LeadAssignmentSetting $settings): ?int
    {
        if ($settings->assigned_stage_id) {
            return (int) $settings->assigned_stage_id;
        }

        $byName = Stage::query()->where('stage_type', 'lead')->whereRaw('LOWER(name) = ?', ['assigned'])->value('id');
        if ($byName) {
            return (int) $byName;
        }

        return Stage::query()
            ->where('stage_type', 'lead')
            ->where('order', '>', 1)
            ->orderBy('order')
            ->value('id');
    }

    protected function baseSalesQuery()
    {
        $query = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'sales'));

        if (\Spatie\Permission\Models\Permission::where('name', 'show-leads')->exists()) {
            $query->permission('show-leads');
        }

        return $query;
    }

    /**
     * @return list<int>
     */
    protected function priorityUserIds(LeadAssignmentSetting $settings): array
    {
        return array_values(array_unique(array_map('intval', $settings->priority_sales_user_ids ?? [])));
    }

    /**
     * Absolute priority: if any of the already-eligible ids belong to the priority list,
     * restrict to those; otherwise fall back to the full eligible set unchanged.
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    protected function applyPriorityFilter(array $ids, LeadAssignmentSetting $settings): array
    {
        $priorityIds = $this->priorityUserIds($settings);
        if ($priorityIds === []) {
            return $ids;
        }

        $filtered = array_values(array_intersect($ids, $priorityIds));

        return $filtered !== [] ? $filtered : $ids;
    }

    /**
     * When no normal winner exists, assign configured fallback sales (ignores working hours).
     *
     * @return array<string, mixed>|null
     */
    protected function resolveFallbackAssignment(Lead $lead, LeadAssignmentSetting $settings): ?array
    {
        $fid = $settings->fallback_user_id;
        if (!$fid) {
            return null;
        }

        /** @var User|null $agent */
        $agent = User::query()
            ->whereKey((int) $fid)
            ->where('on_vacation', false)
            ->whereHas('roles', fn ($q) => $q->where('name', 'sales'))
            ->first();
        if (!$agent) {
            return null;
        }

        $ctx = $this->learningMaps($lead, collect([$agent]));
        $attendanceByUserId = $this->todayAttendanceByUserId();
        $openCounts = $this->openLeadCountsByUser([(int) $agent->id]);
        [$wa, $wp, $wv, $wf] = $this->dynamicWeights($lead, $settings);
        $row = $this->computeAgentScoreBreakdown($agent, $lead, $settings, $attendanceByUserId, $openCounts, $wa, $wp, $wv, $wf, $ctx);
        $row['reason'] = 'Fail-safe fallback user #'.$agent->id.' ('.$agent->name.'). '.$row['reason'];
        $row['explanation']['reason'] = 'Assigned via configured fallback because no eligible sales matched attendance / capacity rules.';
        $row['was_exploration'] = false;
        $row['context_fingerprint'] = (string) ($ctx['context_fingerprint'] ?? '');
        $this->attachPredictionMeta($row, $lead, $settings, $ctx);

        return $row;
    }

    /**
     * Escalate leads in the assigned stage with no first contact past SLA to a higher-performing sales rep.
     */
    public function processSlaEscalations(?LeadAssignmentSetting $settings = null): int
    {
        $settings ??= LeadAssignmentSetting::current();
        if ($settings->system_disabled || !$settings->sla_escalation_enabled) {
            return 0;
        }

        $sla = (int) ($settings->sla_minutes ?? 0);
        if ($sla === 0) {
            return 0;
        }
        if ($sla < 5) {
            return 0;
        }

        $assignedStageId = $this->resolveAssignedStageId($settings);
        if (!$assignedStageId) {
            return 0;
        }

        $cutoff = now()->subMinutes($sla);
        $ids = Lead::query()
            ->where('stage_id', $assignedStageId)
            ->whereNotNull('responsible_person_id')
            ->whereNull('first_contacted_at')
            ->where('last_stage_change_at', '<=', $cutoff)
            ->orderBy('id')
            ->pluck('id');

        $n = 0;
        foreach ($ids as $leadId) {
            if ($this->escalateLeadSla((int) $leadId, $settings, $assignedStageId)) {
                $n++;
            }
        }

        return $n;
    }

    protected function escalateLeadSla(int $leadId, LeadAssignmentSetting $settings, int $assignedStageId): bool
    {
        $assignmentLog = DB::transaction(function () use ($leadId, $settings, $assignedStageId) {
            /** @var Lead|null $lead */
            $lead = Lead::query()->lockForUpdate()->find($leadId);
            if (!$lead || (int) $lead->stage_id !== (int) $assignedStageId) {
                return null;
            }
            if ($lead->first_contacted_at) {
                return null;
            }
            if (!$lead->last_stage_change_at || $lead->last_stage_change_at->greaterThan(now()->subMinutes(max(5, (int) $settings->sla_minutes)))) {
                return null;
            }

            $exclude = (int) $lead->responsible_person_id;
            if (!$exclude) {
                return null;
            }

            $this->refreshSalesPerformanceSnapshot();

            $winner = $this->pickBestAgent($lead, $settings, $exclude);
            if (!$winner) {
                return null;
            }

            $curScore = (float) (SalesPerformance::query()->where('sales_id', $exclude)->value('score') ?? 0);
            $newScore = (float) (SalesPerformance::query()->where('sales_id', $winner['user']->id)->value('score') ?? 0);
            if ($newScore <= $curScore) {
                return null;
            }

            /** @var User $agent */
            $agent = $winner['user'];
            $oldPersonId = $lead->responsible_person_id;
            $oldPerson = $lead->responsiblePerson;

            $winner['reason'] = 'SLA escalation (no contact within '.$settings->sla_minutes.'m, higher performer). '.$winner['reason'];
            $winner['explanation']['reason'] = sprintf(
                'Reassigned because the lead had no logged activity before the %d-minute SLA while owned by a lower-scoring rep.',
                (int) $settings->sla_minutes
            );

            $lead->update([
                'responsible_person_id' => $agent->id,
                'last_stage_change_at' => now(),
                'last_sla_escalation_at' => now(),
            ]);
            $lead->refresh();

            $changes = [
                'action' => 'assigned',
                'old_person_id' => $oldPersonId,
                'old_person' => $oldPerson?->name,
                'new_person' => $agent->name,
            ];

            LeadHistoryHelper::log($lead->id, [
                'action' => 'sla_escalation',
                'old_person_id' => $oldPersonId,
                'old_person' => $oldPerson?->name,
                'new_person' => $agent->name,
                'engine' => 'lead_assignment',
                'reason' => $winner['reason'],
            ]);

            broadcast(new LeadUpdated($lead, 'assigned', null, $changes));

            $persisted = $this->persistAssignmentLog($lead, $agent, $winner, 'sla_escalation', $oldPersonId);
            $this->touchAssigneeStats($agent);
            broadcast(new LeadAssignmentBroadcast($lead, $agent->id, $oldPersonId, [
                'type' => 'sla_escalation',
                'log_id' => $persisted->id,
                'explanation' => $winner['explanation'] ?? [],
            ]));

            return $persisted;
        });

        return $assignmentLog !== null;
    }

    /**
     * Force-assign a lead to a specific sales user (bypasses engine scoring and system_disabled).
     */
    public function adminOverrideAssign(int $leadId, int $salesUserId, ?LeadAssignmentSetting $settings = null): ?LeadAssignmentLog
    {
        $settings ??= LeadAssignmentSetting::current();

        return DB::transaction(function () use ($leadId, $salesUserId, $settings) {
            /** @var Lead|null $lead */
            $lead = Lead::query()->lockForUpdate()->find($leadId);
            if (!$lead) {
                return null;
            }

            $agent = User::query()
                ->whereKey($salesUserId)
                ->whereHas('roles', fn ($q) => $q->where('name', 'sales'))
                ->first();
            if (!$agent) {
                return null;
            }

            $newStageId = $this->resolveNewStageId();
            $assignedStageId = $this->resolveAssignedStageId($settings);
            if (!$assignedStageId) {
                return null;
            }

            $oldPersonId = $lead->responsible_person_id;
            $oldPerson = $lead->responsiblePerson;

            $payload = [
                'responsible_person_id' => $agent->id,
                'last_stage_change_at' => now(),
            ];
            if ($newStageId && (int) $lead->stage_id === (int) $newStageId) {
                $payload['stage_id'] = $assignedStageId;
            }

            $lead->update($payload);
            $lead->refresh();

            $changes = [
                'action' => 'assigned',
                'old_person_id' => $oldPersonId,
                'old_person' => $oldPerson?->name,
                'new_person' => $agent->name,
            ];

            LeadHistoryHelper::log($lead->id, [
                'action' => 'admin_override',
                'old_person_id' => $oldPersonId,
                'old_person' => $oldPerson?->name,
                'new_person' => $agent->name,
                'engine' => 'lead_assignment',
            ]);

            broadcast(new LeadUpdated($lead, 'assigned', null, $changes));

            $explanation = [
                'attendance' => 'Not evaluated (administrator override).',
                'performance' => 'Not evaluated (administrator override).',
                'load' => 'Not evaluated (administrator override).',
                'fairness' => 'Not evaluated (administrator override).',
                'reason' => 'Lead owner set directly by an administrator.',
            ];

            $winner = [
                'final_score' => null,
                'attendance_score' => null,
                'performance_score' => null,
                'load_score' => null,
                'fairness_score' => null,
                'explanation' => $explanation,
                'reason' => 'Administrator override to user #'.$agent->id.' ('.$agent->name.').',
                'dominant_factor' => null,
                'probability_of_close' => null,
                'was_exploration' => false,
                'context_fingerprint' => app(LeadAssignmentLearningService::class)->contextFingerprint($lead),
            ];

            $log = $this->persistAssignmentLog($lead, $agent, $winner, 'admin_override', $oldPersonId);
            $this->touchAssigneeStats($agent);

            broadcast(new LeadAssignmentBroadcast($lead, $agent->id, $oldPersonId, [
                'type' => 'admin_override',
                'log_id' => $log->id,
                'explanation' => $explanation,
            ]));

            return $log;
        });
    }

    /**
     * Revert lead assignees in a specific stage back to their original owner.
     * Stage remains unchanged.
     *
     * @return array{updated:int, skipped:int, errors:int, details:list<array<string,mixed>>}
     */
    public function revertStageAssignments(int $stageId): array
    {
        $newStageId = $this->resolveNewStageId();
        if (!$newStageId) {
            return [
                'updated' => 0,
                'skipped' => 0,
                'errors' => 1,
                'details' => [['message' => 'New stage not configured']],
            ];
        }

        $ids = Lead::query()
            ->where('stage_id', $stageId)
            ->whereNotNull('responsible_person_id')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $stats = ['updated' => 0, 'skipped' => 0, 'errors' => 0, 'details' => []];

        foreach ($ids as $leadId) {
            try {
                $changed = DB::transaction(function () use ($leadId, $newStageId) {
                    /** @var Lead|null $lead */
                    $lead = Lead::query()->lockForUpdate()->find($leadId);
                    if (!$lead || !$lead->responsible_person_id) {
                        return false;
                    }

                    // Original owner priority:
                    // 1) initial_responsible_person_id snapshot
                    // 2) lead creator (added_by) as fallback
                    // 3) earliest assignment log assignee
                    $originalOwnerId = (int) ($lead->initial_responsible_person_id ?? 0);
                    if ($originalOwnerId <= 0) {
                        $originalOwnerId = (int) ($lead->added_by ?? 0);
                    }
                    if ($originalOwnerId <= 0) {
                        $firstAssigned = LeadAssignmentLog::query()
                            ->where('lead_id', $lead->id)
                            ->orderBy('id')
                            ->value('assigned_to');
                        $originalOwnerId = (int) ($firstAssigned ?? 0);
                    }
                    if ($originalOwnerId <= 0) {
                        return false;
                    }

                    /** @var User|null $originalOwner */
                    $originalOwner = User::query()->find($originalOwnerId);
                    if (!$originalOwner) {
                        return false;
                    }

                    $oldPersonId = (int) $lead->responsible_person_id;
                    $oldPerson = $lead->responsiblePerson;
                    $oldStageId = (int) $lead->stage_id;

                    $lead->update([
                        'responsible_person_id' => $originalOwner->id,
                        'stage_id' => $newStageId,
                        'last_stage_change_at' => now(),
                        'assignment_hold' => false,
                        'assignment_hold_reason' => null,
                    ]);
                    $lead->refresh();

                    LeadHistoryHelper::log($lead->id, [
                        'action' => 'revert_stage_assignment',
                        'old_person_id' => $oldPersonId,
                        'old_person' => $oldPerson?->name,
                        'new_person' => $originalOwner->name,
                        'engine' => 'lead_assignment',
                        'old_stage_id' => $oldStageId,
                        'new_stage_id' => (int) $newStageId,
                        'reason' => 'Bulk stage revert to New + original owner',
                    ]);

                    LeadAssignmentLog::query()->create([
                        'lead_id' => $lead->id,
                        'assigned_to' => $originalOwner->id,
                        'score_used' => null,
                        'attendance_score' => null,
                        'performance_score' => null,
                        'load_score' => null,
                        'fairness_score' => null,
                        'explanation' => [
                            'reason' => 'Reverted to New stage and restored original owner.',
                        ],
                        'reason' => sprintf(
                            'Stage revert: moved to New stage and ownership restored from %s to original owner %s.',
                            $oldPerson?->name ?? ('#'.$oldPersonId),
                            $originalOwner->name
                        ),
                        'method' => 'stage_revert',
                        'dominant_factor' => null,
                        'probability_of_close' => null,
                        'was_exploration' => false,
                        'context_fingerprint' => app(LeadAssignmentLearningService::class)->contextFingerprint($lead),
                    ]);

                    try {
                        broadcast(new LeadUpdated($lead, 'assigned', null, [
                            'action' => 'assigned',
                            'old_person_id' => $oldPersonId,
                            'old_person' => $oldPerson?->name,
                            'new_person' => $originalOwner->name,
                            'old_stage_id' => $oldStageId,
                            'new_stage_id' => (int) $newStageId,
                        ]));
                    } catch (\Throwable) {
                        // non-fatal for bulk revert
                    }

                    return true;
                });

                if ($changed) {
                    $stats['updated']++;
                } else {
                    $stats['skipped']++;
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                $stats['details'][] = ['lead_id' => (int) $leadId, 'error' => $e->getMessage()];
            }
        }

        return $stats;
    }

    protected function averageFirstResponseMinutes(int $salesId): ?float
    {
        if (DB::getDriverName() !== 'mysql') {
            return null;
        }

        $row = DB::selectOne(
            'SELECT AVG(TIMESTAMPDIFF(MINUTE, l.created_at, x.first_at)) AS avg_min
             FROM (
                 SELECT lead_id, MIN(created_at) AS first_at
                 FROM lead_activities
                 WHERE user_id = ? AND deleted_at IS NULL
                 GROUP BY lead_id
             ) x
             INNER JOIN leads l ON l.id = x.lead_id',
            [$salesId]
        );

        if (!$row || $row->avg_min === null) {
            return null;
        }

        return round((float) $row->avg_min, 2);
    }
}
