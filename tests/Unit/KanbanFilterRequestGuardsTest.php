<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Phase 2.5-B / 2.5-B.1: kanban filter debounce, fingerprint, assigned filter, agent chart.
 */
class KanbanFilterRequestGuardsTest extends TestCase
{
    public function test_navbar_mounts_only_one_lead_search_modal_per_viewport(): void
    {
        $source = file_get_contents(base_path('resources/js/components/layout/navbar/index.vue'));
        $this->assertStringContainsString('v-if="searchModalMounted && isMobileViewport"', $source);
        $this->assertStringContainsString('v-if="searchModalMounted && !isMobileViewport"', $source);
        $this->assertStringContainsString('const SEARCH_DEBOUNCE_MS = 400', $source);
    }

    public function test_lead_search_modal_owns_single_text_debounce(): void
    {
        $modal = file_get_contents(base_path('resources/js/components/kanban/leadList/LeadSearchModal.vue'));
        $deal = file_get_contents(base_path('resources/js/components/kanban/kanban_deal.vue'));
        $leads = file_get_contents(base_path('resources/js/components/kanban/leadList/leads.vue'));

        $this->assertStringContainsString('const LIVE_TEXT_SEARCH_MS = 400', $modal);
        // Explicit Search may re-apply; live keepOpen still skips identical query.
        $this->assertStringContainsString('force: !keepOpen', $modal);
        $this->assertStringContainsString('keepOpen', $modal);

        // No stacked soft debounce: search always routes immediate=true.
        $this->assertStringContainsString('leadsComponent.fetchLeads(true, query || null, { force: !!force })', $deal);
        $this->assertStringNotContainsString('isSoftLeadSearchQuery', $deal);
        $this->assertStringNotContainsString('lastRoutedLeadSearchFingerprint', $deal);

        // Board keeps AbortController + generation stale guard.
        $this->assertStringContainsString('new AbortController()', $leads);
        $this->assertStringContainsString("err?.code === 'ERR_CANCELED'", $leads);
        $this->assertStringContainsString('generation !== fetchGeneration', $leads);
    }

    public function test_board_fingerprint_covers_effective_filters_and_pagination(): void
    {
        $leads = file_get_contents(base_path('resources/js/components/kanban/leadList/leads.vue'));
        $this->assertStringContainsString('function buildBoardFetchFingerprint', $leads);
        $this->assertStringContainsString('inFlightBoardFingerprint', $leads);
        $this->assertStringContainsString('page: 1', $leads);
        $this->assertStringContainsString('per_page:', $leads);
        $this->assertStringContainsString('buildLeadSearchApiParams(q)', $leads);
        // Skip only while in flight unless force.
        $this->assertStringContainsString(
            'if (!force && isFetching.value && inFlightBoardFingerprint === fingerprint)',
            $leads
        );
        $this->assertStringContainsString('force: !!options.force', $leads);
    }

    public function test_build_lead_search_api_params_includes_assigned_and_agent_filters(): void
    {
        $leads = file_get_contents(base_path('resources/js/components/kanban/leadList/leads.vue'));
        foreach ([
            'lead_name',
            'first_name',
            'work_phone',
            'email',
            'assigned_from',
            'assigned_to',
            'assigned_at',
            'responsible_person_id',
            'office_branch',
            'stage_id',
            'heat',
            'has_duplicate',
            'has_blacklist',
        ] as $key) {
            $this->assertStringContainsString($key, $leads, "Expected filter key {$key} in leads.vue");
        }
    }

    public function test_assigned_history_filter_uses_where_exists_helper(): void
    {
        $source = file_get_contents(base_path('app/Http/Controllers/Api/StageController.php'));
        $this->assertStringContainsString('function applyAssignedHistoryFilter', $source);
        $this->assertStringContainsString("where('lead_histories.changes->action', 'assigned')", $source);
        $this->assertStringContainsString("UaeDateRange::apply(\$query, 'lead_histories.created_at'", $source);
        $this->assertStringNotContainsString(
            "whereHas('histories', function(\$query) use (\$assignedFrom",
            $source
        );
    }

    public function test_active_agents_weekly_chart_uses_group_by_not_per_day_count(): void
    {
        $source = file_get_contents(base_path('app/Http/Controllers/Api/DashboardController.php'));
        $methodStart = strpos($source, 'public function getActiveAgents()');
        $this->assertNotFalse($methodStart);
        $methodEnd = strpos($source, 'public function getLeadsOverview', $methodStart);
        $this->assertNotFalse($methodEnd);
        $method = substr($source, $methodStart, $methodEnd - $methodStart);

        $this->assertStringContainsString("DATE(created_at) as d, COUNT(*) as total", $method);
        $this->assertStringContainsString("groupBy('d')", $method);
        $this->assertStringNotContainsString("whereDate('created_at', \$date)->count()", $method);
        // Hierarchy restriction preserved for non-admin.
        $this->assertStringContainsString("whereIn('parent_id', \$user_hierarchy)", $method);
    }

    public function test_listing_chart_helpers_still_use_group_by(): void
    {
        $source = file_get_contents(base_path('app/Http/Controllers/Api/DashboardController.php'));
        $this->assertStringContainsString('fillListingChartFromDailyCounts', $source);
        $this->assertStringContainsString('scopedListingChartQuery', $source);
        $this->assertStringContainsString("groupBy('d')", $source);
        $this->assertStringContainsString("groupBy('m')", $source);
    }
}
