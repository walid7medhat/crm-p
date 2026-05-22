<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bitrix_sync_states')) {
            Schema::table('bitrix_sync_states', function (Blueprint $table) {
                if (!Schema::hasColumn('bitrix_sync_states', 'leads_per_sec')) {
                    $table->decimal('leads_per_sec', 10, 2)->default(0)->after('finished_at');
                }
                if (!Schema::hasColumn('bitrix_sync_states', 'eta_seconds')) {
                    $table->unsignedInteger('eta_seconds')->nullable()->after('leads_per_sec');
                }
                if (!Schema::hasColumn('bitrix_sync_states', 'last_progress_at')) {
                    $table->timestamp('last_progress_at')->nullable()->after('eta_seconds');
                }
                if (!Schema::hasColumn('bitrix_sync_states', 'last_processed_snapshot')) {
                    $table->unsignedInteger('last_processed_snapshot')->default(0)->after('last_progress_at');
                }
                if (!Schema::hasColumn('bitrix_sync_states', 'parallel_shards')) {
                    $table->unsignedSmallInteger('parallel_shards')->default(1)->after('last_processed_snapshot');
                }
                if (!Schema::hasColumn('bitrix_sync_states', 'shards_completed')) {
                    $table->unsignedSmallInteger('shards_completed')->default(0)->after('parallel_shards');
                }
                if (!Schema::hasColumn('bitrix_sync_states', 'sync_mode')) {
                    $table->string('sync_mode', 32)->default('sequential')->after('shards_completed');
                }
                if (!Schema::hasColumn('bitrix_sync_states', 'meta')) {
                    $table->json('meta')->nullable()->after('sync_mode');
                }
            });
        }

        if (!Schema::hasTable('bitrix_sync_shards')) {
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

        if (Schema::hasTable('leads')) {
            Schema::table('leads', function (Blueprint $table) {
                if (Schema::hasColumn('leads', 'bitrix24_id') && !$this->indexExists('leads', 'leads_bitrix24_id_index')) {
                    $table->index('bitrix24_id', 'leads_bitrix24_id_index');
                }
                if (Schema::hasColumn('leads', 'email') && !$this->indexExists('leads', 'leads_email_index')) {
                    $table->index('email', 'leads_email_index');
                }
                if (Schema::hasColumn('leads', 'work_phone') && !$this->indexExists('leads', 'leads_work_phone_index')) {
                    $table->index('work_phone', 'leads_work_phone_index');
                }
                if (!$this->indexExists('leads', 'leads_created_at_index')) {
                    $table->index('created_at', 'leads_created_at_index');
                }
            });
        }

        if (Schema::hasTable('lead_histories') && Schema::hasColumn('lead_histories', 'bitrix24_id')) {
            Schema::table('lead_histories', function (Blueprint $table) {
                if (!$this->indexExists('lead_histories', 'lead_histories_lead_bitrix24_idx')) {
                    $table->index(['lead_id', 'bitrix24_id'], 'lead_histories_lead_bitrix24_idx');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bitrix_sync_shards');

        if (Schema::hasTable('bitrix_sync_states')) {
            Schema::table('bitrix_sync_states', function (Blueprint $table) {
                foreach ([
                    'leads_per_sec', 'eta_seconds', 'last_progress_at', 'last_processed_snapshot',
                    'parallel_shards', 'shards_completed', 'sync_mode', 'meta',
                ] as $col) {
                    if (Schema::hasColumn('bitrix_sync_states', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('leads')) {
            Schema::table('leads', function (Blueprint $table) {
                foreach (['leads_bitrix24_id_index', 'leads_email_index', 'leads_work_phone_index', 'leads_created_at_index'] as $idx) {
                    if ($this->indexExists('leads', $idx)) {
                        $table->dropIndex($idx);
                    }
                }
            });
        }

        if (Schema::hasTable('lead_histories')) {
            Schema::table('lead_histories', function (Blueprint $table) {
                if ($this->indexExists('lead_histories', 'lead_histories_lead_bitrix24_idx')) {
                    $table->dropIndex('lead_histories_lead_bitrix24_idx');
                }
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = $connection->select("PRAGMA index_list('{$table}')");
            foreach ($indexes as $idx) {
                if (($idx->name ?? '') === $index) {
                    return true;
                }
            }
            return false;
        }

        $db = $connection->getDatabaseName();
        $rows = $connection->select(
            'SELECT COUNT(*) AS c FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$db, $table, $index]
        );

        return ((int) ($rows[0]->c ?? 0)) > 0;
    }
};
