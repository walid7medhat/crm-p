<?php

namespace Tests\Unit;

use App\Models\Lead;
use App\Models\User;
use Tests\TestCase;

/** Shared Leads (stage 9): admins / branch admins of the branch the lead is shared WITH see it. */
class SharedLeadsBranchVisibilityTest extends TestCase
{
    private function viewer(array $roles, ?int $branchNodeId, string $branchNodeName = ''): User
    {
        $u = new class extends User {
            public array $fakeRoles = [];
            public ?User $fakeNode = null;

            public function hasAnyRole(...$roles): bool
            {
                $flat = [];
                array_walk_recursive($roles, function ($r) use (&$flat) { $flat[] = $r; });

                return (bool) array_intersect($flat, $this->fakeRoles);
            }

            public function getBranchNode(): ?User
            {
                return $this->fakeNode;
            }
        };
        $u->fakeRoles = $roles;
        if ($branchNodeId) {
            $node = new User();
            $node->forceFill(['id' => $branchNodeId, 'name' => $branchNodeName]);
            $u->fakeNode = $node;
        }

        return $u;
    }

    private function lead(int $stageId, ?string $branch): Lead
    {
        $lead = new Lead();
        $lead->forceFill(['stage_id' => $stageId, 'branch' => $branch]);

        return $lead;
    }

    public function test_abu_dhabi_admins_see_lead_shared_from_dubai(): void
    {
        $lead = $this->lead(User::SHARED_LEADS_STAGE_ID, 'Abu Dhabi');

        $this->assertTrue($this->viewer(['branch_admin'], 25, 'abu dhabi')->seesSharedLead($lead));
        $this->assertTrue($this->viewer(['admin'], 25, 'abu dhabi')->seesSharedLead($lead));
        $this->assertFalse($this->viewer(['branch_admin'], 59, 'Dubai')->seesSharedLead($lead));
    }

    public function test_dubai_admins_see_lead_shared_from_abu_dhabi(): void
    {
        $lead = $this->lead(User::SHARED_LEADS_STAGE_ID, 'Dubai');

        $this->assertTrue($this->viewer(['branch_admin'], 59, 'Dubai')->seesSharedLead($lead));
        $this->assertFalse($this->viewer(['admin'], 25, 'abu dhabi')->seesSharedLead($lead));
    }

    public function test_only_shared_stage_and_only_admin_roles(): void
    {
        $this->assertFalse($this->viewer(['branch_admin'], 25)->seesSharedLead($this->lead(3, 'Abu Dhabi')));
        $this->assertFalse($this->viewer(['sales'], 25)->seesSharedLead($this->lead(User::SHARED_LEADS_STAGE_ID, 'Abu Dhabi')));
    }
}
