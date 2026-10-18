<?php

namespace Tests\Feature\Auth;

use App\Jobs\ResolveLoginLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class LoginLocationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasColumn('users', 'last_login_accuracy') || ! Schema::hasColumn('users', 'last_login_location_at')) {
            $this->fail('Run php artisan migrate before login location tests.');
        }
    }

    public function test_sign_in_saves_the_device_fix_for_every_role_and_profile_returns_it(): void
    {
        $this->fakeGeocoder();

        foreach (['sales', 'admin'] as $role) {
            $user = $this->makeUser($role);
            $capturedAt = now()->subMinute()->toIso8601String();

            $response = $this->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'password',
                'latitude' => 25.2048493,
                'longitude' => 55.2707825,
                'accuracy' => 18,
                'captured_at' => $capturedAt,
                'user_id' => 999999,
            ]);

            $response->assertOk()
                ->assertJsonPath('data.user.last_login_location_source', 'device')
                ->assertJsonPath('data.user.last_login_location_precise', true)
                ->assertJsonPath('data.user.last_login_accuracy', 18);

            $user->refresh();
            $this->assertEqualsWithDelta(25.2048493, (float) $user->last_login_lat, 0.0000001);
            $this->assertEqualsWithDelta(55.2707825, (float) $user->last_login_lng, 0.0000001);
            $this->assertSame(18, (int) $user->last_login_accuracy);
            $this->assertNotNull($user->last_login_location_at);
            $this->assertSame('Nearby Street, Abu Dhabi', $user->last_login_location);

            $again = $this->asUser($user)->getJson('/api/profile');
            $again->assertOk()
                ->assertJsonPath('data.last_login_accuracy', 18)
                ->assertJsonPath('data.last_login_location_source', 'device');
            $this->assertEqualsWithDelta(25.2048493, (float) $again->json('data.last_login_lat'), 0.0000001);
            $this->assertEqualsWithDelta(55.2707825, (float) $again->json('data.last_login_lng'), 0.0000001);

            $user->refresh();
            $this->assertEqualsWithDelta(25.2048493, (float) $user->last_login_lat, 0.0000001);
            $this->assertEqualsWithDelta(55.2707825, (float) $user->last_login_lng, 0.0000001);
            $this->flushHeaders();
        }

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'ip-api.com') || str_contains($request->url(), 'ipify.org'));
    }

    public function test_view_user_returns_the_selected_users_saved_coordinates(): void
    {
        $admin = $this->makeUser('admin');
        $sales = $this->makeUser('sales');
        $sales->forceFill([
            'last_login_lat' => 25.2048493,
            'last_login_lng' => 55.2707825,
            'last_login_accuracy' => 18,
            'last_login_location_at' => now(),
            'last_login_location' => 'Marina, Dubai',
        ])->save();

        $first = $this->asUser($admin)->getJson('/api/users/'.$sales->id);
        $second = $this->asUser($admin)->getJson('/api/users/'.$sales->id);

        $first->assertOk()
            ->assertJsonPath('data.id', $sales->id)
            ->assertJsonPath('data.last_login_location_source', 'device')
            ->assertJsonPath('data.last_login_location_precise', true)
            ->assertJsonPath('data.last_login_location', 'Marina, Dubai');
        $this->assertEqualsWithDelta(25.2048493, (float) $first->json('data.last_login_lat'), 0.0000001);
        $this->assertEqualsWithDelta(55.2707825, (float) $first->json('data.last_login_lng'), 0.0000001);
        $this->assertSame($first->json('data.last_login_lat'), $second->json('data.last_login_lat'));
        $this->assertSame($first->json('data.last_login_lng'), $second->json('data.last_login_lng'));

        $sales->refresh();
        $this->assertEqualsWithDelta(25.2048493, (float) $sales->last_login_lat, 0.0000001);
    }

    public function test_a_failed_fix_does_not_invent_coordinates_or_erase_a_saved_one(): void
    {
        Http::fake();

        $fresh = $this->makeUser('sales');
        $this->postJson('/api/auth/login', [
            'email' => $fresh->email,
            'password' => 'password',
            'latitude' => null,
            'longitude' => null,
        ])->assertOk();

        $fresh->refresh();
        $this->assertNull($fresh->last_login_lat);
        $this->assertNull($fresh->last_login_lng);
        $this->assertNull($fresh->last_login_accuracy);

        $saved = $this->makeUser('team_lead');
        $saved->forceFill([
            'last_login_lat' => 25.2048493,
            'last_login_lng' => 55.2707825,
            'last_login_accuracy' => 12,
            'last_login_location_at' => now()->subHour(),
            'last_login_location' => 'Marina, Dubai',
        ])->save();

        $this->postJson('/api/auth/login', [
            'email' => $saved->email,
            'password' => 'password',
        ])->assertOk();

        $saved->refresh();
        $this->assertEqualsWithDelta(25.2048493, (float) $saved->last_login_lat, 0.0000001);
        $this->assertEqualsWithDelta(55.2707825, (float) $saved->last_login_lng, 0.0000001);
        $this->assertSame(12, (int) $saved->last_login_accuracy);
        $this->assertSame('Marina, Dubai', $saved->last_login_location);

        Http::assertNothingSent();
    }

    public function test_an_older_or_coarse_update_cannot_overwrite_a_newer_precise_fix(): void
    {
        $this->fakeGeocoder();
        $user = $this->makeUser('sales');

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'latitude' => 25.2048493,
            'longitude' => 55.2707825,
            'accuracy' => 15,
            'captured_at' => now()->subMinutes(2)->toIso8601String(),
        ])->assertOk();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'latitude' => 24.4539,
            'longitude' => 54.3773,
            'accuracy' => 20,
            'captured_at' => now()->subHour()->toIso8601String(),
        ])->assertOk();

        $user->refresh();
        $this->assertEqualsWithDelta(25.2048493, (float) $user->last_login_lat, 0.0000001);
        $this->assertSame(15, (int) $user->last_login_accuracy);

        $this->asUser($user)->postJson('/api/auth/location', [
            'latitude' => 24.4539,
            'longitude' => 54.3773,
            'accuracy' => 8000,
            'captured_at' => now()->toIso8601String(),
        ])->assertOk()->assertJsonPath('data.location_updated', false);

        $user->refresh();
        $this->assertEqualsWithDelta(25.2048493, (float) $user->last_login_lat, 0.0000001);
        $this->assertEqualsWithDelta(55.2707825, (float) $user->last_login_lng, 0.0000001);
        $this->assertSame(15, (int) $user->last_login_accuracy);
    }

    public function test_a_user_cannot_write_another_users_location(): void
    {
        $this->fakeGeocoder();
        $actor = $this->makeUser('sales');
        $other = $this->makeUser('manager');
        $other->forceFill([
            'last_login_lat' => 25.1111111,
            'last_login_lng' => 55.2222222,
            'last_login_accuracy' => 30,
            'last_login_location_at' => now()->subDay(),
        ])->save();

        $this->asUser($actor)->postJson('/api/auth/location', [
            'user_id' => $other->id,
            'latitude' => 25.2048493,
            'longitude' => 55.2707825,
            'accuracy' => 22,
            'captured_at' => now()->toIso8601String(),
        ])->assertOk()->assertJsonPath('data.location_updated', true);

        $actor->refresh();
        $other->refresh();
        $this->assertEqualsWithDelta(25.2048493, (float) $actor->last_login_lat, 0.0000001);
        $this->assertEqualsWithDelta(25.1111111, (float) $other->last_login_lat, 0.0000001);
        $this->assertEqualsWithDelta(55.2222222, (float) $other->last_login_lng, 0.0000001);
    }

    public function test_invalid_coordinates_are_rejected_and_leave_the_saved_fix_alone(): void
    {
        $user = $this->makeUser('sales');
        $user->forceFill([
            'last_login_lat' => 25.2048493,
            'last_login_lng' => 55.2707825,
            'last_login_accuracy' => 18,
            'last_login_location_at' => now()->subHour(),
        ])->save();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'latitude' => 125,
            'longitude' => 55,
        ])->assertStatus(422);

        $user->refresh();
        $this->assertEqualsWithDelta(25.2048493, (float) $user->last_login_lat, 0.0000001);
        $this->assertSame(18, (int) $user->last_login_accuracy);
    }

    public function test_reverse_geocoding_cannot_move_the_saved_point_or_apply_a_stale_job(): void
    {
        $user = $this->makeUser('sales');
        $user->forceFill([
            'last_login_lat' => 25.2048493,
            'last_login_lng' => 55.2707825,
            'last_login_accuracy' => 18,
            'last_login_location' => 'Marina, Dubai',
            'last_login_location_at' => now(),
        ])->save();

        Http::fake([
            '*' => Http::response([
                'lat' => '24.4539',
                'lon' => '54.3773',
                'display_name' => 'Wrong City Center',
            ], 200),
        ]);

        (new ResolveLoginLocation($user->id, null, null, '8.8.8.8'))->handle();
        (new ResolveLoginLocation($user->id, 24.4539, 54.3773, '8.8.8.8'))->handle();

        $user->refresh();
        $this->assertEqualsWithDelta(25.2048493, (float) $user->last_login_lat, 0.0000001);
        $this->assertEqualsWithDelta(55.2707825, (float) $user->last_login_lng, 0.0000001);
        $this->assertSame('Marina, Dubai', $user->last_login_location);
        Http::assertNothingSent();

        (new ResolveLoginLocation($user->id, 25.2048493, 55.2707825, '8.8.8.8'))->handle();

        $user->refresh();
        $this->assertEqualsWithDelta(25.2048493, (float) $user->last_login_lat, 0.0000001);
        $this->assertEqualsWithDelta(55.2707825, (float) $user->last_login_lng, 0.0000001);
        $this->assertSame('Wrong City Center', $user->last_login_location);
    }

    public function test_a_network_estimate_is_not_returned_as_a_device_position(): void
    {
        $admin = $this->makeUser('super_admin');
        $sales = $this->makeUser('sales');
        $sales->forceFill([
            'last_login_lat' => 25.2,
            'last_login_lng' => 55.27,
            'last_login_location' => '(approx. by IP) Dubai',
        ])->save();

        $this->asUser($admin)->getJson('/api/users/'.$sales->id)
            ->assertOk()
            ->assertJsonPath('data.last_login_location_source', 'ip')
            ->assertJsonPath('data.last_login_location_precise', false);
    }

    private function fakeGeocoder(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                'lat' => '24.4539',
                'lon' => '54.3773',
                'display_name' => 'Nearby Street, Abu Dhabi',
                'address' => ['road' => 'Nearby Street', 'city' => 'Abu Dhabi'],
            ], 200),
        ]);
    }

    private function makeUser(string $role): User
    {
        $user = User::factory()->create([
            'status' => 'active',
            'email' => uniqid($role, true).'@example.test',
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
