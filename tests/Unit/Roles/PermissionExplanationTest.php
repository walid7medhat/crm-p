<?php

namespace Tests\Unit\Roles;

use App\Services\Roles\PermissionCatalog;
use App\Services\Roles\RoleAccessSimulator;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class PermissionExplanationTest extends TestCase
{
    public function test_every_catalog_permission_has_a_short_plain_explanation(): void
    {
        $catalog = new PermissionCatalog();
        $definitions = (new ReflectionMethod(PermissionCatalog::class, 'definitions'))->invoke($catalog);
        $keys = array_keys($definitions);
        $keys[] = 'some-future-key';

        $presented = $catalog->present($keys);
        $seen = 0;

        foreach ($presented['modules'] as $module) {
            foreach ($module['permissions'] as $permission) {
                $seen++;
                $points = $permission['points'];
                $this->assertGreaterThanOrEqual(2, count($points), $permission['key']);
                $this->assertLessThanOrEqual(4, count($points), $permission['key']);
                $this->assertContains($permission['access'], ['allowed', 'conditional', 'restricted', 'unverified']);

                $text = strtolower(implode(' ', $points));
                $this->assertStringNotContainsString('controller', $text, $permission['key']);
                $this->assertStringNotContainsString('::', implode(' ', $points), $permission['key']);
                $this->assertStringNotContainsString('middleware', $text, $permission['key']);
            }
        }

        $this->assertSame(count($keys), $seen);
    }

    public function test_lead_listing_and_user_explanations_do_not_overstate_access(): void
    {
        $catalog = new PermissionCatalog();
        $presented = $catalog->present([
            'show-leads',
            'leads-list',
            'listings-list',
            'users-list',
            'users-edit',
            'settings-view',
        ]);
        $byKey = [];
        foreach ($presented['modules'] as $module) {
            foreach ($module['permissions'] as $permission) {
                $byKey[$permission['key']] = $permission;
            }
        }

        $this->assertStringContainsString('does not decide which', strtolower(implode(' ', $byKey['show-leads']['points'])));
        $this->assertStringContainsString('does not show every lead', strtolower(implode(' ', $byKey['leads-list']['points'])));
        $this->assertStringContainsString('published', strtolower(implode(' ', $byKey['listings-list']['points'])));
        $this->assertStringContainsString('does not open every listing', strtolower(implode(' ', $byKey['listings-list']['points'])));
        $this->assertStringContainsString('people below them', strtolower(implode(' ', $byKey['users-list']['points'])));
        $this->assertSame('restricted', $byKey['users-edit']['access']);
        $this->assertSame('unverified', $byKey['settings-view']['access']);
        $this->assertStringStartsWith('Not verified', $byKey['settings-view']['points'][0]);
    }

    public function test_preview_copy_separates_menu_from_records_without_class_names(): void
    {
        $result = (new RoleAccessSimulator(new PermissionCatalog()))->simulate([
            'user_id' => 50,
            'name' => 'Agent Example',
            'current_roles' => ['sales'],
            'direct_permissions' => [],
            'is_listing_team' => false,
            'branch_label' => 'Dubai',
            'parent_id' => 4,
        ], 'Listing coordinator', ['show-leads', 'listings-list']);

        $scope = null;
        $menu = null;
        foreach ($result['modules'] as $module) {
            foreach ($module['items'] as $item) {
                if ($item['id'] === 'leads.scope') {
                    $scope = $item;
                }
                if ($item['id'] === 'leads.menu') {
                    $menu = $item;
                }
            }
        }

        $this->assertSame('records', $scope['group']);
        $this->assertSame('menu', $menu['group']);
        $this->assertStringNotContainsString('Controller', $scope['brief']);
        $this->assertStringNotContainsString('::', $scope['brief']);
        $this->assertStringContainsString('does not', strtolower($menu['brief']));
    }
}
