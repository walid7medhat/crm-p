<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_intelligence_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32)->index();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('provider', 32)->nullable();
            $table->string('status_message', 500)->nullable();
            $table->json('queries_meta')->nullable();
            $table->json('report')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('fresh_until')->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'status']);
            $table->index(['lead_id', 'created_at']);
        });

        Schema::create('lead_intelligence_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained('lead_intelligence_scans')->cascadeOnDelete();
            $table->string('title', 180);
            $table->string('url', 500);
            $table->string('tier', 32);
            $table->json('reasons')->nullable();
            $table->json('uncertainties')->nullable();
            $table->string('excerpt', 300)->nullable();
            $table->timestamps();

            $table->index(['scan_id', 'tier']);
        });

        Schema::create('lead_intelligence_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained('lead_intelligence_scans')->cascadeOnDelete();
            $table->foreignId('candidate_id')->nullable()->constrained('lead_intelligence_candidates')->nullOnDelete();
            $table->string('title', 180);
            $table->string('url', 500);
            $table->string('source_type', 32);
            $table->string('excerpt', 300)->nullable();
            $table->string('published_at', 40)->nullable();
            $table->timestamp('retrieved_at')->nullable();
            $table->string('origin', 32)->default('extracted');
            $table->timestamps();

            $table->index('scan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_intelligence_sources');
        Schema::dropIfExists('lead_intelligence_candidates');
        Schema::dropIfExists('lead_intelligence_scans');
    }
};
