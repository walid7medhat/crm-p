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
            $table->unsignedBigInteger('previous_owner_id')->nullable()->after('owner_id');
    
            $table->foreign('previous_owner_id')
                  ->references('id')
                  ->on('owners')
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
        });
    }
};
