<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 2026_10_06_172600 can already be listed in the migrations table after the
        // MariaDB parse error, so that file will not run again. Create the same
        // plain indexes here when they are still missing.
        if (! $this->hasIndex('leads_stage_kanban_sort_idx')) {
            DB::statement('ALTER TABLE leads ADD INDEX leads_stage_kanban_sort_idx (stage_id, deleted_at, bitrix24_last_activity_at, created_at, id)');
        }

        if (! $this->hasIndex('leads_stage_created_live_idx')) {
            DB::statement('ALTER TABLE leads ADD INDEX leads_stage_created_live_idx (stage_id, deleted_at, created_at, id)');
        }

        if (! $this->hasIndex('leads_kanban_heat_cover_idx')) {
            DB::statement('ALTER TABLE leads ADD INDEX leads_kanban_heat_cover_idx (deleted_at, status_lead, stage_id, interaction_result)');
        }
    }

    public function down(): void
    {
        foreach (['leads_stage_kanban_sort_idx', 'leads_stage_created_live_idx', 'leads_kanban_heat_cover_idx'] as $name) {
            if ($this->hasIndex($name)) {
                DB::statement('ALTER TABLE leads DROP INDEX '.$name);
            }
        }
    }

    private function hasIndex(string $name): bool
    {
        $rows = DB::select('SHOW INDEX FROM leads WHERE Key_name = ?', [$name]);

        return $rows !== [];
    }
};
