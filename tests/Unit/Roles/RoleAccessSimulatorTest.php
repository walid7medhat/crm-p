<?php

namespace Tests\Unit\Roles;

use App\Services\Roles\PermissionCatalog;
use App\Services\Roles\RoleAccessSimulator;
use PHPUnit\Framework\TestCase;

/**
 * Pure tests. They do not boot Laravel and do not touch the CRM database.
 */
class RoleAccessSimulatorTest extends TestCase
{
    private function simulator(): RoleAccessSimulator
    {
        return new RoleAccessSimulator(new PermissionCatalog());
    }

    public function test_custom_role_does_not_inherit_manager_scope_from_similar_permissions(): void
    {
        $result = $this->simulator()->simulate([
            'user_id' => 50,
            'name' => 'Agent Example',
            'current_roles' => ['sales'],
            'direct_permissions' => [],
            'is_listing_team' => false,
            'branch_label' => 'Dubai',
            'parent_id' => 4,
        ], 'Listing coordinator', ['show-leads', 'leads-list', 'leads-edit']);

        $this->assertFalse($result['draft']['role_name_is_builtin']);
        $this->assertTrue($result['draft']['replaces_current_role']);
        $scope = $this->item($result, 'leads', 'leads.scope');
        $this->assertSame('conditional', $scope['state']);
        $this->assertStringContainsString('Kanban query', $scope['detail']);
        $this->assertStringContainsString('Opening a lead by id', $scope['detail']);

        $deal = $this->item($result, 'deals', 'deals.scope');
        $this->assertStringContainsString('show-branch-leads does not widen deals', $deal['detail']);
    }

    public function test_show_leads_without_leads_list_is_a_warning_not_a_block(): void
    {
        $result = $this->simulator()->simulate($this->subject(), 'Night desk', ['show-leads']);

        $ids = array_column($result['warnings'], 'id');
        $this->assertContains('leads-menu-without-api', $ids);
        $this->assertSame('visible', $this->item($result, 'leads', 'leads.menu')['state']);
        $this->assertSame('denied', $this->item($result, 'leads', 'leads.api')['state']);
    }

    public function test_direct_permissions_remain_effective(): void
    {
        $result = $this->simulator()->simulate([
            ...$this->subject(),
            'direct_permissions' => ['marketing'],
        ], 'Night desk', []);

        $this->assertContains('marketing', $result['effective_permissions']);
        $this->assertSame('allowed', $this->item($result, 'other', 'marketing')['state']);
        $this->assertContains('direct-permissions-kept', array_column($result['warnings'], 'id'));
    }

    public function test_user_33_keeps_organization_wide_lead_scope_even_with_a_custom_role(): void
    {
        $result = $this->simulator()->simulate([
            ...$this->subject(),
            'user_id' => 33,
        ], 'Night desk', ['leads-list']);

        $scope = $this->item($result, 'leads', 'leads.scope');
        $this->assertSame('allowed', $scope['state']);
        $this->assertStringContainsString('user 30 or 33', $scope['detail']);
    }

    public function test_branch_admin_role_name_widens_deals_and_show_branch_leads_does_not(): void
    {
        $byName = $this->simulator()->simulate($this->subject(), 'branch_admin', ['show-leads', 'leads-list']);
        $byPermission = $this->simulator()->simulate($this->subject(), 'Night desk', ['show-leads', 'leads-list', 'show-branch-leads']);

        $this->assertStringContainsString('branch_admin', $this->item($byName, 'deals', 'deals.scope')['detail']);
        $this->assertStringContainsString('does not widen deals', $this->item($byPermission, 'deals', 'deals.scope')['detail']);
        $this->assertStringContainsString('Whole branch', $this->item($byPermission, 'leads', 'leads.scope')['detail']);
    }

    public function test_sidebar_hides_leads_without_show_leads_for_a_custom_role(): void
    {
        $result = $this->simulator()->simulate($this->subject(), 'Night desk', ['leads-list']);
        $crm = $this->sidebar($result, 'crm');
        $leads = $this->child($crm, 'leads');

        $this->assertSame('hidden', $leads['state']);
        $this->assertStringContainsString('leads-list alone does not show it', $leads['reason']);
    }

    public function test_catalog_keeps_unknown_database_permissions_visible(): void
    {
        $presented = (new PermissionCatalog())->present(['leads-list', 'some-future-key']);
        $ids = array_column($presented['modules'], 'id');
        $this->assertContains('unmapped', $ids);

        $unmapped = null;
        foreach ($presented['modules'] as $module) {
            if ($module['id'] === 'unmapped') {
                $unmapped = $module;
            }
        }

        $this->assertSame('some-future-key', $unmapped['permissions'][0]['key']);
        $this->assertFalse($unmapped['permissions'][0]['verified']);
    }

    public function test_admin_listings_menu_includes_all_requests_without_listing_permissions(): void
    {
        $result = $this->simulator()->simulate($this->subject(), 'admin', []);
        $crm = $this->sidebar($result, 'crm');
        $listings = $this->child($crm, 'listings-menu');

        $this->assertSame('visible', $listings['state']);
        $this->assertSame('visible', $this->child($listings, 'all-requests')['state']);
        $this->assertNull($this->findChild($listings, 'hot-deals'));
    }

    public function test_listing_team_manager_sees_hot_deals_and_admin_does_not(): void
    {
        $manager = $this->simulator()->simulate([
            ...$this->subject(),
            'is_listing_team' => true,
        ], 'manager', ['listings-list']);

        $custom = $this->simulator()->simulate([
            ...$this->subject(),
            'is_listing_team' => true,
        ], 'Listing coordinator', ['listings-list']);

        $managerListings = $this->child($this->sidebar($manager, 'crm'), 'listings-flat');
        $this->assertSame('conditional', $this->child($managerListings, 'hot-deals')['state']);
        $this->assertSame('conditional', $this->child($managerListings, 'need-approval')['state']);

        $customListings = $this->child($this->sidebar($custom, 'crm'), 'listings-flat');
        $this->assertNull($this->findChild($customListings, 'hot-deals'));
    }

    public function test_user_186_hr_menu_does_not_grant_the_hr_route(): void
    {
        $result = $this->simulator()->simulate([
            ...$this->subject(),
            'user_id' => 186,
        ], 'sales', ['show-leads']);

        $this->assertSame('conditional', $this->sidebar($result, 'hr')['state']);
        $this->assertStringContainsString('user 186', $this->sidebar($result, 'hr')['reason']);
        $this->assertSame('denied', $this->item($result, 'hr', 'hr.route')['state']);
    }

    public function test_admin_hr_route_is_conditional_because_the_menu_hides_it(): void
    {
        $result = $this->simulator()->simulate($this->subject(), 'admin', []);
        $hr = $this->sidebar($result, 'hr');

        $this->assertSame('conditional', $hr['state']);
        $this->assertStringContainsString('URL', $hr['reason']);
    }

    public function test_published_listings_are_not_labeled_as_simple_menu_visibility(): void
    {
        $result = $this->simulator()->simulate($this->subject(), 'Night desk', []);
        $this->assertSame('conditional', $this->item($result, 'listings', 'listings.published')['state']);
        $this->assertNotSame('visible', $this->item($result, 'listings', 'listings.published')['state']);
    }

    public function test_manager_role_name_changes_deal_scope_when_the_same_permissions_do_not(): void
    {
        $permissions = ['show-leads', 'leads-list', 'leads-edit'];
        $manager = $this->simulator()->simulate($this->subject(), 'manager', $permissions);
        $copy = $this->simulator()->simulate($this->subject(), 'manager copy', $permissions);

        $this->assertStringContainsString('people below', $this->item($manager, 'deals', 'deals.scope')['detail']);
        $this->assertStringContainsString('does not widen deals', $this->item($copy, 'deals', 'deals.scope')['detail']);
        $this->assertStringContainsString('only leads assigned to this employee', $this->item($copy, 'leads', 'leads.scope')['detail']);
    }

    public function test_super_admin_preview_still_describes_existing_bypass(): void
    {
        $result = $this->simulator()->simulate($this->subject(), 'super_admin', []);
        $this->assertSame('allowed', $this->item($result, 'leads', 'leads.scope')['state']);
        $this->assertSame('visible', $this->sidebar($result, 'hr')['state']);
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, string>
     */
    private function item(array $result, string $moduleId, string $itemId): array
    {
        foreach ($result['modules'] as $module) {
            if ($module['id'] !== $moduleId) {
                continue;
            }
            foreach ($module['items'] as $item) {
                if ($item['id'] === $itemId) {
                    return $item;
                }
            }
        }

        $this->fail("Missing {$moduleId}.{$itemId}");
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function sidebar(array $result, string $id): array
    {
        foreach ($result['sidebar'] as $item) {
            if ($item['id'] === $id) {
                return $item;
            }
        }

        $this->fail("Missing sidebar {$id}");
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function child(array $node, string $id): array
    {
        $found = $this->findChild($node, $id);
        if ($found === null) {
            $this->fail("Missing child {$id}");
        }

        return $found;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>|null
     */
    private function findChild(array $node, string $id): ?array
    {
        foreach ($node['children'] as $child) {
            if ($child['id'] === $id) {
                return $child;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function subject(): array
    {
        return [
            'user_id' => 50,
            'name' => 'Agent Example',
            'current_roles' => ['sales'],
            'direct_permissions' => [],
            'is_listing_team' => false,
            'branch_label' => 'Dubai',
            'parent_id' => 4,
        ];
    }
}
