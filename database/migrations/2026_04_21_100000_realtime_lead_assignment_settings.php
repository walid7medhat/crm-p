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
            if (!Schema::hasColumn('lead_assignment_settings', 'realtime_assignment_enabled')) {
                $table->boolean('realtime_assignment_enabled')->default(false)->after('system_disabled');
            }
            if (!Schema::hasColumn('lead_assignment_settings', 'realtime_interval_seconds')) {
                $table->unsignedTinyInteger('realtime_interval_seconds')->default(3)->after('realtime_assignment_enabled');
            }
            if (!Schema::hasColumn('lead_assignment_settings', 'realtime_last_assigned_user_id')) {
                $table->unsignedBigInteger('realtime_last_assigned_user_id')->nullable()->after('realtime_interval_seconds');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('lead_assignment_settings')) {
            return;
        }

        Schema::table('lead_assignment_settings', function (Blueprint $table) {
            if (Schema::hasColumn('lead_assignment_settings', 'realtime_last_assigned_user_id')) {
                $table->dropColumn('realtime_last_assigned_user_id');
            }
            if (Schema::hasColumn('lead_assignment_settings', 'realtime_interval_seconds')) {
                $table->dropColumn('realtime_interval_seconds');
            }
            if (Schema::hasColumn('lead_assignment_settings', 'realtime_assignment_enabled')) {
                $table->dropColumn('realtime_assignment_enabled');
            }
        });
    }
};
