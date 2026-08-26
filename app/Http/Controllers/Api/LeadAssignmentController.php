<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadAssignmentLog;
use App\Models\LeadAssignmentSetting;
use App\Services\LeadAssignmentInsightsService;
use App\Services\LeadAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadAssignmentController extends Controller
{
    public function show(): JsonResponse
    {
        return ApiResponse::success(LeadAssignmentSetting::current()->loadMissing('fallbackUser:id,name'), 'Lead assignment settings loaded');
    }

    public function stats(LeadAssignmentService $service): JsonResponse
    {
        return ApiResponse::success($service->dashboardStats(), 'Lead assignment stats');
    }

    public function insights(LeadAssignmentInsightsService $insights): JsonResponse
    {
        return ApiResponse::success($insights->build(), 'Lead assignment insights');
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'auto_assign' => 'sometimes|boolean',
            'system_disabled' => 'sometimes|boolean',
            'mode' => 'sometimes|in:realtime,scheduled,manual',
            'strategy' => 'sometimes|in:ai_hybrid,attendance_priority,performance,round_robin',
            'schedule_times' => 'sometimes|array',
            'schedule_times.*' => 'string|regex:/^\d{2}:\d{2}$/',
            'max_leads_per_user' => 'sometimes|integer|min:1|max:500',
            'working_hours' => 'sometimes|array',
            'working_hours.start' => 'sometimes|string',
            'working_hours.end' => 'sometimes|string',
            'working_hours.timezone' => 'sometimes|string',
            'assigned_stage_id' => 'sometimes|nullable|integer|exists:stages,id',
            'weight_attendance' => 'sometimes|numeric|min:0|max:1',
            'weight_performance' => 'sometimes|numeric|min:0|max:1',
            'weight_availability' => 'sometimes|numeric|min:0|max:1',
            'weight_fairness' => 'sometimes|numeric|min:0|max:1',
            'require_attendance' => 'sometimes|boolean',
            'stuck_recovery_enabled' => 'sometimes|boolean',
            'stuck_lead_minutes' => 'sometimes|integer|min:15|max:10080',
            'assign_cooldown_minutes' => 'sometimes|integer|min:1|max:1440',
            'high_priority_score_threshold' => 'sometimes|integer|min:1|max:100',
            'sla_minutes' => 'sometimes|integer|min:0|max:10080',
            'sla_escalation_enabled' => 'sometimes|boolean',
            'fallback_user_id' => 'sometimes|nullable|integer|exists:users,id',
            'priority_sales_user_ids' => 'sometimes|array',
            'priority_sales_user_ids.*' => 'integer|exists:users,id',
            'exploration_epsilon' => 'sometimes|numeric|min:0|max:0.45',
            'cold_start_max_samples' => 'sometimes|integer|min:1|max:100',
            'cold_start_explore_ratio' => 'sometimes|numeric|min:0|max:0.5',
            'adaptive_weights_enabled' => 'sometimes|boolean',
            'factor_weight_attendance' => 'sometimes|numeric|min:0.12|max:0.55',
            'factor_weight_performance' => 'sometimes|numeric|min:0.12|max:0.55',
            'factor_weight_skill' => 'sometimes|numeric|min:0.12|max:0.55',
            'realtime_assignment_enabled' => 'sometimes|boolean',
            'realtime_interval_seconds' => 'sometimes|integer|min:2|max:30',
            'simple_mode_enabled' => 'sometimes|boolean',
            'simple_mode_batch_size' => 'sometimes|integer|min:1|max:500',
            'simple_mode_auto_interval_seconds' => 'sometimes|integer|min:2|max:300',
        ]);

        $row = LeadAssignmentSetting::current();
        $row->fill($data);
        if ($request->has('realtime_assignment_enabled') && !$request->boolean('realtime_assignment_enabled')) {
            $row->realtime_status = 'stopped';
        }
        if (
            ($request->has('auto_assign') && !$request->boolean('auto_assign')) ||
            ($request->has('system_disabled') && $request->boolean('system_disabled'))
        ) {
            $row->realtime_status = 'stopped';
            $row->realtime_last_run_at = now();
        }
        $row->save();

        // Simple mode control layer: turning Auto+Simple on should start immediately.
        if ((bool) $row->auto_assign && (bool) $row->simple_mode_enabled && !(bool) $row->system_disabled) {
            app(LeadAssignmentService::class)->assignLeadsByAttendanceSimple();
        }

        return ApiResponse::success($row->fresh(), 'Lead assignment settings saved');
    }

    public function queue(LeadAssignmentService $service): JsonResponse
    {
        $newStageId = $service->resolveNewStageId();
        if (!$newStageId) {
            return ApiResponse::error('New stage not found', 422);
        }

        $totalInNew = (int) Lead::query()->where('stage_id', $newStageId)->count();

        // Cap list payload for UI performance; total is returned in meta for the headline count.
        $leads = Lead::query()
            ->with(['stage:id,name', 'responsiblePerson:id,name'])
            ->where('stage_id', $newStageId)
            ->orderByDesc('id')
            ->limit(75)
            ->get(['id', 'lead_number', 'lead_name', 'stage_id', 'responsible_person_id', 'budget', 'lead_source', 'priority', 'score', 'created_at']);

        $payload = $leads->map(function (Lead $lead) {
            return [
                'id' => $lead->id,
                'lead_number' => $lead->lead_number,
                'lead_name' => $lead->lead_name,
                'responsible' => $lead->responsiblePerson?->name,
                'computed_assignment_score' => $lead->computed_assignment_score,
                'created_at' => $lead->created_at,
            ];
        });

        return ApiResponse::success($payload, 'Queue loaded', 200, [
            'total_in_new' => $totalInNew,
            'preview_limit' => 75,
        ]);
    }

    public function logs(Request $request): JsonResponse
    {
        $logs = LeadAssignmentLog::query()
            ->with(['assignee:id,name', 'lead:id,lead_number,lead_name'])
            ->orderByDesc('id')
            ->paginate((int) $request->query('per_page', 20));

        return ApiResponse::success($logs, 'Logs loaded');
    }

    public function runNow(LeadAssignmentService $service): JsonResponse
    {
        $settings = LeadAssignmentSetting::current();
        if ($settings->simple_mode_enabled && !$settings->system_disabled) {
            $stats = $service->assignLeadsByAttendanceSimple();
        } else {
            $stats = $service->assignQueuedLeads(false);
        }

        return ApiResponse::success($stats, 'Assignment run finished');
    }

    public function reassign(Request $request, LeadAssignmentService $service): JsonResponse
    {
        $data = $request->validate([
            'lead_id' => 'required|integer|exists:leads,id',
        ]);

        $settings = LeadAssignmentSetting::current();
        $newStageId = $service->resolveNewStageId();
        if (!$newStageId || (int) Lead::query()->whereKey($data['lead_id'])->value('stage_id') !== (int) $newStageId) {
            return ApiResponse::error('Lead must be in the New stage to reassign.', 422);
        }

        $log = $service->assignLeadById((int) $data['lead_id'], 'manual', $settings, null, null, true);

        if (!$log) {
            return ApiResponse::error('Unable to reassign (no eligible agent or outside working hours).', 422);
        }

        return ApiResponse::success($log->load(['assignee:id,name']), 'Lead reassigned');
    }

    public function simulate(Request $request, LeadAssignmentService $service): JsonResponse
    {
        $data = $request->validate([
            'lead_id' => 'required|integer|exists:leads,id',
        ]);

        $result = $service->simulateAssignment((int) $data['lead_id']);
        if ($result === null) {
            return ApiResponse::error('Lead not found', 404);
        }

        return ApiResponse::success($result, 'Simulation complete (no data saved)');
    }

    public function override(Request $request, LeadAssignmentService $service): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasRole('admin') && !$user->hasRole('super_admin'))) {
            return ApiResponse::error('Forbidden', 403);
        }

        $data = $request->validate([
            'lead_id' => 'required|integer|exists:leads,id',
            'assigned_to' => 'required|integer|exists:users,id',
        ]);

        $log = $service->adminOverrideAssign((int) $data['lead_id'], (int) $data['assigned_to']);
        if (!$log) {
            return ApiResponse::error('Unable to override (lead not found, target is not sales, or assigned stage missing).', 422);
        }

        return ApiResponse::success($log->load(['assignee:id,name', 'lead:id,lead_number,lead_name']), 'Lead owner updated');
    }

    public function revertStageAssignments(Request $request, LeadAssignmentService $service): JsonResponse
    {
        $data = $request->validate([
            'stage_id' => 'required|integer|exists:stages,id',
        ]);

        $stats = $service->revertStageAssignments((int) $data['stage_id']);

        return ApiResponse::success($stats, 'Stage assignments reverted');
    }
}
