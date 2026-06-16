<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('developers', function (Blueprint $table) {
            $table->decimal('noc_fees_ready', 15, 2)->nullable()->after('phone');
            $table->decimal('noc_fees_off_plan', 15, 2)->nullable()->after('noc_fees_ready');
        });
    }

    public function down(): void
    {
        Schema::table('developers', function (Blueprint $table) {
            $table->dropColumn(['noc_fees_ready', 'noc_fees_off_plan']);
        });
    }
};