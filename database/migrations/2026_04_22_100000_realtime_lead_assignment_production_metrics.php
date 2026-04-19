<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('lead_assignment_settings')) {
            return;
        }

        Schema::table('lead_assignment_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('lead_assignment_settings', 'realtime_rotation_index')) {
                $table->unsignedInteger('realtime_rotation_index')->default(0);
            }
            if (!Schema::hasColumn('lead_assignment_settings', 'realtime_last_run_at')) {
                $table->timestamp('realtime_last_run_at')->nullable();
            }
            if (!Schema::hasColumn('lead_assignment_settings', 'realtime_status')) {
                $table->string('realtime_status', 24)->default('stopped');
            }
            if (!Schema::hasColumn('lead_assignment_settings', 'realtime_last_tick_assigned')) {
                $table->unsignedSmallInteger('realtime_last_tick_assigned')->default(0);
            }
            if (!Schema::hasColumn('lead_assignment_settings', 'realtime_last_tick_duration_ms')) {
                $table->unsignedInteger('realtime_last_tick_duration_ms')->nullable();
            }
            if (!Schema::hasColumn('lead_assignment_settings', 'realtime_last_queue_depth')) {
                $table->unsignedInteger('realtime_last_queue_depth')->default(0);
            }
            if (!Schema::hasColumn('lead_assignment_settings', 'realtime_active_sales_count')) {
                $table->unsignedSmallInteger('realtime_active_sales_count')->default(0);
            }
            if (!Schema::hasColumn('lead_assignment_settings', 'realtime_last_interval_applied')) {
                $table->unsignedTinyInteger('realtime_last_interval_applied')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('lead_assignment_settings')) {
            return;
        }

        Schema::table('lead_assignment_settings', function (Blueprint $table) {
            foreach ([
                'realtime_last_interval_applied',
                'realtime_active_sales_count',
                'realtime_last_queue_depth',
                'realtime_last_tick_duration_ms',
                'realtime_last_tick_assigned',
                'realtime_status',
                'realtime_last_run_at',
                'realtime_rotation_index',
            ] as $col) {
                if (Schema::hasColumn('lead_assignment_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
