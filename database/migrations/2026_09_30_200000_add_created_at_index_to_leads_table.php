<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dashboard analytics filter leads by a created_at range (This month / week / custom).
 * For admin / super_admin there is no other filter, so without this index every
 * analytics query scanned the whole leads table. A plain secondary index is built in
 * place — the table stays readable/writable while it is created.
 */
return new class extends Migration
{
    private const INDEX = 'leads_created_at_index';

    public function up(): void
    {
        if ($this->indexExists()) {
            return;
        }

        Schema::table('leads', function (Blueprint $table) {
            $table->index('created_at', self::INDEX);
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
        return collect(DB::select('SHOW INDEX FROM leads'))
            ->contains(fn ($row) => $row->Key_name === self::INDEX);
    }
};
