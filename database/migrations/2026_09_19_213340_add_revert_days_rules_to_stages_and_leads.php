<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stages', fn (Blueprint $t) =>
            $t->json('status_revert_rules')->nullable()->after('notification_times')
        );

        Schema::table('leads', function (Blueprint $t) {
            $t->timestamp('last_engagement_at')->nullable()->after('last_stage_change_at');
            $t->index(['stage_id', 'last_engagement_at'], 'leads_stage_engagement_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stages_and_leads', function (Blueprint $table) {
            //
        });
    }
};
