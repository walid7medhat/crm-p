<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Map pins use area centroid when property-level geocoding is unreliable.
     * Properties still may override via listings.latitude / listings.longitude.
     */
    public function up(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('name');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->index(['latitude', 'longitude'], 'areas_lat_lng_index');
        });
    }

    public function down(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->dropIndex('areas_lat_lng_index');
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
