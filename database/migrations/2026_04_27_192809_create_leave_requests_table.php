<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Employee
            $table->foreignId('leave_type_id')->constrained('leave_types')->onDelete('restrict');
            $table->foreignId('parent_id')->nullable()->constrained('users')->onDelete('set null'); // Direct manager
            $table->foreignId('hr_id')->nullable()->constrained('users')->onDelete('set null'); // HR who approved
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('days'); // number of days
            $table->boolean('is_half_day')->default(false);
            $table->enum('half_day_type', ['morning', 'afternoon'])->nullable();
            $table->text('reason')->nullable();
            $table->string('attachment')->nullable(); // file path
            $table->enum('status', ['pending_parent', 'pending_hr', 'approved', 'rejected', 'cancelled'])->default('pending_parent');
            $table->text('parent_rejection_reason')->nullable();
            $table->text('hr_rejection_reason')->nullable();
            $table->timestamp('parent_approved_at')->nullable();
            $table->timestamp('hr_approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};