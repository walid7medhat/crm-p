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
        Schema::table('lead_activities', function (Blueprint $table) {
            //
            $table->json('reminders')->nullable()
        ->comment('Reminder offsets in minutes before activity time');

            $table->timestamp('next_reminder_at')->nullable()->index();
        
            $table->timestamp('last_reminded_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_activities', function (Blueprint $table) {
            //
        });
    }
};
