<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up()
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->boolean('approved')->default(true)->after('status');
            $table->unsignedBigInteger('approved_by')->nullable()->after('approved');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
    
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
        });
    }
    
    public function down()
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn(['approved', 'approved_by', 'approved_at']);
        });
}

};
