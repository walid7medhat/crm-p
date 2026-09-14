<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_pool_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('lead_id')
                ->constrained('leads')
                ->cascadeOnDelete();

            $table->uuid('batch_id');

            $table->timestamp('assigned_at');

            $table->timestamps();

            $table->index(['user_id', 'assigned_at']);
            $table->index(['user_id', 'batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_pool_assignments');
    }
};