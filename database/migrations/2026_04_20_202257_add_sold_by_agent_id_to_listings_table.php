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
        Schema::table('listings', function (Blueprint $table) {
            //
            $table->foreignId('sold_by_agent_id')
                  ->nullable()
                  ->after('sold_by')
                  ->constrained('users')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            //
               $table->dropForeign(['sold_by_agent_id']);
            $table->dropColumn('sold_by_agent_id');
        });
    }
};
