<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bitrix_sync_states')) {
            return;
        }
        Schema::table('bitrix_sync_states', function (Blueprint $table) {
            if (!Schema::hasColumn('bitrix_sync_states', 'status')) {
                $table->string('status', 32)->default('idle')->after('key');
            }
            if (!Schema::hasColumn('bitrix_sync_states', 'total')) {
                $table->unsignedInteger('total')->default(0)->after('cursor');
            }
            if (!Schema::hasColumn('bitrix_sync_states', 'processed')) {
                $table->unsignedInteger('processed')->default(0)->after('total');
            }
            if (!Schema::hasColumn('bitrix_sync_states', 'new_count')) {
                $table->unsignedInteger('new_count')->default(0)->after('processed');
            }
            if (!Schema::hasColumn('bitrix_sync_states', 'existing_count')) {
                $table->unsignedInteger('existing_count')->default(0)->after('new_count');
            }
            if (!Schema::hasColumn('bitrix_sync_states', 'error_count')) {
                $table->unsignedInteger('error_count')->default(0)->after('existing_count');
            }
            if (!Schema::hasColumn('bitrix_sync_states', 'skip_existing')) {
                $table->boolean('skip_existing')->default(false)->after('error_count');
            }
            if (!Schema::hasColumn('bitrix_sync_states', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('skip_existing');
            }
            if (!Schema::hasColumn('bitrix_sync_states', 'last_error')) {
                $table->text('last_error')->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('bitrix_sync_states', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('last_error');
            }
            if (!Schema::hasColumn('bitrix_sync_states', 'finished_at')) {
                $table->timestamp('finished_at')->nullable()->after('started_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('bitrix_sync_states')) {
            return;
        }
        Schema::table('bitrix_sync_states', function (Blueprint $table) {
            foreach ([
                'status', 'total', 'processed', 'new_count', 'existing_count',
                'error_count', 'skip_existing', 'user_id', 'last_error',
                'started_at', 'finished_at',
            ] as $col) {
                if (Schema::hasColumn('bitrix_sync_states', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
