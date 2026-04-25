<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up()
{
    Schema::table('deals', function (Blueprint $table) {
        $table->string('developer_name')->nullable()->after('developer_id');
        $table->string('developer_phone')->nullable()->after('developer_name');
    });
}

public function down()
{
    Schema::table('deals', function (Blueprint $table) {
        $table->dropColumn(['developer_name', 'developer_phone']);
    });
}
};
