<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('leads')) {
            return;
        }
        Schema::table('leads', function (Blueprint $table) {
            // Dedicated query-able mirrors of the most-used Bitrix24 timestamps.
            // Full payload still lives in `bitrix24_data`; these columns let
            // SQL filter/sort by "recently active" leads without JSON_EXTRACT.
            if (!Schema::hasColumn('leads', 'bitrix24_last_activity_at')) {
                $table->timestamp('bitrix24_last_activity_at')->nullable()->after('bitrix24_data');
            }
            if (!Schema::hasColumn('leads', 'bitrix24_moved_at')) {
                $table->timestamp('bitrix24_moved_at')->nullable()->after('bitrix24_last_activity_at');
            }
            if (!Schema::hasColumn('leads', 'bitrix24_last_activity_by_id')) {
                $table->unsignedBigInteger('bitrix24_last_activity_by_id')->nullable()->after('bitrix24_moved_at');
            }
            if (!Schema::hasColumn('leads', 'bitrix24_assigned_user_id')) {
                $table->unsignedBigInteger('bitrix24_assigned_user_id')->nullable()->after('bitrix24_last_activity_by_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('leads')) {
            return;
        }
        Schema::table('leads', function (Blueprint $table) {
            foreach ([
                'bitrix24_last_activity_at',
                'bitrix24_moved_at',
                'bitrix24_last_activity_by_id',
                'bitrix24_assigned_user_id',
            ] as $col) {
                if (Schema::hasColumn('leads', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
