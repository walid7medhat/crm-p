<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_openings', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('requirements')->nullable();
            $table->text('skills')->nullable(); 
            $table->foreignId('department_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('branch_id')->nullable()->constrained('company_branches')->onDelete('set null');
            $table->foreignId('hiring_manager_id')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('job_type', ['full_time', 'part_time', 'contract', 'internship', 'remote'])->default('full_time');
            $table->enum('status', ['draft', 'open', 'on_hold', 'closed', 'cancelled'])->default('draft');
            $table->integer('openings')->default(1);
            $table->date('posted_date')->nullable();
            $table->date('closing_date')->nullable();
            $table->json('custom_questions')->nullable(); 
            $table->json('required_documents')->nullable(); 
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_openings');
    }
};