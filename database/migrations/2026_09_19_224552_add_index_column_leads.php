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
        //
        Schema::table('leads', function (Blueprint $t) {
            $t->index(['responsible_person_id', 'stage_id', 'interaction_result'], 'leads_analytics_idx');
            $t->index(['responsible_person_id', 'status_lead'], 'leads_heat_idx');
            $t->index('work_phone', 'leads_work_phone_idx');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
