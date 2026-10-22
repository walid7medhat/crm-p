<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('floor_plan_images', function (Blueprint $table) {
            $table->json('scene_data')->nullable()->after('scene_saved_at');
        });
    }

    public function down(): void
    {
        Schema::table('floor_plan_images', function (Blueprint $table) {
            $table->dropColumn('scene_data');
        });
    }
};
