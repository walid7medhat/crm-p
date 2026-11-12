<?php

namespace Tests\Unit\Lead;

use App\Http\Controllers\Api\LeadMatchController;
use App\Jobs\RefreshLeadMatchesJob;
use ReflectionClass;
use Tests\TestCase;

/**
 * Cold match scoring must not block GET /leads/{id}/matches (lead open path).
 */
class LeadMatchForLeadAsyncRefreshTest extends TestCase
{
    public function test_for_lead_dispatches_refresh_after_response_instead_of_inline_refresh(): void
    {
        $source = file_get_contents(base_path('app/Http/Controllers/Api/LeadMatchController.php'));
        $this->assertNotFalse($source);
        $this->assertStringContainsString('RefreshLeadMatchesJob::dispatch', $source);
        $this->assertStringContainsString('afterResponse()', $source);
        $this->assertStringContainsString("'refreshing' => \$refreshing", $source);
        $this->assertStringNotContainsString('$this->matcher->refreshLead($lead);', $source);
    }

    public function test_refresh_job_marks_fresh_cache_key(): void
    {
        $source = file_get_contents(base_path('app/Jobs/RefreshLeadMatchesJob.php'));
        $this->assertNotFalse($source);
        $this->assertStringContainsString("Cache::put('lead-matches:fresh:'", $source);

        $controller = new ReflectionClass(LeadMatchController::class);
        $this->assertTrue($controller->hasMethod('forLead'));
        $this->assertTrue(class_exists(RefreshLeadMatchesJob::class));
    }

    public function test_matching_section_soft_refreshes_when_backend_marks_refreshing(): void
    {
        $source = file_get_contents(base_path(
            'resources/js/components/kanban/viewLead/MatchingPropertiesSection.vue'
        ));
        $this->assertNotFalse($source);
        $this->assertStringContainsString('scheduleSoftScoredRefresh', $source);
        $this->assertStringContainsString('data.refreshing', $source);
        $this->assertStringContainsString('fetchScoredMatchPayload', $source);
    }

    public function test_lead_open_defers_portal_and_comments_rail(): void
    {
        $modal = file_get_contents(base_path('resources/js/components/kanban/viewLead/ViewLeadModal.vue'));
        $general = file_get_contents(base_path('resources/js/components/kanban/viewLead/GeneralTab.vue'));
        $this->assertNotFalse($modal);
        $this->assertNotFalse($general);
        $this->assertStringContainsString('scheduleCommentPortalLinks', $modal);
        $this->assertStringContainsString('showCommentsRail', $general);
        $this->assertStringContainsString('scheduleCommentsRail', $general);
    }
}
