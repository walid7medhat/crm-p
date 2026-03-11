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
        Schema::table('deals', function (Blueprint $table) {
            // إضافة عمود subcommunity_id منفصل عن area_id
            $table->foreignId('subcommunity_id')
                  ->nullable()
                  ->after('area_id')
                  ->constrained('areas')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            // حذف العلاقة الأول
            $table->dropForeign(['subcommunity_id']);
            // ثم حذف العمود
            $table->dropColumn('subcommunity_id');
        });
    }
};