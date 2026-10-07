<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('listings', function (Blueprint $table) {
            // Plot size in sqft — required for villa / townhouse / twinhouse / duplex.
            $table->decimal('plot_size', 12, 2)->nullable()->after('size_sqmt');
        });
    }

    public function down()
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn('plot_size');
        });
    }
};
