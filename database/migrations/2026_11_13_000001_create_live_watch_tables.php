<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_watch_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitored_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('heartbeat_interval_seconds')->default(30);
            $table->unsignedInteger('offline_timeout_seconds')->default(90);
            $table->string('current_transition_id', 64)->nullable();
            $table->timestamp('online_since')->nullable();
            $table->boolean('is_online')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('live_watch_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('session_id', 64);
            $table->string('device_label', 64)->nullable();
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(['user_id', 'session_id']);
            $table->index(['user_id', 'last_seen_at']);
        });

        Schema::create('live_watch_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('event_kind', 32); // navigation | business
            $table->string('event_type', 64);
            $table->string('module', 64)->nullable();
            $table->string('action', 64);
            $table->string('entity_type', 64)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('metadata')->nullable();
            $table->string('session_id', 64)->nullable();
            $table->string('client_event_id', 64)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique('client_event_id');
            $table->index(['user_id', 'occurred_at']);
            $table->index(['event_kind', 'occurred_at']);
            $table->index(['module', 'occurred_at']);
            $table->index(['event_type', 'occurred_at']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_watch_events');
        Schema::dropIfExists('live_watch_sessions');
        Schema::dropIfExists('live_watch_settings');
    }
};
