<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scoring_rules', function (Blueprint $table) {
            $table->id();
            $table->string('factor_name', 64)->unique();
            $table->decimal('weight', 8, 4)->default(0.1667);
            $table->decimal('low_value', 14, 4)->nullable();
            $table->decimal('medium_value', 14, 4)->nullable();
            $table->decimal('high_value', 14, 4)->nullable();
            $table->string('direction', 16)->default('higher_better'); // higher_better | lower_better
            $table->timestamps();
        });

        Schema::create('agent_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('conversion_rate', 8, 4)->default(0);
            $table->decimal('avg_response_time', 12, 2)->nullable()->comment('Hours; lower is better');
            $table->decimal('revenue', 16, 2)->default(0);
            $table->unsignedInteger('deals_won')->default(0);
            $table->unsignedInteger('deals_lost')->default(0);
            $table->unsignedInteger('activity_count')->default(0);
            $table->decimal('follow_up_score', 8, 4)->default(0)->comment('0-100 on-time follow-up quality');
            $table->decimal('closing_speed', 10, 2)->nullable()->comment('Avg days lead->closed deal; lower is better');
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->index('computed_at');
        });

        Schema::create('agent_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 8, 2)->default(0);
            $table->string('tier', 16)->default('cold')->comment('hot|warm|cold');
            $table->json('breakdown')->nullable();
            $table->timestamp('calculated_at')->useCurrent();

            $table->index(['user_id', 'calculated_at']);
            $table->index('calculated_at');
        });

        Schema::create('lead_distribution_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
            $table->decimal('score_at_assignment', 8, 2)->nullable();
            $table->string('method', 64);
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['lead_id', 'created_at']);
            $table->index(['assigned_to', 'created_at']);
            $table->index('method');
        });

        Schema::create('sales_intelligence_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('max_leads_per_agent_per_day')->default(15);
            $table->string('distribution_mode', 32)->default('weighted');
            $table->string('ai_mode', 32)->default('hybrid');
            $table->boolean('require_attendance')->default(true);
            $table->unsignedSmallInteger('metrics_lookback_days')->default(90);
            $table->unsignedBigInteger('round_robin_last_user_id')->nullable();
            $table->json('automation_flags')->nullable();
            $table->timestamps();
        });

        DB::table('sales_intelligence_settings')->insert([
            'max_leads_per_agent_per_day' => 15,
            'distribution_mode' => 'weighted',
            'ai_mode' => 'hybrid',
            'require_attendance' => true,
            'metrics_lookback_days' => 90,
            'round_robin_last_user_id' => null,
            'automation_flags' => json_encode([
                'recalculate_on_deal_close' => true,
                'recalculate_on_activity' => true,
                'auto_assign_new_leads' => false,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $now = now();
        $defaults = [
            [
                'factor_name' => 'conversion_rate',
                'weight' => 0.22,
                'low_value' => 5,
                'medium_value' => 12,
                'high_value' => 25,
                'direction' => 'higher_better',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'factor_name' => 'response_speed',
                'weight' => 0.18,
                'low_value' => 72,
                'medium_value' => 24,
                'high_value' => 4,
                'direction' => 'lower_better',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'factor_name' => 'revenue_performance',
                'weight' => 0.20,
                'low_value' => 0,
                'medium_value' => 500000,
                'high_value' => 2000000,
                'direction' => 'higher_better',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'factor_name' => 'activity_level',
                'weight' => 0.15,
                'low_value' => 10,
                'medium_value' => 35,
                'high_value' => 80,
                'direction' => 'higher_better',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'factor_name' => 'follow_up_discipline',
                'weight' => 0.15,
                'low_value' => 40,
                'medium_value' => 65,
                'high_value' => 85,
                'direction' => 'higher_better',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'factor_name' => 'closing_efficiency',
                'weight' => 0.10,
                'low_value' => 120,
                'medium_value' => 60,
                'high_value' => 25,
                'direction' => 'lower_better',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];
        DB::table('scoring_rules')->insert($defaults);
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_distribution_logs');
        Schema::dropIfExists('agent_scores');
        Schema::dropIfExists('agent_metrics');
        Schema::dropIfExists('scoring_rules');
        Schema::dropIfExists('sales_intelligence_settings');
    }
};
