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
             // Add relation to unit_views table
            $table->foreignId('unit_view_id')->nullable()->constrained('unit_views')->nullOnDelete();

          
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            //
                $table->dropForeign(['unit_view_id']);
            $table->dropColumn(['unit_view_id']);
        });
    }
};
