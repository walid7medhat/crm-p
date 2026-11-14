<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foreign_access_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('brand_key', 32)->index();
            $table->string('ip', 45)->nullable();
            $table->string('country')->nullable();
            $table->string('country_code', 8)->nullable()->index();
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('isp')->nullable();
            $table->string('browser', 120)->nullable();
            $table->string('operating_system', 120)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('session_key', 64)->nullable()->index();
            $table->string('detection_reason', 64);
            $table->string('status', 32)->default('new')->index(); // new | reviewed
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['brand_key', 'occurred_at']);
            $table->index(['user_id', 'occurred_at']);
            $table->index(['user_id', 'ip', 'session_key']);
            $table->index(['status', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foreign_access_events');
    }
};
