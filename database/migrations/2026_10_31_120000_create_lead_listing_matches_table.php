<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Match Studio: one row per lead + listing the matcher thinks fit together.
 * The row keeps what the agent decided (shortlisted, skipped, sent) so a match is
 * never suggested twice, and skips can teach the matcher for that lead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_listing_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            // {"fits": ["Budget", "Al Reem", "2 BR"], "gaps": ["3% over budget"]}
            $table->json('reasons')->nullable();
            // new | shortlisted | skipped | sent
            $table->string('status', 20)->default('new');
            // new | price_drop | revive: why this match surfaced
            $table->string('bucket', 20)->default('new');
            $table->decimal('matched_price', 15, 2)->nullable();
            $table->decimal('previous_price', 15, 2)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['lead_id', 'listing_id']);
            $table->index(['lead_id', 'status']);
            $table->index(['status', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_listing_matches');
    }
};
