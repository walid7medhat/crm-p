<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('listings', function (Blueprint $table) {
            // Plot size is a whole number of sqft.
            $table->unsignedInteger('plot_size')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->decimal('plot_size', 12, 2)->nullable()->change();
        });
    }
};
