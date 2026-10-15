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
        Schema::table('marketing_bookins', function (Blueprint $table) {
            //
            Schema::table('marketing_bookings', function (Blueprint $t) {
                    $t->json('units')->nullable()->after('bedrooms');
                });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketing_bookins', function (Blueprint $table) {
            //
        });
    }
};
