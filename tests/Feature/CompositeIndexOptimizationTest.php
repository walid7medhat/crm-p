<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * STEP 8 — regression: composite indexes for access requests / listings / deals.
 */
class CompositeIndexOptimizationTest extends TestCase
{
    public function test_access_requests_listings_deals_composite_indexes_exist(): void
    {
        $expected = [
            'listing_access_requests' => [
                'lar_requested_by_created_at_idx',
                'lar_status_created_at_idx',
            ],
            'listings' => [
                'listings_agent_created_at_idx',
                'listings_approved_archived_active_created_idx',
            ],
            'deals' => [
                'deals_stage_updated_idx',
                'deals_resp_stage_updated_idx',
            ],
        ];

        foreach ($expected as $table => $indexes) {
            $this->assertTrue(Schema::hasTable($table), "Missing table {$table}");
            $present = collect(DB::select("SHOW INDEX FROM {$table}"))
                ->pluck('Key_name')
                ->unique()
                ->all();

            foreach ($indexes as $index) {
                $this->assertContains(
                    $index,
                    $present,
                    "Expected index {$index} on {$table}"
                );
            }
        }

        // Redundant single-column stage_id index must be gone (replaced by composite).
        $dealIndexes = collect(DB::select('SHOW INDEX FROM deals'))
            ->pluck('Key_name')
            ->unique()
            ->all();
        $this->assertNotContains('deals_stage_id_index', $dealIndexes);
    }

    public function test_key_query_plans_use_new_composites(): void
    {
        $statusPlan = DB::select(
            'EXPLAIN SELECT id FROM listing_access_requests WHERE status = ? ORDER BY created_at DESC LIMIT 10',
            ['pending']
        )[0];
        $this->assertSame('lar_status_created_at_idx', $statusPlan->key);

        $catalogPlan = DB::select(
            'EXPLAIN SELECT id FROM listings WHERE approved = 1 AND is_active = 1 AND is_archived = 0 ORDER BY created_at DESC LIMIT 12'
        )[0];
        $this->assertSame('listings_approved_archived_active_created_idx', $catalogPlan->key);

        $stageId = DB::table('stages')->where('stage_type', 'deal')->value('id');
        if ($stageId) {
            $dealPlan = DB::select(
                'EXPLAIN SELECT id FROM deals WHERE deleted_at IS NULL AND stage_id = ? ORDER BY updated_at DESC LIMIT 10',
                [$stageId]
            )[0];
            $this->assertSame('deals_stage_updated_idx', $dealPlan->key);
            $this->assertStringNotContainsString('filesort', (string) ($dealPlan->Extra ?? ''));
        }
    }
}
