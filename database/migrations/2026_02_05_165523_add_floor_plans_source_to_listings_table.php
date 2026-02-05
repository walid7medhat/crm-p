<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->json('floor_plans_source')->nullable();
            
           
        });
        
        Schema::table('floor_plans', function (Blueprint $table) {
            $table->boolean('is_from_project')->default(false);
            $table->unsignedBigInteger('project_floor_plan_id')->nullable();
            $table->foreign('project_floor_plan_id')->references('id')->on('floor_plan_images')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn('floor_plans_source');
           
        });
        
        Schema::table('floor_plans', function (Blueprint $table) {
            $table->dropForeign(['project_floor_plan_id']);
            $table->dropColumn(['is_from_project', 'project_floor_plan_id']);
        });
    }
};