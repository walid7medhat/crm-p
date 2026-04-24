<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_settings')) {
            Schema::table('attendance_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('attendance_settings', 'department_ids')) {
                    $table->json('department_ids')->nullable()->after('end_time');
                }
            });
        }

        if (Schema::hasTable('attendance_checkin_settings')) {
            Schema::table('attendance_checkin_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('attendance_checkin_settings', 'active_department_ids')) {
                    $table->json('active_department_ids')->nullable()->after('window_end');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('attendance_settings')) {
            Schema::table('attendance_settings', function (Blueprint $table) {
                if (Schema::hasColumn('attendance_settings', 'department_ids')) {
                    $table->dropColumn('department_ids');
                }
            });
        }

        if (Schema::hasTable('attendance_checkin_settings')) {
            Schema::table('attendance_checkin_settings', function (Blueprint $table) {
                if (Schema::hasColumn('attendance_checkin_settings', 'active_department_ids')) {
                    $table->dropColumn('active_department_ids');
                }
            });
        }
    }
};

