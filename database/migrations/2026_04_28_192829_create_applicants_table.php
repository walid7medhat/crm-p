<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('job_openings')->onDelete('cascade');
            $table->string('full_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('nationality')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            
            // Application Details
            $table->enum('visa_status', ['resident', 'visit', 'sponsorship', 'none'])->nullable();
            $table->integer('notice_period_days')->nullable();
            $table->string('total_experience_years')->nullable();
            $table->string('experience_in_uae_years')->nullable();
            $table->decimal('current_salary', 12, 2)->nullable();
            $table->decimal('expected_salary', 12, 2)->nullable();
            
            // Documents
            $table->string('resume_path')->nullable();
            $table->string('cover_letter_path')->nullable();
            $table->text('additional_notes')->nullable();
            
            // Answers to custom questions
            $table->json('answers')->nullable();
            
            // Status
            $table->enum('status', ['pending', 'shortlisted', 'interview', 'hired', 'rejected', 'withdrawn'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('applied_at')->useCurrent();
            $table->timestamps();
            
            $table->unique(['job_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicants');
    }
};