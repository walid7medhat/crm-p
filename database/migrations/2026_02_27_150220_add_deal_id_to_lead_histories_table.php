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
        Schema::table('lead_histories', function (Blueprint $table) {
            //
                $table->foreignId('deal_id')
                ->nullable()
                ->after('lead_id')
                ->constrained('deals')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_histories', function (Blueprint $table) {
            //
            $table->dropForeign(['deal_id']);
            $table->dropColumn('deal_id');
        });
    }
};
