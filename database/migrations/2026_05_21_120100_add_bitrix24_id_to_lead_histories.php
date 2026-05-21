<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('lead_histories')) {
            return;
        }
        Schema::table('lead_histories', function (Blueprint $table) {
            if (!Schema::hasColumn('lead_histories', 'bitrix24_id')) {
                $table->unsignedBigInteger('bitrix24_id')->nullable()->index('lead_histories_bitrix24_id_index');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('lead_histories')) {
            return;
        }
        Schema::table('lead_histories', function (Blueprint $table) {
            if (Schema::hasColumn('lead_histories', 'bitrix24_id')) {
                $table->dropIndex('lead_histories_bitrix24_id_index');
                $table->dropColumn('bitrix24_id');
            }
        });
    }
};
