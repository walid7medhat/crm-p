<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * STEP 8 — Composite indexes for hot CRM list/kanban query shapes.
 *
 * Only adds composites justified by real WHERE / ORDER BY / GROUP BY patterns
 * (and EXPLAIN showing filesort / full scan). Does not change application logic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listing_access_requests', function (Blueprint $table) {
            // my-orders / AllRequests: whereIn(requested_by) + orderBy(created_at desc)
            $table->index(
                ['requested_by', 'created_at'],
                'lar_requested_by_created_at_idx'
            );
            // status tab filter + default sort; also covers groupBy(status) counts
            $table->index(
                ['status', 'created_at'],
                'lar_status_created_at_idx'
            );
        });

        Schema::table('listings', function (Blueprint $table) {
            // my_listings: whereIn(agent_id) + orderBy(created_at)
            $table->index(
                ['agent_id', 'created_at'],
                'listings_agent_created_at_idx'
            );
            // default catalog / active grid:
            // approved + is_archived + is_active equalities + orderBy(created_at)
            $table->index(
                ['approved', 'is_archived', 'is_active', 'created_at'],
                'listings_approved_archived_active_created_idx'
            );
        });

        Schema::table('deals', function (Blueprint $table) {
            // getDealsByStage + window PARTITION BY stage_id ORDER BY updated_at
            $table->index(
                ['stage_id', 'updated_at'],
                'deals_stage_updated_idx'
            );
            // visibleFor(responsible_person_id) + stage filter + orderBy(updated_at)
            $table->index(
                ['responsible_person_id', 'stage_id', 'updated_at'],
                'deals_resp_stage_updated_idx'
            );

            // Redundant with deals_stage_updated_idx leftmost prefix (stage_id).
            // FK deals_stage_id_foreign remains valid against the new composite.
            $table->dropIndex('deals_stage_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->index('stage_id', 'deals_stage_id_index');
            $table->dropIndex('deals_resp_stage_updated_idx');
            $table->dropIndex('deals_stage_updated_idx');
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex('listings_approved_archived_active_created_idx');
            $table->dropIndex('listings_agent_created_at_idx');
        });

        Schema::table('listing_access_requests', function (Blueprint $table) {
            $table->dropIndex('lar_status_created_at_idx');
            $table->dropIndex('lar_requested_by_created_at_idx');
        });
    }
};
