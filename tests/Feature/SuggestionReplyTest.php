<?php

namespace Tests\Feature;

use App\Models\Suggestion;
use App\Models\SuggestionReply;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Suggestion list scoping and official OIA Properties replies.
 * Uses the live database, same as the other CRM feature suites, and deletes
 * only the rows it creates.
 */
class SuggestionReplyTest extends TestCase
{
    /** @var array<int, int> */
    private array $userIds = [];

    protected function tearDown(): void
    {
        if ($this->userIds !== []) {
            $suggestionIds = Suggestion::query()->whereIn('user_id', $this->userIds)->pluck('id');
            SuggestionReply::query()->whereIn('suggestion_id', $suggestionIds)->delete();
            Suggestion::query()->whereIn('id', $suggestionIds)->delete();

            User::query()->whereIn('id', $this->userIds)->each(function (User $user) {
                $user->syncRoles([]);
                $user->delete();
            });
        }

        parent::tearDown();
    }

    public function test_employee_can_create_a_suggestion_and_only_sees_their_own(): void
    {
        $employee = $this->makeUser('Suggestion Employee');
        $other = $this->makeUser('Other Employee');

        $create = $this->asUser($employee)->postJson('/api/suggestions', [
            'content' => 'Add a quieter focus room.',
        ]);

        $create->assertCreated();
        $create->assertJsonPath('message', 'Suggestion submitted successfully.');
        $ownId = $create->json('suggestion.id');
        $this->assertNotNull($ownId);

        Suggestion::create([
            'user_id' => $other->id,
            'content' => 'This belongs to someone else.',
        ]);

        $list = $this->asUser($employee)->getJson('/api/suggestions');
        $list->assertOk();

        $ids = collect($list->json('suggestions'))->pluck('id')->all();
        $this->assertContains($ownId, $ids);
        $this->assertNotContains(
            Suggestion::query()->where('user_id', $other->id)->value('id'),
            $ids
        );
        $this->assertFalse(
            collect($list->json('suggestions'))->contains(
                fn ($row) => str_contains((string) ($row['content'] ?? ''), 'someone else')
            )
        );
    }

    public function test_admin_and_super_admin_see_all_suggestions(): void
    {
        $employee = $this->makeUser('Listed Employee');
        $admin = $this->makeUser('Listing Admin', 'admin');
        $superAdmin = $this->makeUser('Listing Super', 'super_admin');

        $suggestion = Suggestion::create([
            'user_id' => $employee->id,
            'content' => 'Visible to managers only.',
        ]);

        foreach ([$admin, $superAdmin] as $manager) {
            $list = $this->asUser($manager)->getJson('/api/suggestions');
            $list->assertOk();
            $this->assertTrue(
                collect($list->json('suggestions'))->contains(fn ($row) => (int) $row['id'] === (int) $suggestion->id)
            );
        }
    }

    public function test_employee_cannot_reply_and_managers_can(): void
    {
        $employee = $this->makeUser('Reply Employee');
        $admin = $this->makeUser('Reply Admin Secret', 'admin');
        $superAdmin = $this->makeUser('Reply Super Secret', 'super_admin');

        $suggestion = Suggestion::create([
            'user_id' => $employee->id,
            'content' => 'Please add a dark mode.',
        ]);

        $denied = $this->asUser($employee)->postJson("/api/suggestions/{$suggestion->id}/replies", [
            'content' => 'I should not be able to reply.',
        ]);
        $denied->assertForbidden();
        $this->assertSame(0, SuggestionReply::query()->where('suggestion_id', $suggestion->id)->count());

        $adminReply = $this->asUser($admin)->postJson("/api/suggestions/{$suggestion->id}/replies", [
            'content' => 'Thank you. We will review this with the team.',
        ]);
        $adminReply->assertCreated();
        $adminReply->assertJsonPath('reply.sender', 'OIA Properties');
        $adminReply->assertJsonPath('reply.user_id', $admin->id);
        $adminReply->assertJsonMissingPath('reply.user.name');
        $this->assertDatabaseHas('suggestion_replies', [
            'suggestion_id' => $suggestion->id,
            'user_id' => $admin->id,
            'content' => 'Thank you. We will review this with the team.',
        ]);

        $superReply = $this->asUser($superAdmin)->postJson("/api/suggestions/{$suggestion->id}/replies", [
            'content' => 'We have shared this with the product team.',
        ]);
        $superReply->assertCreated();
        $superReply->assertJsonPath('reply.sender', 'OIA Properties');
        $superReply->assertJsonPath('reply.user_id', $superAdmin->id);

        $employeeView = $this->asUser($employee)->getJson('/api/suggestions');
        $employeeView->assertOk();
        $row = collect($employeeView->json('suggestions'))->firstWhere('id', $suggestion->id);
        $this->assertNotNull($row);
        $this->assertCount(2, $row['replies']);
        $this->assertSame('OIA Properties', $row['replies'][0]['sender']);
        $this->assertSame('Thank you. We will review this with the team.', $row['replies'][0]['content']);
        $this->assertArrayNotHasKey('user_id', $row['replies'][0]);
        $this->assertStringNotContainsString('Reply Admin Secret', $employeeView->getContent());
        $this->assertStringNotContainsString('Reply Super Secret', $employeeView->getContent());
    }

    public function test_empty_replies_are_rejected_and_rapid_duplicates_are_not_stored_twice(): void
    {
        $employee = $this->makeUser('Dup Employee');
        $admin = $this->makeUser('Dup Admin', 'admin');
        $suggestion = Suggestion::create([
            'user_id' => $employee->id,
            'content' => 'Longer lunch break.',
        ]);

        $this->asUser($admin)->postJson("/api/suggestions/{$suggestion->id}/replies", [
            'content' => '   ',
        ])->assertStatus(422);

        $this->asUser($admin)->postJson("/api/suggestions/{$suggestion->id}/replies", [
            'content' => '',
        ])->assertStatus(422);

        $first = $this->asUser($admin)->postJson("/api/suggestions/{$suggestion->id}/replies", [
            'content' => 'Noted.',
        ]);
        $first->assertCreated();

        $second = $this->asUser($admin)->postJson("/api/suggestions/{$suggestion->id}/replies", [
            'content' => 'Noted.',
        ]);
        $second->assertOk();
        $second->assertJsonPath('reply.id', $first->json('reply.id'));
        $this->assertSame(1, SuggestionReply::query()->where('suggestion_id', $suggestion->id)->count());
    }

    public function test_guest_cannot_list_or_reply(): void
    {
        $employee = $this->makeUser('Guest Target');
        $suggestion = Suggestion::create([
            'user_id' => $employee->id,
            'content' => 'Private suggestion.',
        ]);

        $this->getJson('/api/suggestions')->assertUnauthorized();
        $this->postJson("/api/suggestions/{$suggestion->id}/replies", [
            'content' => 'No.',
        ])->assertUnauthorized();
    }

    private function makeUser(string $name, ?string $role = null): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'status' => 'active',
        ]);
        $this->userIds[] = $user->id;

        if ($role !== null) {
            Role::findOrCreate($role, 'api');
            $user->assignRole($role);
        }

        return $user;
    }

    private function asUser(User $user): self
    {
        return $this->withHeader('Authorization', 'Bearer '.JWTAuth::fromUser($user));
    }
}
