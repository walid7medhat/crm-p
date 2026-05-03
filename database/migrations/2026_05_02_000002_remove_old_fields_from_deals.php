<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('deals', function (Blueprint $table) {
              // ✅ Step 1: Drop foreign keys first
            $table->dropForeign(['property_type_id']);
            $table->dropForeign(['area_id']);
            $table->dropForeign(['subcommunity_id']);
            $table->dropForeign(['project_id']);
            $table->dropForeign(['developer_id']);
            $table->dropColumn([
                'property_link',
                'property_reference',
                'unit_no',
                'property_type_id',
                'bedrooms',
                'unit_size',
                'area_id',
                'subcommunity_id',
                'project_id',
                'developer_id',
                'developer_name',
                'developer_phone',
            ]);
        });
    }

    public function down()
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->string('property_reference')->nullable();
            $table->string('property_link')->nullable();
            $table->string('unit_no')->nullable();
            $table->foreignId('property_type_id')->nullable();
            $table->string('bedrooms')->nullable();
            $table->string('unit_size')->nullable();
            $table->foreignId('area_id')->nullable();
            $table->foreignId('subcommunity_id')->nullable();
            $table->foreignId('project_id')->nullable();
            $table->foreignId('developer_id')->nullable();
            $table->string('developer_name')->nullable();
            $table->string('developer_phone')->nullable();
        });
    }
};