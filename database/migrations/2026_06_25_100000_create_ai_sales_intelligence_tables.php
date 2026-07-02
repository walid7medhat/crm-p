<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_sales_intelligence_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('metrics_lookback_days')->default(90);
            $table->unsignedSmallInteger('neglect_inactive_days')->default(7);
            $table->unsignedSmallInteger('stuck_follow_up_days')->default(10);
            $table->json('response_sla_minutes')->nullable();
            $table->json('automation_flags')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_agent_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('overall_ai_score', 8, 2)->default(0);
            $table->string('status', 24)->default('needs_attention');
            $table->string('risk_level', 16)->default('medium');
            $table->decimal('pipeline_score', 8, 2)->default(0);
            $table->decimal('response_score', 8, 2)->default(0);
            $table->decimal('followup_score', 8, 2)->default(0);
            $table->decimal('qualification_score', 8, 2)->default(0);
            $table->decimal('communication_score', 8, 2)->default(0);
            $table->decimal('discipline_score', 8, 2)->default(0);
            $table->decimal('engagement_score', 8, 2)->default(0);
            $table->decimal('neglect_score', 8, 2)->default(0);
            $table->decimal('risk_score', 8, 2)->default(0);
            $table->decimal('behavior_score', 8, 2)->default(0);
            $table->decimal('conversion_score', 8, 2)->default(0);
            $table->json('pipeline_metrics')->nullable();
            $table->json('response_metrics')->nullable();
            $table->json('followup_metrics')->nullable();
            $table->json('qualification_metrics')->nullable();
            $table->json('communication_metrics')->nullable();
            $table->json('neglect_metrics')->nullable();
            $table->json('daily_performance')->nullable();
            $table->json('weekly_trends')->nullable();
            $table->json('coaching_cards')->nullable();
            $table->text('executive_summary')->nullable();
            $table->timestamp('computed_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('ai_agent_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('overall_ai_score', 8, 2)->default(0);
            $table->string('status', 24)->default('needs_attention');
            $table->string('risk_level', 16)->default('medium');
            $table->json('breakdown')->nullable();
            $table->timestamp('calculated_at')->useCurrent()->index();
            $table->index(['user_id', 'calculated_at']);
        });

        Schema::create('ai_agent_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('stat_date');
            $table->unsignedInteger('assignments')->default(0);
            $table->unsignedInteger('contacts')->default(0);
            $table->unsignedInteger('comments')->default(0);
            $table->unsignedInteger('follow_ups_created')->default(0);
            $table->unsignedInteger('reminders_completed')->default(0);
            $table->unsignedInteger('qualified')->default(0);
            $table->unsignedInteger('converted')->default(0);
            $table->unsignedInteger('lost')->default(0);
            $table->decimal('avg_response_minutes', 10, 2)->nullable();
            $table->json('extra')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'stat_date']);
        });

        Schema::create('ai_agent_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('alert_type', 64);
            $table->string('severity', 16)->default('medium');
            $table->string('title');
            $table->text('message');
            $table->json('meta')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'is_read', 'created_at']);
        });

        Schema::create('ai_agent_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 48);
            $table->string('severity', 16)->default('info');
            $table->text('observation');
            $table->json('meta')->nullable();
            $table->timestamp('generated_at')->useCurrent();
            $table->index(['user_id', 'generated_at']);
        });

        Schema::create('ai_agent_rankings', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_date');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('overall_rank')->default(0);
            $table->unsignedSmallInteger('behavior_rank')->default(0);
            $table->unsignedSmallInteger('pipeline_rank')->default(0);
            $table->unsignedSmallInteger('followup_rank')->default(0);
            $table->unsignedSmallInteger('qualification_rank')->default(0);
            $table->unsignedSmallInteger('communication_rank')->default(0);
            $table->unsignedSmallInteger('conversion_rank')->default(0);
            $table->json('scores')->nullable();
            $table->timestamps();
            $table->unique(['snapshot_date', 'user_id']);
        });

        Schema::create('ai_agent_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('snapshot_type', 16)->default('daily');
            $table->date('period_start');
            $table->date('period_end');
            $table->json('payload');
            $table->timestamps();
            $table->index(['user_id', 'snapshot_type', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agent_snapshots');
        Schema::dropIfExists('ai_agent_rankings');
        Schema::dropIfExists('ai_agent_observations');
        Schema::dropIfExists('ai_agent_alerts');
        Schema::dropIfExists('ai_agent_daily_stats');
        Schema::dropIfExists('ai_agent_scores');
        Schema::dropIfExists('ai_agent_metrics');
        Schema::dropIfExists('ai_sales_intelligence_settings');
    }
};
