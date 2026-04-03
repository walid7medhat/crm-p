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
        Schema::table('leads', function (Blueprint $table) {
            //
               $table->enum('lead_type', ['sale', 'rent'])->nullable();
            $table->enum('property_status', ['ready', 'off_plan', 'both'])->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            //
              $table->dropColumn('lead_type');
            $table->dropColumn('property_status');
        });
    }
};
