<?php

namespace Tests\Feature;

use App\Events\LeadUpdated;
use App\Jobs\ProcessLeadAutoAssignmentJob;
use App\Jobs\ProcessLeadIntelligenceJob;
use App\Models\Lead;
use App\Models\User;
use App\Support\LeadTracking;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * gclid / utm_* / landing_page from WordPress: saved in their own columns, kept out of the
 * questions list, and visible only to super_admin and user #33.
 */
class WordpressLeadTrackingTest extends TestCase
{
    private const EMAIL = 'wordpress-lead-tracking-test@example.com';

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([LeadUpdated::class]);
        Bus::fake([ProcessLeadAutoAssignmentJob::class, ProcessLeadIntelligenceJob::class]);
    }

    protected function tearDown(): void
    {
        Lead::withTrashed()->where('email', self::EMAIL)->forceDelete();
        parent::tearDown();
    }

    public function test_wordpress_tracking_is_saved_and_hidden_from_questions(): void
    {
        $this->postJson('/api/website-lead/wordpress', [
            'Page_Name' => 'WP Tracking',
            'No_Label_name' => 'Tracker',
            'No_Label_email' => self::EMAIL,
            'No_Label_phone' => '0500000099',
            'Budget' => '2M',
            'gclid' => 'Cj0KCQ-test',
            'UTM_Source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'dubai-hills',
            'utm_term' => 'villa dubai',
            'utm_content' => 'ad-1',
            'landing_page' => 'https://example.com/dubai-hills?x=1',
            'utm_empty' => '',
        ])->assertOk()->assertJsonPath('status', 'success');

        $lead = Lead::where('email', self::EMAIL)->latest('id')->firstOrFail();

        $this->assertSame('Cj0KCQ-test', $lead->gclid);
        $this->assertSame('google', $lead->utm_source);
        $this->assertSame('cpc', $lead->utm_medium);
        $this->assertSame('dubai-hills', $lead->utm_campaign);
        $this->assertSame('villa dubai', $lead->utm_term);
        $this->assertSame('ad-1', $lead->utm_content);
        $this->assertSame('https://example.com/dubai-hills?x=1', $lead->landing_page);

        $names = array_column(json_decode($lead->raw_meta_data, true)['field_data'], 'name');
        $this->assertContains('Budget', $names);
        foreach ($names as $name) {
            $this->assertFalse(LeadTracking::isTrackingKey($name), "$name should not be a question");
        }
    }

    public function test_tracking_visibility_by_role(): void
    {
        $lead = (new Lead)->forceFill(['utm_source' => 'google', 'gclid' => 'abc']);

        $this->assertTrue(LeadTracking::canView($this->userWithRole('super_admin', 990001)));
        $this->assertTrue(LeadTracking::canView($this->userWithRole('sales', 33)));
        $this->assertFalse(LeadTracking::canView($this->userWithRole('admin', 990002)));
        $this->assertFalse(LeadTracking::canView($this->userWithRole('admin', 30)));
        $this->assertFalse(LeadTracking::canView($this->userWithRole('sales', 990003)));
        $this->assertFalse(LeadTracking::canView(null));

        $this->assertSame('google', LeadTracking::forLead($lead)['utm_source']);
        $this->assertNull(LeadTracking::forLead(new Lead));
    }

    private function userWithRole(string $role, int $id): User
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->id = $id;
        $user->shouldReceive('hasRole')->andReturnUsing(
            fn ($roles) => in_array($role, (array) $roles, true)
        );

        return $user;
    }
}
