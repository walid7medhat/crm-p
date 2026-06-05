<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'bitrix24_id')) {
                $table->unsignedBigInteger('bitrix24_id')->nullable()->after('id');
                $table->unique('bitrix24_id', 'users_bitrix24_id_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'bitrix24_id')) {
                $table->dropUnique('users_bitrix24_id_unique');
                $table->dropColumn('bitrix24_id');
            }
        });
    }
};
