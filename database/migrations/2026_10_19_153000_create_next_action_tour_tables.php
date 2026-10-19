<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('next_action_tour_campaigns', function (Blueprint $table) {
            $table->id();
            $table->uuid('launch_id')->unique();
            $table->boolean('is_active')->default(false);
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->timestamps();
        });

        Schema::create('next_action_tour_user_progress', function (Blueprint $table) {
            $table->id();
            $table->uuid('launch_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('views')->default(0);
            $table->timestamp('last_shown_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->unique(['launch_id', 'user_id']);
            $table->index('launch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('next_action_tour_user_progress');
        Schema::dropIfExists('next_action_tour_campaigns');
    }
};
