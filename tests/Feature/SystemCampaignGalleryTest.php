<?php

namespace Tests\Feature;

use App\Models\SystemCampaign;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class SystemCampaignGalleryTest extends TestCase
{
    /** @var array<int, int> */
    private array $userIds = [];

    /** @var array<int, int> */
    private array $campaignIds = [];

    protected function tearDown(): void
    {
        if ($this->campaignIds !== []) {
            SystemCampaign::query()->whereIn('id', $this->campaignIds)->delete();
        }

        if ($this->userIds !== []) {
            User::query()->whereIn('id', $this->userIds)->each(function (User $user) {
                $user->syncRoles([]);
                $user->delete();
            });
        }

        parent::tearDown();
    }

    public function test_active_sales_sees_only_active_sales_announcements_newest_first(): void
    {
        $sales = $this->makeUser('Gallery Sales', 'sales');
        $older = $this->makeCampaign($sales, 'Older launch', true);
        $newer = $this->makeCampaign($sales, 'Newer launch', true);
        $paused = $this->makeCampaign($sales, 'Paused launch', false);
        $other = $this->makeCampaign($sales, 'Hidden audience', true, ['someone_else']);

        $response = $this->asUser($sales)->getJson('/api/system-campaigns/gallery');

        $response->assertOk();
        $response->assertJsonPath('status', true);

        $rows = collect($response->json('data'));
        $titles = $rows->pluck('title');
        $this->assertTrue($titles->contains('Newer launch'));
        $this->assertTrue($titles->contains('Older launch'));
        $this->assertLessThan(
            $titles->search('Older launch'),
            $titles->search('Newer launch')
        );
        $this->assertTrue($titles->contains('Hidden audience'));
        $this->assertFalse($titles->contains('Paused launch'));
        $this->assertStringContainsString(
            'system-campaigns/desktop-newer-launch.webp',
            (string) $rows->firstWhere('title', 'Newer launch')['desktop_image_url']
        );
        $this->assertNotNull($older->id);
        $this->assertNotNull($newer->id);
        $this->assertNotNull($paused->id);
        $this->assertNotNull($other->id);
    }

    public function test_any_signed_in_user_sees_active_announcements(): void
    {
        $creator = $this->makeUser('Gallery Creator', 'sales');
        $this->makeCampaign($creator, 'Sales only', true);
        $inactive = $this->makeUser('Inactive Sales', 'sales', 'in_active');
        $admin = $this->makeUser('Gallery Admin', 'admin');

        $this->asUser($inactive)->getJson('/api/system-campaigns/gallery')
            ->assertForbidden();

        $titles = collect(
            $this->asUser($admin)->getJson('/api/system-campaigns/gallery')->assertOk()->json('data')
        )->pluck('title');

        $this->assertTrue($titles->contains('Sales only'));
    }

    public function test_super_admin_can_preview_every_active_announcement(): void
    {
        $creator = $this->makeUser('Preview Creator', 'sales');
        $this->makeCampaign($creator, 'Preview launch', true);
        $super = $this->makeUser('Preview Super', 'super_admin');

        $titles = collect(
            $this->asUser($super)->getJson('/api/system-campaigns/gallery')->assertOk()->json('data')
        )->pluck('title');

        $this->assertTrue($titles->contains('Preview launch'));
    }

    public function test_guest_cannot_browse_announcements(): void
    {
        $this->getJson('/api/system-campaigns/gallery')->assertUnauthorized();
    }

    private function makeUser(string $name, ?string $role = null, string $status = 'active'): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'status' => $status,
        ]);
        $this->userIds[] = $user->id;

        if ($role !== null) {
            Role::findOrCreate($role, 'api');
            $user->assignRole($role);
        }

        return $user;
    }

    private function makeCampaign(User $creator, string $title, bool $active, array $audiences = ['sales_active']): SystemCampaign
    {
        $slug = strtolower(str_replace(' ', '-', $title));
        $campaign = SystemCampaign::create([
            'title' => $title,
            'audiences' => $audiences,
            'frequency_hours' => 4,
            'is_active' => $active,
            'desktop_image_path' => "system-campaigns/desktop-{$slug}.webp",
            'mobile_image_path' => "system-campaigns/mobile-{$slug}.webp",
            'created_by' => $creator->id,
        ]);
        $this->campaignIds[] = $campaign->id;

        return $campaign;
    }

    private function asUser(User $user): self
    {
        return $this->withHeader('Authorization', 'Bearer '.JWTAuth::fromUser($user));
    }
}
