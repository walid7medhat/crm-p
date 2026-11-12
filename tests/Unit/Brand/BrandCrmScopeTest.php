<?php

namespace Tests\Unit\Brand;

use App\Support\Brand\BrandContext;
use App\Support\Brand\BrandCrmScope;
use Tests\TestCase;

class BrandCrmScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        BrandCrmScope::reset();
    }

    public function test_inactive_on_oia_brand(): void
    {
        $ctx = app(BrandContext::class);
        $ctx->set(config('brands.brands.oia'));

        $this->assertFalse(BrandCrmScope::active());
    }

    public function test_active_on_luxvant_brand_when_scope_enabled(): void
    {
        $ctx = app(BrandContext::class);
        $ctx->set(config('brands.brands.luxvant'));

        $this->assertTrue(BrandCrmScope::active());
        $this->assertSame('Admin Luxvant', config('brands.brands.luxvant.crm_scope.branch_user_name'));
    }

    public function test_config_contains_crm_scope_defaults(): void
    {
        $scope = config('brands.brands.luxvant.crm_scope');

        $this->assertIsArray($scope);
        $this->assertTrue((bool) ($scope['enabled'] ?? false));
        $this->assertSame('Admin Luxvant', $scope['branch_user_name'] ?? null);
    }
}
