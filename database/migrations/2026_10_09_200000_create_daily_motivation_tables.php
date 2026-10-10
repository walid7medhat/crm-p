<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motivation_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(false);
            $table->date('anchor_date')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('motivation_settings')->insert([
            'id' => 1,
            'is_enabled' => false,
            'anchor_date' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('motivation_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('number')->unique();
            $table->text('body_en');
            $table->text('body_ar');
            $table->string('subtitle_en', 180)->nullable();
            $table->string('subtitle_ar', 180)->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('motivation_user_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rotation_offset');
            $table->boolean('offset_shared')->default(false);
            // Null when the slot is shared. Unique so two people cannot claim the same private slot.
            $table->unsignedTinyInteger('unique_slot')->nullable()->unique();
            $table->unsignedInteger('cycle_reset_day_index')->nullable();
            $table->timestamps();
        });

        Schema::create('motivation_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('assigned_on');
            $table->unsignedSmallInteger('message_number');
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'assigned_on']);
            $table->index('assigned_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('motivation_assignments');
        Schema::dropIfExists('motivation_user_states');
        Schema::dropIfExists('motivation_messages');
        Schema::dropIfExists('motivation_settings');
    }
};
