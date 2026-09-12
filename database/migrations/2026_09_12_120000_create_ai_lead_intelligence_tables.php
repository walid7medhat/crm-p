<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_lead_intelligence_runs', function (Blueprint $table) {
            $table->id();
            $table->string('status', 32)->default('queued')->index();
            $table->unsignedBigInteger('triggered_by')->nullable()->index();
            $table->unsignedInteger('total_eligible')->default(0);
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('skipped_unchanged')->default(0);
            $table->unsignedInteger('failed_leads')->default(0);
            $table->unsignedInteger('batch_size')->default(40);
            $table->unsignedBigInteger('cursor_after_id')->default(0);
            $table->json('overview')->nullable();
            $table->json('dashboard_payload')->nullable();
            $table->string('error_message', 1000)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_lead_intelligence_lead_contexts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->unique();
            $table->string('fingerprint', 64)->index();
            $table->string('urgency_bucket', 32)->nullable()->index();
            $table->boolean('is_high_priority')->default(false)->index();
            $table->boolean('is_at_risk')->default(false)->index();
            $table->boolean('is_neglected')->default(false)->index();
            $table->boolean('requires_action_today')->default(false)->index();
            $table->unsignedSmallInteger('matching_listings_count')->default(0);
            $table->json('dashboard_card')->nullable();
            $table->json('context')->nullable();
            $table->unsignedBigInteger('last_run_id')->nullable()->index();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();

            $table->foreign('lead_id')->references('id')->on('leads')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_lead_intelligence_lead_contexts');
        Schema::dropIfExists('ai_lead_intelligence_runs');
    }
};
