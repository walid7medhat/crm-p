<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Global (non-brand) maintenance mode — singleton row, never BrandOwned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_maintenance_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(false);
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->string('estimated_return')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('platform_maintenance_settings')->insert([
            'is_enabled' => false,
            'title' => "We'll Be Back Soon",
            'message' => 'The CRM is temporarily unavailable while we perform scheduled maintenance.',
            'estimated_return' => null,
            'updated_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_maintenance_settings');
    }
};
