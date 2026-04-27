<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Employee
            $table->foreignId('assigned_by')->constrained('users')->onDelete('cascade'); // HR/IT who assigned
            $table->date('handover_date');
            $table->date('return_date')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['active', 'returned'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_assignments');
    }
};