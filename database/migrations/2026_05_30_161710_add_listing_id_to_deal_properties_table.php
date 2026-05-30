<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('deal_properties', function (Blueprint $table) {
            $table->foreignId('listing_id')
                ->nullable()
                ->after('area_id')
                ->constrained('listings')
                ->nullOnDelete();

            $table->index('listing_id');
        });
    }

    public function down(): void
    {
        Schema::table('deal_properties', function (Blueprint $table) {
            $table->dropForeign(['listing_id']);
            $table->dropIndex(['listing_id']);
            $table->dropColumn('listing_id');
        });
    }
};
