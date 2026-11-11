<?php

namespace Tests\Feature\Listing;

use App\Models\Area;
use App\Models\Developer;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Dubai listings are approved by the Dubai branch_admin — not by the listing-team
 * managers, who keep approving everything else.
 */
class ListingDubaiApprovalTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dubai_listing_is_approved_by_the_dubai_branch_admin_only(): void
    {
        $t = $this->scenario();

        $this->asUser($t['listingManager'])
            ->patchJson("/api/listings/properties/{$t['dubaiListing']->id}/approve")
            ->assertForbidden();
        $this->asUser($t['abuBranchAdmin'])
            ->patchJson("/api/listings/properties/{$t['dubaiListing']->id}/approve")
            ->assertForbidden();
        $this->assertFalse((bool) $t['dubaiListing']->fresh()->approved);

        $this->asUser($t['dubaiBranchAdmin'])
            ->patchJson("/api/listings/properties/{$t['dubaiListing']->id}/approve")
            ->assertOk();
        $this->assertTrue((bool) $t['dubaiListing']->fresh()->approved);
    }

    public function test_other_listings_stay_with_the_listing_team(): void
    {
        $t = $this->scenario();

        $this->asUser($t['dubaiBranchAdmin'])
            ->patchJson("/api/listings/properties/{$t['abuListing']->id}/approve")
            ->assertForbidden();

        $this->asUser($t['listingManager'])
            ->patchJson("/api/listings/properties/{$t['abuListing']->id}/approve")
            ->assertOk();
        $this->assertTrue((bool) $t['abuListing']->fresh()->approved);
    }

    public function test_dubai_rejection_follows_the_same_rule(): void
    {
        $t = $this->scenario();

        $this->asUser($t['listingManager'])
            ->patchJson("/api/listings/properties/{$t['dubaiListing']->id}/reject", ['reason' => 'Missing photos'])
            ->assertForbidden();

        $this->asUser($t['dubaiBranchAdmin'])
            ->patchJson("/api/listings/properties/{$t['dubaiListing']->id}/reject", ['reason' => 'Missing photos'])
            ->assertOk();
        $this->assertSame('draft', $t['dubaiListing']->fresh()->status);
    }

    public function test_pending_approvals_only_lists_what_the_user_may_approve(): void
    {
        $t = $this->scenario();

        $dubaiIds = collect($this->asUser($t['dubaiBranchAdmin'])
            ->getJson('/api/listings/pending-approvals?per_page=500')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($dubaiIds->contains($t['dubaiListing']->id));
        $this->assertFalse($dubaiIds->contains($t['abuListing']->id));

        $managerIds = collect($this->asUser($t['listingManager'])
            ->getJson('/api/listings/pending-approvals?per_page=500')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($managerIds->contains($t['abuListing']->id));
        $this->assertFalse($managerIds->contains($t['dubaiListing']->id));

        $this->asUser($t['sales'])->getJson('/api/listings/pending-approvals')->assertForbidden();
    }

    public function test_listing_without_saved_noc_gets_the_developers_dubai_fee(): void
    {
        $t = $this->scenario();
        $developer = Developer::query()->create([
            'name' => 'Test Dubai Developer '.uniqid(),
            'noc_fees_ready' => 1000, 'noc_fees_off_plan' => 2000,
            'dubai_noc_fees_ready' => 525, 'dubai_noc_fees_off_plan' => 5000,
        ]);

        $listing = $t['dubaiListing'];
        $listing->forceFill(['noc_fixed_amount' => 0, 'completion_status' => 'Completed', 'assignment_expense_lines' => []])->save();
        $listing->setRelation('developer', $developer);
        $listing->unsetRelation('project');

        $this->assertTrue($listing->isInDubai());
        $this->assertSame(0.0, $listing->resolvedNocFixedAmount(), 'no breakdown → nothing added');

        $listing->assignment_expense_lines = [['label' => 'DLD Fees', 'calcType' => 'percentage', 'value' => 4]];
        $this->assertSame(525.0, $listing->resolvedNocFixedAmount());

        $listing->completion_status = 'Under Construction';
        $this->assertSame(5000.0, $listing->resolvedNocFixedAmount());

        $listing->noc_fixed_amount = 750; // a saved value always wins
        $this->assertSame(750.0, $listing->resolvedNocFixedAmount());
    }

    private function scenario(): array
    {
        $super = $this->makeUser('super_admin', ['name' => 'Org Root']);
        $dubai = $this->makeUser('admin', ['name' => 'Dubai', 'parent_id' => $super->id]);
        $abu = $this->makeUser('admin', ['name' => 'Abu Dhabi', 'parent_id' => $super->id]);
        $dubaiOffice = $this->makeUser('manager', ['parent_id' => $dubai->id]);
        $abuOffice = $this->makeUser('manager', ['parent_id' => $abu->id]);

        $dubaiCity = Area::query()->create(['name' => 'Dubai', 'type' => 'city']);
        $dubaiCommunity = Area::query()->create(['name' => 'Test Dubai Marina', 'type' => 'community', 'parent_id' => $dubaiCity->id]);
        $abuCity = Area::query()->create(['name' => 'Abu Dhabi', 'type' => 'city']);
        $abuCommunity = Area::query()->create(['name' => 'Test Yas Island', 'type' => 'community', 'parent_id' => $abuCity->id]);

        $sales = $this->makeUser('sales', ['parent_id' => $dubaiOffice->id]);
        [$dubaiListing, $abuListing] = Listing::query()->whereNotNull('id')->limit(2)->get()->all();
        foreach ([[$dubaiListing, $dubaiCommunity], [$abuListing, $abuCommunity]] as [$listing, $area]) {
            $listing->forceFill([
                'area_id' => $area->id,
                'approved' => false,
                'approved_by' => null,
                'approved_at' => null,
                'status' => 'published',
                'is_archived' => false,
                'agent_id' => $sales->id,
            ])->save();
        }

        return [
            'dubaiBranchAdmin' => $this->makeUser('branch_admin', ['parent_id' => $dubaiOffice->id]),
            'abuBranchAdmin' => $this->makeUser('branch_admin', ['parent_id' => $abuOffice->id]),
            'listingManager' => $this->makeUser('manager', ['parent_id' => $abu->id, 'listing_team' => 1]),
            'sales' => $sales,
            'dubaiListing' => $dubaiListing->fresh(),
            'abuListing' => $abuListing->fresh(),
        ];
    }

    private function makeUser(string $role, array $extra = []): User
    {
        $user = User::factory()->create(array_merge([
            'status' => 'active',
            'email' => uniqid($role, true).'@example.test',
        ], $extra));
        Role::findOrCreate($role, 'api');
        $user->assignRole($role);

        return $user;
    }

    private function asUser(User $user): self
    {
        return $this->withHeader('Authorization', 'Bearer '.JWTAuth::fromUser($user));
    }
}
