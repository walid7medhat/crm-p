<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_location_history')) {
            return;
        }

        Schema::create('user_location_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('accuracy')->nullable();
            $table->timestamp('captured_at');
            $table->timestamp('recorded_at')->useCurrent();
            $table->string('source', 32)->default('device');
            $table->string('address')->nullable();
            $table->timestamp('session_started_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'captured_at']);
            $table->index('captured_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_location_history');
    }
};
