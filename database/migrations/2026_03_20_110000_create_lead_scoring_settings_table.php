<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('lead_scoring_settings')) {
            Schema::create('lead_scoring_settings', function (Blueprint $table) {
                $table->id();
                $table->json('weights')->nullable();
                $table->json('thresholds')->nullable();
                $table->json('automation_flags')->nullable();
                $table->string('ai_mode', 30)->default('fallback');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('lead_scoring_settings') && DB::table('lead_scoring_settings')->count() === 0) {
            DB::table('lead_scoring_settings')->insert([
                'weights' => json_encode(config('lead_scoring.weights')),
                'thresholds' => json_encode(config('lead_scoring.thresholds')),
                'automation_flags' => json_encode(config('lead_scoring.automation')),
                'ai_mode' => config('lead_scoring.ai_mode', 'fallback'),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_scoring_settings');
    }
};
