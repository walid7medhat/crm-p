<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('deals', function (Blueprint $table) {
            // Primary Deal Stages
            $table->timestamp('eoi_date')->nullable()->after('stage_id');
            $table->timestamp('booking_date')->nullable()->after('eoi_date');
            $table->timestamp('spa_date')->nullable()->after('booking_date');
            
            // Secondary Deal Stages
            $table->timestamp('security_deposit_date')->nullable()->after('spa_date');
            $table->timestamp('mou_date')->nullable()->after('security_deposit_date');
            $table->timestamp('noc_date')->nullable()->after('mou_date');
            
            // Won Stage (for both Primary & Secondary)
            $table->timestamp('won_date')->nullable()->after('noc_date');
        });
    }

    public function down()
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn([
                'eoi_date',
                'booking_date',
                'spa_date',
                'security_deposit_date',
                'mou_date',
                'noc_date',
                'won_date'
            ]);
        });
    }
};