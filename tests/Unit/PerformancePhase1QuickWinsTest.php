<?php

namespace Tests\Unit;

use Tests\TestCase;

class PerformancePhase1QuickWinsTest extends TestCase
{
    public function test_kanban_perf_query_log_defaults_to_disabled(): void
    {
        $this->assertFalse((bool) config('lead_scoring.kanban_perf_query_log'));
    }

    public function test_leads_score_is_scheduled_only_once(): void
    {
        $source = file_get_contents(base_path('bootstrap/app.php'));
        $this->assertNotFalse($source);
        $this->assertSame(
            1,
            substr_count($source, "command('leads:score')"),
            'leads:score must be registered once in the scheduler'
        );
    }

    public function test_dashboard_chart_helpers_use_group_by(): void
    {
        $source = file_get_contents(base_path('app/Http/Controllers/Api/DashboardController.php'));
        $this->assertNotFalse($source);
        $this->assertStringContainsString('fillListingChartFromDailyCounts', $source);
        $this->assertStringContainsString('scopedListingChartQuery', $source);
        $this->assertStringContainsString('groupBy(\'d\')', $source);
        $this->assertStringContainsString('groupBy(\'m\')', $source);
        $this->assertStringContainsString('DATE(created_at) as d, COUNT(*) as total', $source);
        $this->assertStringContainsString('MONTH(created_at) as m, COUNT(*) as total', $source);
    }

    public function test_property_map_defaults_are_bounded(): void
    {
        $backend = file_get_contents(base_path('app/Http/Controllers/Api/Listing/ListingController.php'));
        $frontend = file_get_contents(base_path('resources/js/components/alllisting/PropertyMap.vue'));
        $this->assertNotFalse($backend);
        $this->assertNotFalse($frontend);
        $this->assertStringContainsString("max:1000", $backend);
        $this->assertStringContainsString("validated['per_page'] ?? 500", $backend);
        $this->assertStringContainsString('per_page: 500', $frontend);
        $this->assertStringNotContainsString('per_page: 3000', $frontend);
    }
}
