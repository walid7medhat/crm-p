<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public photoshoot bookings: anyone (no login) can request a date + time; the marketing
 * team follows up from the Marketing → Bookings tab.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 40);
            $table->string('shoot_type', 20); // photo | video | photo_video | drone
            $table->date('booking_date')->index();
            $table->time('booking_time');
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('new')->index(); // new | contacted | confirmed | cancelled
            $table->text('internal_note')->nullable();
            $table->foreignId('task_id')->nullable()->constrained('marketing_tasks')->nullOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('status_changed_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_bookings');
    }
};
