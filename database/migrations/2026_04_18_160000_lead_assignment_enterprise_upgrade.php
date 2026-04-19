<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lead_assignment_logs')) {
            Schema::table('lead_assignment_logs', function (Blueprint $table) {
                if (!Schema::hasColumn('lead_assignment_logs', 'attendance_score')) {
                    $table->decimal('attendance_score', 10, 4)->nullable()->after('score_used');
                }
                if (!Schema::hasColumn('lead_assignment_logs', 'performance_score')) {
                    $table->decimal('performance_score', 10, 4)->nullable()->after('attendance_score');
                }
                if (!Schema::hasColumn('lead_assignment_logs', 'load_score')) {
                    $table->decimal('load_score', 10, 4)->nullable()->after('performance_score');
                }
                if (!Schema::hasColumn('lead_assignment_logs', 'fairness_score')) {
                    $table->decimal('fairness_score', 10, 4)->nullable()->after('load_score');
                }
                if (!Schema::hasColumn('lead_assignment_logs', 'explanation')) {
                    $table->json('explanation')->nullable()->after('fairness_score');
                }
            });
        }

        if (Schema::hasTable('lead_assignment_settings')) {
            Schema::table('lead_assignment_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('lead_assignment_settings', 'stuck_recovery_enabled')) {
                    $table->boolean('stuck_recovery_enabled')->default(false)->after('require_attendance');
                }
                if (!Schema::hasColumn('lead_assignment_settings', 'stuck_lead_minutes')) {
                    $table->unsignedSmallInteger('stuck_lead_minutes')->default(120)->after('stuck_recovery_enabled');
                }
                if (!Schema::hasColumn('lead_assignment_settings', 'assign_cooldown_minutes')) {
                    $table->unsignedSmallInteger('assign_cooldown_minutes')->default(10)->after('stuck_lead_minutes');
                }
                if (!Schema::hasColumn('lead_assignment_settings', 'high_priority_score_threshold')) {
                    $table->unsignedTinyInteger('high_priority_score_threshold')->default(70)->after('assign_cooldown_minutes');
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'last_lead_assigned_at')) {
                    $table->timestamp('last_lead_assigned_at')->nullable()->after('remember_token');
                }
                if (!Schema::hasColumn('users', 'lead_assign_daily_count')) {
                    $table->unsignedSmallInteger('lead_assign_daily_count')->default(0)->after('last_lead_assigned_at');
                }
                if (!Schema::hasColumn('users', 'lead_assign_count_date')) {
                    $table->date('lead_assign_count_date')->nullable()->after('lead_assign_daily_count');
                }
            });
        }

        if (Schema::hasTable('sales_performance')) {
            Schema::table('sales_performance', function (Blueprint $table) {
                if (!Schema::hasColumn('sales_performance', 'conversion_rate')) {
                    $table->decimal('conversion_rate', 6, 2)->nullable()->after('deals_closed');
                }
                if (!Schema::hasColumn('sales_performance', 'deals_total')) {
                    $table->unsignedInteger('deals_total')->default(0)->after('conversion_rate');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('lead_assignment_logs')) {
            Schema::table('lead_assignment_logs', function (Blueprint $table) {
                $table->dropColumn(['attendance_score', 'performance_score', 'load_score', 'fairness_score', 'explanation']);
            });
        }
        if (Schema::hasTable('lead_assignment_settings')) {
            Schema::table('lead_assignment_settings', function (Blueprint $table) {
                $table->dropColumn([
                    'stuck_recovery_enabled',
                    'stuck_lead_minutes',
                    'assign_cooldown_minutes',
                    'high_priority_score_threshold',
                ]);
            });
        }
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['last_lead_assigned_at', 'lead_assign_daily_count', 'lead_assign_count_date']);
            });
        }
        if (Schema::hasTable('sales_performance')) {
            Schema::table('sales_performance', function (Blueprint $table) {
                $table->dropColumn(['conversion_rate', 'deals_total']);
            });
        }
    }
};
