<?php

namespace Tests\Unit\Brand;

use App\Http\Resources\Deal\Concerns\FormatsResponsiblePersonForDisplay;
use App\Http\Resources\Lead\LeadResource;
use App\Models\Lead;
use App\Models\User;
use App\Support\Brand\BrandContext;
use App\Support\Brand\BrandIsolation;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeadDealUserMetadataIsolationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        BrandIsolation::clearColumnCache();
    }

    public function test_lead_resource_hides_cross_brand_responsible_person_metadata(): void
    {
        if (! Schema::hasColumn('users', 'brand_key')) {
            $this->markTestSkipped('users.brand_key not migrated');
        }

        $luxUser = new User;
        $luxUser->forceFill([
            'id' => 910001,
            'name' => 'Lux Agent',
            'email' => 'lux-agent@example.test',
            'avatar' => 'luxvant/users/avatars/x.png',
            'brand_key' => 'luxvant',
        ]);

        app(BrandContext::class)->set(config('brands.brands.oia'));

        $lead = new Lead;
        $resource = new class($lead) extends LeadResource {
            public function expose($user)
            {
                return $this->formatLeadUser($user);
            }
        };

        $this->assertNull($resource->expose($luxUser));

        app(BrandContext::class)->set(config('brands.brands.luxvant'));
        $payload = $resource->expose($luxUser);
        $this->assertNotNull($payload);
        $this->assertSame('Lux Agent', $payload['name'] ?? $payload['display_name'] ?? null);
        $this->assertArrayHasKey('email', $payload);
    }

    public function test_deal_formatter_hides_cross_brand_user(): void
    {
        if (! Schema::hasColumn('users', 'brand_key')) {
            $this->markTestSkipped('users.brand_key not migrated');
        }

        $oiaUser = new User;
        $oiaUser->forceFill([
            'id' => 910002,
            'name' => 'OIA Agent',
            'email' => 'oia-agent@example.test',
            'brand_key' => 'oia',
        ]);

        $formatter = new class {
            use FormatsResponsiblePersonForDisplay;

            public function expose($user)
            {
                return $this->formatLeadUser($user);
            }
        };

        app(BrandContext::class)->set(config('brands.brands.luxvant'));
        $this->assertNull($formatter->expose($oiaUser));
    }

    public function test_assign_helpers_do_not_resolve_cross_brand_ids(): void
    {
        if (! Schema::hasColumn('users', 'brand_key')) {
            $this->markTestSkipped('users.brand_key not migrated');
        }

        app(BrandContext::class)->set(config('brands.brands.luxvant'));
        $oiaOnly = User::query()->where(function ($q) {
            $q->whereNull('brand_key')->orWhere('brand_key', 'oia');
        })->orderBy('id')->first();

        if (! $oiaOnly) {
            $this->markTestSkipped('No OIA user row');
        }

        $this->assertNull(BrandIsolation::findUserInCurrentBrand($oiaOnly->id));
    }
}
