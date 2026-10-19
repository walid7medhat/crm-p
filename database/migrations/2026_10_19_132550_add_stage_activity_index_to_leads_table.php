<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Only ~23 rows: lets us order by the plain column instead of COALESCE().
        DB::table('leads')
            ->whereNull('bitrix24_last_activity_at')
            ->update(['bitrix24_last_activity_at' => DB::raw('created_at')]);

        Schema::table('leads', function (Blueprint $table) {
            $table->index(['stage_id', 'bitrix24_last_activity_at'], 'idx_stage_activity');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('idx_stage_activity');
        });
    }
};