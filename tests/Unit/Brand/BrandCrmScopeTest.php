<?php

namespace Tests\Unit\Brand;

use App\Models\Lead;
use App\Models\Stage;
use App\Support\Brand\BrandContext;
use App\Support\Brand\BrandCrmScope;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function test_constrain_leads_accepts_stage_has_many_on_oia(): void
    {
        $ctx = app(BrandContext::class);
        $ctx->set(config('brands.brands.oia'));
        BrandCrmScope::reset();

        $relation = $this->stageLeadsRelation();
        $this->assertInstanceOf(HasMany::class, $relation);

        // Kanban infinite scroll passes HasMany; must not TypeError before OIA no-op.
        BrandCrmScope::constrainLeads($relation);
        $this->assertFalse(BrandCrmScope::active());
    }

    public function test_constrain_leads_accepts_builder_and_has_many_on_luxvant(): void
    {
        $ctx = app(BrandContext::class);
        $ctx->set(config('brands.brands.luxvant'));
        BrandCrmScope::reset();
        $this->assertTrue(BrandCrmScope::active());

        BrandCrmScope::constrainLeads(Lead::query());

        $scoped = $this->stageLeadsRelation();
        BrandCrmScope::constrainLeads($scoped);
        $scopedSql = strtolower($scoped->toSql());
        $this->assertTrue(
            str_contains($scopedSql, 'responsible_person_id')
                || str_contains($scopedSql, '1 = 0')
                || str_contains($scopedSql, '0 = 1'),
            'Luxvant scope should constrain the HasMany query: '.$scopedSql
        );
    }

    /**
     * Plain HasMany like StageController::getMoreStageLeads ($stage->leads()),
     * without Stage::leads() auth side-effects that need a logged-in user.
     */
    private function stageLeadsRelation(): HasMany
    {
        $stage = new Stage;
        $stage->forceFill(['id' => 1]);
        $stage->exists = true;

        return $stage->hasMany(Lead::class);
    }
}
