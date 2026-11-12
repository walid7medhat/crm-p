<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lead search "Modified On" (UaeDateRange::applyModified) picks history rows by
 * date first, then their lead_id. Without this index a date-only filter walks
 * every lead's whole history on each kanban query.
 */
return new class extends Migration
{
    private const INDEX = 'lead_histories_created_lead_idx';

    public function up(): void
    {
        if (! Schema::hasTable('lead_histories') || $this->indexExists()) {
            return;
        }

        Schema::table('lead_histories', function (Blueprint $table) {
            $table->index(['created_at', 'lead_id'], self::INDEX);
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
