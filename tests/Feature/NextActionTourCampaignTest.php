<?php

namespace Tests\Feature;

use App\Models\NextActionTourCampaign;
use App\Models\User;
use App\Services\NextActionTour\NextActionTourCampaignService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class NextActionTourCampaignTest extends TestCase
{
    use DatabaseTransactions;

    public function test_super_admin_can_start_and_stop_campaign(): void
    {
        Event::fake();
        $admin = $this->makeUser('super_admin');

        $start = $this->asUser($admin)->postJson('/api/next-action-tour/admin/start');
        $start->assertOk();
        $this->assertTrue($start->json('data.is_active'));
        $launchId = $start->json('data.launch_id');
        $this->assertNotEmpty($launchId);

        $me = $this->asUser($admin)->getJson('/api/next-action-tour/me');
        $me->assertOk();
        $this->assertTrue($me->json('data.should_show'));

        $stop = $this->asUser($admin)->postJson('/api/next-action-tour/admin/stop');
        $stop->assertOk();
        $this->assertFalse($stop->json('data.is_active'));
    }

    public function test_user_is_capped_at_three_views_per_launch(): void
    {
        Event::fake();
        $admin = $this->makeUser('super_admin');
        $agent = $this->makeUser('sales');
        $service = app(NextActionTourCampaignService::class);

        $status = $service->start($admin);
        $launchId = $status['launch_id'];

        for ($i = 0; $i < 3; $i++) {
            $shown = $service->recordShown($agent, $launchId);
            $this->assertSame($i + 1, $shown['views']);
        }

        $after = $service->recordShown($agent, $launchId);
        $this->assertSame(3, $after['views']);
        $this->assertFalse($after['should_show']);
    }

    public function test_non_super_admin_cannot_start_campaign(): void
    {
        $agent = $this->makeUser('sales');
        $this->asUser($agent)->postJson('/api/next-action-tour/admin/start')->assertStatus(403);
    }

    private function makeUser(string $role): User
    {
        Role::findOrCreate($role);
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole($role);

        return $user;
    }

    private function asUser(User $user)
    {
        $token = JWTAuth::fromUser($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
