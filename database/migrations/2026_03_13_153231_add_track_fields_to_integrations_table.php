<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->boolean('track_enabled')->default(false)->after('dont_make_responsible_if_not_clocked_in');
            $table->string('track_keyword')->nullable()->after('track_enabled');
        });
    }

    public function down()
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->dropColumn(['track_enabled', 'track_keyword']);
        });
    }
};