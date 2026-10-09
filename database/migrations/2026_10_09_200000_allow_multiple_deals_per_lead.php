<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A lead can now have several deals (lead header "Create deal"), so deals.lead_id
 * must no longer be unique. The foreign key to leads stays; MySQL needs an index
 * behind it, so a plain index is added before the unique one is dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->indexExists('deals', 'deals_lead_id_index')) {
            Schema::table('deals', function (Blueprint $table) {
                $table->index('lead_id', 'deals_lead_id_index');
            });
        }

        if ($this->indexExists('deals', 'deals_lead_id_unique')) {
            Schema::table('deals', function (Blueprint $table) {
                $table->dropUnique('deals_lead_id_unique');
            });
        }
    }

    public function down(): void
    {
        // Only reversible while every lead still has at most one deal.
        if (! $this->indexExists('deals', 'deals_lead_id_unique')) {
            Schema::table('deals', function (Blueprint $table) {
                $table->unique('lead_id', 'deals_lead_id_unique');
            });
        }

        if ($this->indexExists('deals', 'deals_lead_id_index')) {
            Schema::table('deals', function (Blueprint $table) {
                $table->dropIndex('deals_lead_id_index');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
