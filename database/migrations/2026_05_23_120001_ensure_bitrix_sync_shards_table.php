<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Safety migration: create bitrix_sync_shards if a prior migration failed
 * before this table was created (fixes cancel/reset 500 on production).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bitrix_sync_shards')) {
            return;
        }

        Schema::create('bitrix_sync_shards', function (Blueprint $table) {
            $table->id();
            $table->string('sync_key', 64)->default('global_sync')->index();
            $table->unsignedSmallInteger('shard_index');
            $table->unsignedBigInteger('min_bitrix_id');
            $table->unsignedBigInteger('max_bitrix_id');
            $table->unsignedInteger('cursor')->default(0);
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('new_count')->default(0);
            $table->unsignedInteger('existing_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['sync_key', 'shard_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitrix_sync_shards');
    }
};
