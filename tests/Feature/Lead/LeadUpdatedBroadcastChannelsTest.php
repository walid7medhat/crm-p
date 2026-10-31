<?php

namespace Tests\Feature\Lead;

use App\Events\LeadUpdated;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use ReflectionMethod;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * LeadUpdated::getUserChannels must not call auth()->user() (queue workers have
 * no auth). Branch-admin "updated" skip of the responsible channel is preserved
 * via the event's userId.
 */
class LeadUpdatedBroadcastChannelsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_non_branch_admin_updated_includes_responsible_channel(): void
    {
        [$lead, $responsible, $actor] = $this->fixtureLeadWithResponsible('sales');

        $this->clearAuth();
        $channels = $this->channelNames(new LeadUpdated($lead, 'updated', $actor->id, null, 'crm'));

        $this->assertContains('private-user.'.$responsible->id, $channels);
    }

    public function test_branch_admin_updated_omits_responsible_channel(): void
    {
        [$lead, $responsible, $actor] = $this->fixtureLeadWithResponsible('branch_admin');

        $this->clearAuth();
        $channels = $this->channelNames(new LeadUpdated($lead, 'updated', $actor->id, null, 'crm'));

        $this->assertNotContains('private-user.'.$responsible->id, $channels);
    }

    public function test_null_user_id_updated_does_not_throw_and_includes_responsible(): void
    {
        [$lead, $responsible] = $this->fixtureLeadWithResponsible('sales');

        $this->clearAuth();
        $channels = $this->channelNames(new LeadUpdated($lead, 'updated', null, null, 'crm'));

        $this->assertContains('private-user.'.$responsible->id, $channels);
    }

    public function test_deleted_actor_updated_does_not_throw_and_includes_responsible(): void
    {
        [$lead, $responsible, $actor] = $this->fixtureLeadWithResponsible('sales');
        $missingId = (int) $actor->id + 9_000_000;
        while (User::query()->whereKey($missingId)->exists()) {
            $missingId++;
        }

        $this->clearAuth();
        $channels = $this->channelNames(new LeadUpdated($lead, 'updated', $missingId, null, 'crm'));

        $this->assertContains('private-user.'.$responsible->id, $channels);
    }

    public function test_stage_changed_without_auth_does_not_depend_on_auth_user(): void
    {
        [$lead, $responsible, $actor] = $this->fixtureLeadWithResponsible('branch_admin');

        $this->clearAuth();
        $this->assertNull(Auth::user());

        $channels = $this->channelNames(new LeadUpdated(
            $lead,
            'stage_changed',
            $actor->id,
            ['old_stage' => 'A', 'new_stage' => 'B'],
            'crm'
        ));

        // Non-updated action types never applied the branch_admin skip.
        $this->assertContains('private-user.'.$responsible->id, $channels);
    }

    public function test_broadcast_as_and_payload_keys_unchanged(): void
    {
        [$lead, , $actor] = $this->fixtureLeadWithResponsible('sales');

        $event = new LeadUpdated($lead, 'updated', $actor->id, ['updated_fields' => ['lead_name']], 'crm');

        $this->assertSame('lead.updated', $event->broadcastAs());
        $payload = $event->broadcastWith();
        foreach (['lead_id', 'lead', 'action_type', 'source', 'user_id', 'user_name', 'changes', 'message', 'timestamp'] as $key) {
            $this->assertArrayHasKey($key, $payload);
        }
        $this->assertSame('updated', $payload['action_type']);
        $this->assertSame($actor->id, $payload['user_id']);
        $this->assertSame($lead->id, $payload['lead_id']);
    }

    /**
     * @return array{0: Lead, 1: User, 2: User}
     */
    private function fixtureLeadWithResponsible(string $actorRole): array
    {
        $lead = Lead::query()->whereNotNull('responsible_person_id')->first()
            ?? Lead::query()->first();
        $this->assertNotNull($lead, 'Need a lead fixture');

        // Prefer a non-super_admin sales user so the responsible channel is
        // not re-added via the unconditional super_admin fan-out.
        Role::findOrCreate('sales', 'api');
        $responsible = User::role('sales')->whereDoesntHave('roles', function ($q) {
            $q->where('name', 'super_admin');
        })->first();
        if (! $responsible) {
            $responsible = User::factory()->create([
                'name' => 'Broadcast Responsible',
                'email' => 'broadcast-responsible-'.uniqid().'@example.test',
            ]);
            $responsible->assignRole('sales');
        }

        // Transaction-scoped mutation only — no new lead row (avoids unique bitrix/phone conflicts).
        $lead->responsible_person_id = $responsible->id;
        $lead->save();

        Role::findOrCreate($actorRole, 'api');
        $actor = User::factory()->create([
            'name' => 'Broadcast Actor '.$actorRole,
            'email' => 'broadcast-actor-'.$actorRole.'-'.uniqid().'@example.test',
        ]);
        $actor->syncRoles([$actorRole]);
        $this->assertTrue(
            $actor->fresh()->hasRole($actorRole),
            "Actor must have role {$actorRole} on api guard"
        );

        $lead->load(['stage', 'responsiblePerson', 'addedBy', 'participants', 'observers.user']);

        return [$lead->fresh(), $responsible->fresh(), $actor->fresh()];
    }

    private function clearAuth(): void
    {
        // Avoid JWTGuard::logout() which requires a request token.
        // Keep the default api guard so Spatie hasRole() matches production.
        Auth::forgetGuards();
        auth()->shouldUse('api');
    }

    /**
     * @return list<string>
     */
    private function channelNames(LeadUpdated $event): array
    {
        $method = new ReflectionMethod(LeadUpdated::class, 'getUserChannels');
        $method->setAccessible(true);
        /** @var array<int, PrivateChannel> $channels */
        $channels = $method->invoke($event);

        return array_map(
            fn ($channel) => $channel instanceof PrivateChannel ? $channel->name : (string) $channel,
            $channels
        );
    }
}
