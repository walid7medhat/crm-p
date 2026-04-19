<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('lead_assignment_settings')) {
            Schema::create('lead_assignment_settings', function (Blueprint $table) {
                $table->id();
                $table->boolean('auto_assign')->default(false);
                $table->boolean('system_disabled')->default(false);
                $table->string('mode', 32)->default('manual'); // realtime, scheduled, manual
                $table->string('strategy', 48)->default('ai_hybrid'); // ai_hybrid, attendance_priority, performance, round_robin
                $table->json('schedule_times')->nullable();
                $table->unsignedSmallInteger('max_leads_per_user')->default(25);
                $table->json('working_hours')->nullable();
                $table->foreignId('assigned_stage_id')->nullable()->constrained('stages')->nullOnDelete();
                $table->unsignedBigInteger('round_robin_cursor_user_id')->nullable();
                $table->decimal('weight_attendance', 5, 2)->default(0.35);
                $table->decimal('weight_performance', 5, 2)->default(0.30);
                $table->decimal('weight_availability', 5, 2)->default(0.20);
                $table->decimal('weight_fairness', 5, 2)->default(0.15);
                $table->boolean('require_attendance')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('lead_assignment_logs')) {
            Schema::create('lead_assignment_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
                $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
                $table->decimal('score_used', 10, 4)->nullable();
                $table->text('reason')->nullable();
                $table->string('method', 16)->default('auto'); // auto, manual
                $table->timestamps();

                $table->index(['lead_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('sales_performance')) {
            Schema::create('sales_performance', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sales_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->decimal('score', 10, 4)->default(0);
                $table->unsignedInteger('deals_closed')->default(0);
                $table->decimal('response_time', 10, 2)->nullable()->comment('Average first response in minutes');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_assignment_logs');
        Schema::dropIfExists('sales_performance');
        Schema::dropIfExists('lead_assignment_settings');
    }
};
