<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remembers which stage a lead was last sent to the Zapier webhook in, so a lead
 * is sent once per stage it enters (and the backfill can be re-run safely).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (! Schema::hasColumn('leads', 'zapier_sent_stage_id')) {
                $table->unsignedBigInteger('zapier_sent_stage_id')->nullable();
            }
            if (! Schema::hasColumn('leads', 'zapier_sent_at')) {
                $table->timestamp('zapier_sent_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['zapier_sent_stage_id', 'zapier_sent_at']);
        });
    }
};
