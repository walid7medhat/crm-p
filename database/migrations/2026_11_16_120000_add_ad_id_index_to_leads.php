<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lead search "Campaign ID" also matches leads.ad_id (Meta ad id) — LeadIntegrationFilter.
 * Without an index that is a full scan of leads on every kanban query.
 */
return new class extends Migration
{
    private const INDEX = 'leads_ad_id_idx';

    public function up(): void
    {
        if (! Schema::hasColumn('leads', 'ad_id') || $this->indexExists()) {
            return;
        }

        Schema::table('leads', function (Blueprint $table) {
            $table->index('ad_id', self::INDEX);
        });
    }

    public function down(): void
    {
        if (! $this->indexExists()) {
            return;
        }

        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(self::INDEX);
        });
    }

    private function indexExists(): bool
    {
        return ! empty(DB::select('SHOW INDEX FROM leads WHERE Key_name = ?', [self::INDEX]));
    }
};
