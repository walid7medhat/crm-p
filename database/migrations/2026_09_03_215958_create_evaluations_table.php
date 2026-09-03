<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('evaluator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('milestone_months');
            $table->unsignedInteger('period_number');
            $table->string('department_name_snapshot')->nullable();
            $table->enum('status', ['pending', 'submitted'])->default('pending');
            $table->timestamp('submitted_at')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'period_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
