<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up()
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->foreignId('hot_deal_approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('hot_deal_approved_at')->nullable();
        });
    }

    public function down()
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropForeign(['hot_deal_approved_by']);
            $table->dropColumn(['hot_deal_approved_by', 'hot_deal_approved_at']);
        });
    }
};
