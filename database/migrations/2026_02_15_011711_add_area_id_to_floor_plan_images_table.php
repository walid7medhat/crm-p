<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('floor_plan_images', function (Blueprint $table) {
            $table->foreignId('area_id')->nullable()->after('project_id')
                  ->constrained('areas')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('floor_plan_images', function (Blueprint $table) {
            $table->dropForeign(['area_id']);
            $table->dropColumn('area_id');
        });
    }
};