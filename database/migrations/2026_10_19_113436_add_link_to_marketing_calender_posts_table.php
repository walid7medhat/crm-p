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
        Schema::table('marketing_calendar_posts', function (Blueprint $t) {   // use your real table name
            $t->string('link_url', 2048)->nullable()->after('link_id');
            $t->string('link_label')->nullable()->after('link_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketing_calender_posts', function (Blueprint $table) {
            //
        });
    }
};
