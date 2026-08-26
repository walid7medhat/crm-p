<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadAssignmentSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'auto_assign' => 'boolean',
        'system_disabled' => 'boolean',
        'stuck_recovery_enabled' => 'boolean',
        'schedule_times' => 'array',
        'working_hours' => 'array',
        'require_attendance' => 'boolean',
        'weight_attendance' => 'float',
        'weight_performance' => 'float',
        'weight_availability' => 'float',
        'weight_fairness' => 'float',
        'stuck_lead_minutes' => 'integer',
        'assign_cooldown_minutes' => 'integer',
        'high_priority_score_threshold' => 'integer',
        'sla_minutes' => 'integer',
        'sla_escalation_enabled' => 'boolean',
        'fallback_user_id' => 'integer',
        'priority_sales_user_ids' => 'array',
        'exploration_epsilon' => 'float',
        'cold_start_max_samples' => 'integer',
        'cold_start_explore_ratio' => 'float',
        'adaptive_weights_enabled' => 'boolean',
        'factor_weight_attendance' => 'float',
        'factor_weight_performance' => 'float',
        'factor_weight_skill' => 'float',
        'realtime_assignment_enabled' => 'boolean',
        'realtime_interval_seconds' => 'integer',
        'realtime_last_assigned_user_id' => 'integer',
        'realtime_rotation_index' => 'integer',
        'realtime_last_run_at' => 'datetime',
        'realtime_last_tick_duration_ms' => 'integer',
        'realtime_last_queue_depth' => 'integer',
        'realtime_active_sales_count' => 'integer',
        'realtime_last_interval_applied' => 'integer',
        'realtime_last_tick_assigned' => 'integer',
        'realtime_status' => 'string',
        'simple_mode_enabled' => 'boolean',
        'simple_rotation_index' => 'integer',
        'simple_mode_batch_size' => 'integer',
        'simple_mode_auto_interval_seconds' => 'integer',
    ];

    public static function current(): self
    {
        $row = static::query()->orderBy('id')->first();
        if ($row) {
            return $row;
        }

        return static::query()->create([
            'auto_assign' => false,
            'system_disabled' => false,
            'mode' => 'manual',
            'strategy' => 'ai_hybrid',
            'schedule_times' => ['09:30', '14:00'],
            'max_leads_per_user' => 25,
            'working_hours' => [
                'start' => '09:00',
                'end' => '18:00',
                'timezone' => 'Asia/Dubai',
            ],
            'weight_attendance' => 0.35,
            'weight_performance' => 0.30,
            'weight_availability' => 0.20,
            'weight_fairness' => 0.15,
            'require_attendance' => true,
            'stuck_recovery_enabled' => false,
            'stuck_lead_minutes' => 120,
            'assign_cooldown_minutes' => 10,
            'high_priority_score_threshold' => 70,
            'sla_minutes' => 1440,
            'sla_escalation_enabled' => true,
            'fallback_user_id' => null,
            'priority_sales_user_ids' => [],
            'exploration_epsilon' => 0.1,
            'cold_start_max_samples' => 8,
            'cold_start_explore_ratio' => 0.15,
            'adaptive_weights_enabled' => true,
            'factor_weight_attendance' => 0.3333,
            'factor_weight_performance' => 0.3333,
            'factor_weight_skill' => 0.3334,
            'realtime_assignment_enabled' => false,
            'realtime_interval_seconds' => 3,
            'realtime_last_assigned_user_id' => null,
            'realtime_rotation_index' => 0,
            'realtime_last_run_at' => null,
            'realtime_status' => 'stopped',
            'realtime_last_tick_assigned' => 0,
            'realtime_last_tick_duration_ms' => null,
            'realtime_last_queue_depth' => 0,
            'realtime_active_sales_count' => 0,
            'realtime_last_interval_applied' => null,
            'simple_mode_enabled' => true,
            'simple_rotation_index' => 0,
            'simple_mode_batch_size' => 25,
            'simple_mode_auto_interval_seconds' => 10,
            'simple_last_assignment_label' => null,
        ]);
    }

    public function assignedStage(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Stage::class, 'assigned_stage_id');
    }

    public function fallbackUser(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'fallback_user_id');
    }
}
