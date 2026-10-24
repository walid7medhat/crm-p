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
            Schema::table('leads', function (Blueprint $t) {
                $t->unsignedBigInteger('merged_into_lead_id')->nullable()->index();
                $t->timestamp('merged_at')->nullable();
                $t->unsignedBigInteger('merged_by')->nullable();            // null = automatic
                $t->unsignedBigInteger('merged_original_stage_id')->nullable();
                $t->string('merge_type', 20)->nullable();                   // pool_auto | manual
                $t->foreign('merged_into_lead_id')->references('id')->on('leads')->restrictOnDelete();
            });
        }
        public function down(): void
        {
            Schema::table('leads', function (Blueprint $t) {
                $t->dropForeign(['merged_into_lead_id']);
                $t->dropColumn(['merged_into_lead_id','merged_at','merged_by','merged_original_stage_id','merge_type']);
            });
        }
};
