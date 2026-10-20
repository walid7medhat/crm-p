<?php

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Read-only checks for the Kanban index proposal.
 * Nothing in this test creates, drops, or rebuilds an index.
 */
class KanbanIndexProposalTest extends TestCase
{
    public function test_proposal_is_not_part_of_the_migration_set_and_does_not_drop_indexes(): void
    {
        $path = base_path('database/proposals/2026_10_19_leads_stage_activity_live_index.php');
        $this->assertFileExists($path);
        $this->assertFileDoesNotExist(database_path('migrations/2026_10_19_leads_stage_activity_live_index.php'));

        $source = file_get_contents($path);
        $this->assertIsString($source);
        $this->assertStringNotContainsString('DROP INDEX leads_stage_kanban_sort_idx', $source);
        $this->assertStringNotContainsString('DROP INDEX idx_stage_activity', $source);
        $this->assertStringNotContainsString('(COALESCE(', $source);
        $this->assertStringNotContainsString('storedAs', $source);
        $this->assertStringNotContainsString('FULLTEXT', $source);
        $this->assertStringContainsString('leads_stage_activity_live_idx', $source);
        $this->assertStringContainsString('(stage_id, deleted_at, bitrix24_last_activity_at, id)', $source);
        $this->assertStringContainsString('KANBAN_INDEX_PROPOSAL_APPROVED', $source);
    }

    public function test_repository_index_definition_no_longer_matches_the_functional_index_that_was_applied(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_10_06_172600_add_kanban_lead_board_indexes.php'));
        $followUp = file_get_contents(database_path('migrations/2026_10_06_194200_create_missing_kanban_lead_board_indexes.php'));

        $this->assertIsString($migration);
        $this->assertIsString($followUp);
        $this->assertStringNotContainsString('(COALESCE(bitrix24_last_activity_at, created_at))', $migration);
        $this->assertStringContainsString(
            'leads_stage_kanban_sort_idx (stage_id, deleted_at, bitrix24_last_activity_at, created_at, id)',
            $migration
        );
        $this->assertStringContainsString('if (! $this->hasIndex(\'leads_stage_kanban_sort_idx\'))', $followUp);

        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Index expression check needs the MySQL connection.');
        }

        $before = collect(DB::select('SHOW INDEX FROM leads'))->pluck('Key_name')->unique()->values()->all();

        $parts = collect(DB::select('SHOW INDEX FROM leads WHERE Key_name = ?', ['leads_stage_kanban_sort_idx']));
        $this->assertNotEmpty($parts, 'The inspected database has leads_stage_kanban_sort_idx');

        $expression = $parts->pluck('Expression')->filter()->implode(' ');
        $this->assertStringContainsString('coalesce', strtolower($expression));
        $this->assertStringContainsString('bitrix24_last_activity_at', strtolower($expression));
        $this->assertStringContainsString('created_at', strtolower($expression));

        $plainColumns = $parts->pluck('Column_name')->filter()->values()->all();
        $this->assertSame(['stage_id', 'deleted_at', 'id'], $plainColumns);

        $after = collect(DB::select('SHOW INDEX FROM leads'))->pluck('Key_name')->unique()->sort()->values()->all();
        $expected = collect($before)->unique()->sort()->values()->all();
        $this->assertSame($expected, $after);
    }

    public function test_current_board_sort_does_not_need_a_new_index_on_this_database(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('EXPLAIN check needs the MySQL connection.');
        }

        $stageId = DB::table('leads')
            ->whereNull('deleted_at')
            ->selectRaw('stage_id, COUNT(*) as c')
            ->groupBy('stage_id')
            ->orderByDesc('c')
            ->value('stage_id');
        if (! $stageId) {
            $this->markTestSkipped('No live lead to explain.');
        }

        $plan = DB::select(
            'EXPLAIN SELECT leads.id FROM leads WHERE leads.stage_id = ? AND leads.deleted_at IS NULL ORDER BY leads.bitrix24_last_activity_at DESC, leads.id DESC LIMIT 16',
            [$stageId]
        );

        $this->assertNotEmpty($plan);
        $this->assertSame('idx_stage_activity', $plan[0]->key);
        $this->assertStringNotContainsString('Using filesort', (string) $plan[0]->Extra);

        $coalesce = DB::select(
            'EXPLAIN SELECT leads.id FROM leads WHERE leads.stage_id = ? AND leads.deleted_at IS NULL ORDER BY COALESCE(leads.bitrix24_last_activity_at, leads.created_at) DESC, leads.id DESC LIMIT 16',
            [$stageId]
        );
        $this->assertSame('leads_stage_kanban_sort_idx', $coalesce[0]->key);
    }
}
