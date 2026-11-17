<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Covering indexes for the kanban board's stage counts and heat chip totals
 * (see App\Support\LeadBoardIndexHint). Built online (no table lock).
 */
return new class extends Migration
{
    private const INDEXES = [
        'leads_board_scope_idx' => '(merged_into_lead_id, deleted_at, brand_key, stage_id, status_lead, interaction_result)',
        'leads_board_owner_idx' => '(responsible_person_id, deleted_at, merged_into_lead_id, brand_key, stage_id, status_lead, interaction_result)',
    ];

    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true) || ! Schema::hasTable('leads')) {
            return;
        }

        foreach (self::INDEXES as $name => $columns) {
            if ($this->hasIndex($name)) {
                continue;
            }
            DB::statement("ALTER TABLE `leads` ADD INDEX `{$name}` {$columns}, ALGORITHM=INPLACE, LOCK=NONE");
        }

        \App\Support\LeadBoardIndexHint::forget();
    }

    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true) || ! Schema::hasTable('leads')) {
            return;
        }

        \App\Support\LeadBoardIndexHint::forget();

        foreach (array_keys(self::INDEXES) as $name) {
            if ($this->hasIndex($name)) {
                DB::statement("ALTER TABLE `leads` DROP INDEX `{$name}`");
            }
        }
    }

    private function hasIndex(string $name): bool
    {
        return DB::select('SHOW INDEX FROM `leads` WHERE Key_name = ?', [$name]) !== [];
    }
};
