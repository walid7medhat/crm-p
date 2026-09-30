<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Index for the create-lead duplicate-phone check (LeadController::phoneDuplicates),
 * which also looks up the secondary phone. Without it that lookup scanned every lead.
 * A plain secondary index is built in place — the table stays readable/writable.
 */
return new class extends Migration
{
    private const INDEX = 'leads_work_phone_2_index';

    public function up(): void
    {
        if ($this->indexExists()) {
            return;
        }

        Schema::table('leads', function (Blueprint $table) {
            $table->index('work_phone_2', self::INDEX);
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
