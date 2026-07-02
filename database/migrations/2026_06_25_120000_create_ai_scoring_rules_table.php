<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_scoring_rules', function (Blueprint $table) {
            $table->id();
            $table->string('factor_key', 64)->unique();
            $table->string('label');
            $table->string('rule_group', 32)->default('overall');
            $table->decimal('weight', 8, 4)->default(0);
            $table->json('thresholds')->nullable();
            $table->string('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        $defaults = [
            // Overall AI score composition
            ['factor_key' => 'overall_behavior', 'label' => 'Behavior Score', 'rule_group' => 'overall', 'weight' => 0.35, 'sort_order' => 1, 'description' => 'Composite behavior across response, follow-up, pipeline'],
            ['factor_key' => 'overall_pipeline', 'label' => 'Pipeline Score', 'rule_group' => 'overall', 'weight' => 0.15, 'sort_order' => 2, 'description' => 'Stage discipline and pipeline cleanliness'],
            ['factor_key' => 'overall_followup', 'label' => 'Follow-up Score', 'rule_group' => 'overall', 'weight' => 0.15, 'sort_order' => 3, 'description' => 'Reminder completion and overdue follow-ups'],
            ['factor_key' => 'overall_qualification', 'label' => 'Qualification Score', 'rule_group' => 'overall', 'weight' => 0.10, 'sort_order' => 4, 'description' => 'Qualified rate and qualified-to-deal conversion'],
            ['factor_key' => 'overall_communication', 'label' => 'Communication Score', 'rule_group' => 'overall', 'weight' => 0.10, 'sort_order' => 5, 'description' => 'Comments, activities, call outcomes'],
            ['factor_key' => 'overall_conversion', 'label' => 'Conversion Score', 'rule_group' => 'overall', 'weight' => 0.10, 'sort_order' => 6, 'description' => 'Deals won vs lost'],
            ['factor_key' => 'overall_neglect', 'label' => 'Neglect Score', 'rule_group' => 'overall', 'weight' => 0.05, 'sort_order' => 7, 'description' => 'Inverse of neglected leads'],
            // Behavior sub-score
            ['factor_key' => 'behavior_response', 'label' => 'Response Speed', 'rule_group' => 'behavior', 'weight' => 0.20, 'sort_order' => 10],
            ['factor_key' => 'behavior_followup', 'label' => 'Follow-up Discipline', 'rule_group' => 'behavior', 'weight' => 0.20, 'sort_order' => 11],
            ['factor_key' => 'behavior_pipeline', 'label' => 'Pipeline Movement', 'rule_group' => 'behavior', 'weight' => 0.15, 'sort_order' => 12],
            ['factor_key' => 'behavior_communication', 'label' => 'Communication', 'rule_group' => 'behavior', 'weight' => 0.15, 'sort_order' => 13],
            ['factor_key' => 'behavior_qualification', 'label' => 'Qualification', 'rule_group' => 'behavior', 'weight' => 0.15, 'sort_order' => 14],
            ['factor_key' => 'behavior_neglect', 'label' => 'Neglect (inverse)', 'rule_group' => 'behavior', 'weight' => 0.15, 'sort_order' => 15],
            // Status thresholds
            ['factor_key' => 'status_excellent', 'label' => 'Excellent', 'rule_group' => 'status', 'weight' => 85, 'sort_order' => 20, 'description' => 'Minimum score for Excellent status'],
            ['factor_key' => 'status_good', 'label' => 'Good', 'rule_group' => 'status', 'weight' => 70, 'sort_order' => 21, 'description' => 'Minimum score for Good status'],
            ['factor_key' => 'status_needs_attention', 'label' => 'Needs Attention', 'rule_group' => 'status', 'weight' => 50, 'sort_order' => 22, 'description' => 'Minimum score before Critical'],
            // Risk thresholds
            ['factor_key' => 'risk_high', 'label' => 'High Risk', 'rule_group' => 'risk', 'weight' => 70, 'sort_order' => 30],
            ['factor_key' => 'risk_medium', 'label' => 'Medium Risk', 'rule_group' => 'risk', 'weight' => 40, 'sort_order' => 31],
            // Response SLA (minutes => score)
            ['factor_key' => 'response_sla', 'label' => 'First Activity SLA', 'rule_group' => 'response_sla', 'weight' => 0, 'sort_order' => 40, 'thresholds' => json_encode([
                ['minutes' => 15, 'score' => 95],
                ['minutes' => 30, 'score' => 85],
                ['minutes' => 60, 'score' => 75],
                ['minutes' => 120, 'score' => 60],
                ['minutes' => 240, 'score' => 45],
                ['minutes' => 1440, 'score' => 30],
                ['minutes' => 99999, 'score' => 15],
            ])],
        ];

        foreach ($defaults as $row) {
            DB::table('ai_scoring_rules')->insert(array_merge($row, [
                'thresholds' => $row['thresholds'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_scoring_rules');
    }
};
