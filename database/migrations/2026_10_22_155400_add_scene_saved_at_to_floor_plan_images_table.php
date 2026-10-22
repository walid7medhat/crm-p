<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('floor_plan_images', function (Blueprint $table) {
            $table->timestamp('scene_saved_at')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('floor_plan_images', function (Blueprint $table) {
            $table->dropColumn('scene_saved_at');
        });
    }
};
