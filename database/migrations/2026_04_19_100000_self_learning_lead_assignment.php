<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('assignment_patterns')) {
            Schema::create('assignment_patterns', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sales_id')->constrained('users')->cascadeOnDelete();
                $table->string('lead_type', 160);
                $table->decimal('success_rate', 10, 6)->default(0.5);
                $table->decimal('avg_close_time_hours', 12, 4)->nullable();
                $table->unsignedInteger('samples')->default(0);
                $table->timestamps();
                $table->unique(['sales_id', 'lead_type']);
            });
        }

        if (!Schema::hasTable('user_skills')) {
            Schema::create('user_skills', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('skill', 120);
                $table->timestamps();
                $table->unique(['user_id', 'skill']);
                $table->index('skill');
            });
        }

        if (Schema::hasTable('lead_assignment_settings')) {
            Schema::table('lead_assignment_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('lead_assignment_settings', 'sla_minutes')) {
                    $table->unsignedInteger('sla_minutes')->default(1440);
                }
                if (!Schema::hasColumn('lead_assignment_settings', 'sla_escalation_enabled')) {
                    $table->boolean('sla_escalation_enabled')->default(true);
                }
                if (!Schema::hasColumn('lead_assignment_settings', 'fallback_user_id')) {
                    $table->foreignId('fallback_user_id')->nullable()->constrained('users')->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('leads')) {
            Schema::table('leads', function (Blueprint $table) {
                if (!Schema::hasColumn('leads', 'first_contacted_at')) {
                    $table->timestamp('first_contacted_at')->nullable()->after('last_stage_change_at');
                }
                if (!Schema::hasColumn('leads', 'assignment_hold')) {
                    $table->boolean('assignment_hold')->default(false)->after('first_contacted_at');
                }
                if (!Schema::hasColumn('leads', 'assignment_hold_reason')) {
                    $table->string('assignment_hold_reason', 255)->nullable()->after('assignment_hold');
                }
                if (!Schema::hasColumn('leads', 'last_sla_escalation_at')) {
                    $table->timestamp('last_sla_escalation_at')->nullable()->after('assignment_hold_reason');
                }
            });
        }

    }

    public function down(): void
    {
        if (Schema::hasTable('leads')) {
            Schema::table('leads', function (Blueprint $table) {
                foreach (['first_contacted_at', 'assignment_hold', 'assignment_hold_reason', 'last_sla_escalation_at'] as $col) {
                    if (Schema::hasColumn('leads', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
        if (Schema::hasTable('lead_assignment_settings')) {
            Schema::table('lead_assignment_settings', function (Blueprint $table) {
                if (Schema::hasColumn('lead_assignment_settings', 'fallback_user_id')) {
                    try {
                        $table->dropForeign(['fallback_user_id']);
                    } catch (\Throwable) {
                        // constraint name may differ
                    }
                }
                foreach (['sla_minutes', 'sla_escalation_enabled', 'fallback_user_id'] as $col) {
                    if (Schema::hasColumn('lead_assignment_settings', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
        Schema::dropIfExists('user_skills');
        Schema::dropIfExists('assignment_patterns');
    }
};
