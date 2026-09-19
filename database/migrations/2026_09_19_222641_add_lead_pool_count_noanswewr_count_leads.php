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
            $t->unsignedTinyInteger('no_answer_count')->default(0)->after('interaction_result');
            $t->unsignedTinyInteger('lead_pool_visits')->default(0)->after('no_answer_count');
            $t->timestamp('archived_at')->nullable()->after('lead_pool_visits');
            $t->softDeletes();
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
