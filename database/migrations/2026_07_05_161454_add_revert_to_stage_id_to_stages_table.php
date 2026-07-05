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
        Schema::table('stages', function (Blueprint $table) {
                $table->foreignId('revert_to_stage_id')->nullable()->constrained('stages')->onDelete('set null');
                $table->text('revert_notification_message')->nullable();
                $table->json('notification_times')->nullable(); 
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stages', function (Blueprint $table) {
            //
        });
    }
};
