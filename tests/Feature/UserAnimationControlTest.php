<?php

namespace Tests\Feature;

use App\Events\UserAnimationUpdated;
use App\Models\User;
use App\Models\UserAnimationState;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class UserAnimationControlTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('user_animation_states')) {
            $this->fail('Run php artisan migrate before user animation tests.');
        }

        Permission::findOrCreate('user-animation-control', 'api');
    }

    public function test_guests_and_users_without_the_permission_cannot_control_animations(): void
    {
        $target = $this->makeUser('sales');

        $this->getJson('/api/user-animations/me')->assertUnauthorized();
        $this->postJson('/api/user-animations/users/'.$target->id.'/start', [
            'animation_id' => 'cat-dancing',
        ])->assertUnauthorized();

        $sales = $this->makeUser('sales');
        $admin = $this->makeUser('admin');

        foreach ([$sales, $admin] as $actor) {
            $this->asUser($actor)->getJson('/api/user-animations/catalog')->assertForbidden();
            $this->asUser($actor)->getJson('/api/user-animations/users?search=a')->assertForbidden();
            $this->asUser($actor)->postJson('/api/user-animations/users/'.$target->id.'/start', [
                'animation_id' => 'cat-dancing',
            ])->assertForbidden();
            $this->asUser($actor)->postJson('/api/user-animations/users/'.$target->id.'/stop')->assertForbidden();
        }

        $this->assertNull(UserAnimationState::query()->where('user_id', $target->id)->first());
    }

    public function test_start_and_stop_affect_only_the_selected_user(): void
    {
        Event::fake([UserAnimationUpdated::class]);

        $operator = $this->makeUser('sales');
        $operator->givePermissionTo('user-animation-control');
        $first = $this->makeUser('sales');
        $second = $this->makeUser('sales');

        $this->asUser($operator)->postJson('/api/user-animations/users/'.$first->id.'/start', [
            'animation_id' => 'cat-dancing',
        ])->assertOk()
            ->assertJsonPath('data.animation.enabled', true)
            ->assertJsonPath('data.animation.animation_id', 'cat-dancing')
            ->assertJsonPath('data.animation.src', '/animations/cat-dancing.webm')
            ->assertJsonPath('data.animation.kind', 'video')
            ->assertJsonPath('data.command_accepted', true)
            ->assertJsonPath('data.playback_confirmed', false)
            ->assertJsonPath('data.user.id', $first->id);

        $this->asUser($operator)->postJson('/api/user-animations/users/'.$second->id.'/start', [
            'animation_id' => 'cat-dancing',
        ])->assertOk()->assertJsonPath('data.user.id', $second->id);

        $this->assertTrue((bool) UserAnimationState::query()->where('user_id', $first->id)->value('enabled'));
        $this->assertSame('cat-dancing', UserAnimationState::query()->where('user_id', $first->id)->value('animation_id'));
        $this->assertSame('cat-dancing', UserAnimationState::query()->where('user_id', $second->id)->value('animation_id'));

        Event::assertDispatched(UserAnimationUpdated::class, function (UserAnimationUpdated $event) use ($first) {
            return $event->userId === (int) $first->id
                && $event->enabled === true
                && $event->animationId === 'cat-dancing'
                && $event->src === '/animations/cat-dancing.webm'
                && count($event->broadcastOn()) === 1
                && $event->broadcastOn()[0]->name === 'private-user.'.$first->id;
        });

        $this->asUser($operator)->postJson('/api/user-animations/users/'.$second->id.'/stop')
            ->assertOk()
            ->assertJsonPath('data.animation.enabled', false)
            ->assertJsonPath('data.animation.animation_id', null);

        $this->assertFalse((bool) UserAnimationState::query()->where('user_id', $second->id)->value('enabled'));
        $this->assertNull(UserAnimationState::query()->where('user_id', $second->id)->value('animation_id'));
        $this->assertTrue((bool) UserAnimationState::query()->where('user_id', $first->id)->value('enabled'));
        $this->assertSame('cat-dancing', UserAnimationState::query()->where('user_id', $first->id)->value('animation_id'));

        $this->asUser($second)->getJson('/api/user-animations/me')
            ->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.animation_id', null);

        $this->asUser($first)->getJson('/api/user-animations/me')
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.animation_id', 'cat-dancing')
            ->assertJsonPath('data.src', '/animations/cat-dancing.webm');
    }

    public function test_super_admin_can_search_and_unknown_animation_is_rejected(): void
    {
        $admin = $this->makeUser('super_admin');
        $target = $this->makeUser('sales');
        $target->forceFill(['name' => 'Animation Target Unique'])->save();

        $this->asUser($admin)->getJson('/api/user-animations/users?search=Animation%20Target%20Unique')
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $target->id);

        $this->asUser($admin)->postJson('/api/user-animations/users/'.$target->id.'/start', [
            'animation_id' => 'not-a-real-animation',
        ])->assertStatus(422);

        $this->assertNull(UserAnimationState::query()->where('user_id', $target->id)->first());
    }

    public function test_stop_increments_version_so_an_older_start_cannot_win(): void
    {
        Event::fake([UserAnimationUpdated::class]);
        $admin = $this->makeUser('super_admin');
        $target = $this->makeUser('sales');

        $this->asUser($admin)->postJson('/api/user-animations/users/'.$target->id.'/start', [
            'animation_id' => 'cat-dancing',
        ])->assertOk();
        $started = (int) UserAnimationState::query()->where('user_id', $target->id)->value('version');

        $this->asUser($admin)->postJson('/api/user-animations/users/'.$target->id.'/stop')->assertOk();
        $stopped = UserAnimationState::query()->where('user_id', $target->id)->first();

        $this->assertFalse($stopped->enabled);
        $this->assertGreaterThan($started, (int) $stopped->version);

        $this->asUser($admin)->postJson('/api/user-animations/users/'.$target->id.'/stop')->assertOk();
        $this->assertSame((int) $stopped->version, (int) $stopped->fresh()->version);
    }

    private function makeUser(string $role, string $status = 'active'): User
    {
        $user = User::factory()->create([
            'status' => $status,
            'email' => uniqid($role, true).'@example.test',
            'last_login_at' => now(),
        ]);
        Role::findOrCreate($role, 'api');
        $user->assignRole($role);

        return $user;
    }

    private function asUser(User $user): self
    {
        return $this->withHeader('Authorization', 'Bearer '.JWTAuth::fromUser($user));
    }
}
