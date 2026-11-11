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
        if (Schema::hasColumn('marketing_bookings', 'units')) {
            return;
        }

        Schema::table('marketing_bookings', function (Blueprint $table) {
            // `bedrooms` doesn't exist on every database, so only position after it when it does.
            $column = $table->json('units')->nullable();
            if (Schema::hasColumn('marketing_bookings', 'bedrooms')) {
                $column->after('bedrooms');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('marketing_bookings', 'units')) {
            Schema::table('marketing_bookings', function (Blueprint $table) {
                $table->dropColumn('units');
            });
        }
    }
};
