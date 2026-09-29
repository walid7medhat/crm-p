<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->json('audiences');
            $table->unsignedTinyInteger('frequency_hours');
            $table->boolean('is_active')->default(true);
            $table->string('desktop_image_path');
            $table->string('mobile_image_path');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('is_active');
        });

        Schema::create('system_campaign_impressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_campaign_id')->constrained('system_campaigns')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('shown_at');
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->unique(['system_campaign_id', 'user_id']);
            $table->index(['user_id', 'shown_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_campaign_impressions');
        Schema::dropIfExists('system_campaigns');
    }
};
