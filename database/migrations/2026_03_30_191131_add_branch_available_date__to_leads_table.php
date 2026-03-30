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
            // Stage 5: Future - Available Date
            $table->date('available_date')->nullable()->after('status_lead');
            
            // Stage 7: Shared - Branch
            $table->string('branch', 100)->nullable()->after('available_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'available_date',
                'branch'
            ]);
        });
    }
};