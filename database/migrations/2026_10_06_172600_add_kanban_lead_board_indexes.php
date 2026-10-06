<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! $this->hasIndex('leads_stage_kanban_sort_idx')) {
            // stage + not-deleted + activity sort, so the first page of a column
            // is an index range instead of a sort of every lead.
            DB::statement('ALTER TABLE leads ADD INDEX leads_stage_kanban_sort_idx (stage_id, deleted_at, (COALESCE(bitrix24_last_activity_at, created_at)), id)');
        }

        if (! $this->hasIndex('leads_stage_created_live_idx')) {
            // Order-1 stages sort by created_at, id. deleted_at is in the index
            // so that filter does not pull the rest of the row.
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
