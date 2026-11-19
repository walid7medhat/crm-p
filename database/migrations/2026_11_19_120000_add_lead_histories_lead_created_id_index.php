<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Speeds visibleEngagement's "next assigned history after engagement" subquery:
 * WHERE lead_id = ? AND created_at > ? ORDER BY created_at, id.
 *
 * Additive only — does not rewrite data or drop existing indexes.
 */
return new class extends Migration
{
    private const INDEX = 'lead_histories_lead_created_id_idx';

    public function up(): void
    {
        if (! Schema::hasTable('lead_histories') || $this->indexExists()) {
            return;
        }

        Schema::table('lead_histories', function (Blueprint $table) {
            $table->index(['lead_id', 'created_at', 'id'], self::INDEX);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('lead_histories') || ! $this->indexExists()) {
            return;
        }

        Schema::table('lead_histories', function (Blueprint $table) {
            $table->dropIndex(self::INDEX);
        });
    }

    private function indexExists(): bool
    {
        return ! empty(DB::select('SHOW INDEX FROM lead_histories WHERE Key_name = ?', [self::INDEX]));
    }
};
