<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained()->onDelete('cascade');
            $table->foreignId('job_id')->constrained('job_openings')->onDelete('cascade');
            $table->foreignId('interviewer_id')->constrained('users')->onDelete('cascade');
            $table->datetime('scheduled_at');
            $table->datetime('end_time')->nullable();
            $table->enum('type', ['online', 'in_person', 'phone'])->default('in_person');
            $table->string('location')->nullable();
            $table->text('meeting_link')->nullable();
            $table->text('feedback')->nullable();
            $table->integer('rating')->nullable(); // 1-5
            $table->enum('status', ['scheduled', 'completed', 'cancelled', 'no_show'])->default('scheduled');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interviews');
    }
};