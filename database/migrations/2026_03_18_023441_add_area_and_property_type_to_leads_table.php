<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up()
{
    Schema::table('leads', function (Blueprint $table) {
            $table->unsignedBigInteger('area_id')->nullable()->after('source_information');
            $table->unsignedBigInteger('property_type_id')->nullable()->after('area_id');
    
            // لو عندك tables للـ areas و property_types
            $table->foreign('area_id')->references('id')->on('areas')->nullOnDelete();
            $table->foreign('property_type_id')->references('id')->on('property_types')->nullOnDelete();
        });
    }
    
    public function down()
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['area_id']);
            $table->dropForeign(['property_type_id']);
    
            $table->dropColumn(['area_id', 'property_type_id']);
        });
    }
};
