<?php

namespace Tests\Unit\Brand;

use App\Models\User;
use App\Support\Brand\BrandContext;
use App\Support\Brand\BrandUrl;
use Illuminate\Auth\Notifications\ResetPassword;
use Tests\TestCase;

/** Email links go to the recipient's company site. */
class BrandUrlTest extends TestCase
{
    protected function tearDown(): void
    {
        app(BrandContext::class)->set(config('brands.brands.oia'));
        parent::tearDown();
    }

    private function user(int $id, string $brand, bool $superAdmin = false): User
    {
        $u = new class extends User {
            public bool $fakeSuperAdmin = false;

            public function hasRole($roles, ?string $guard = null): bool
            {
                return $this->fakeSuperAdmin && in_array('super_admin', (array) $roles, true);
            }
        };
        $u->forceFill(['id' => $id, 'brand_key' => $brand, 'email' => "u{$id}@example.com"]);
        $u->fakeSuperAdmin = $superAdmin;

        return $u;
    }

    public function test_base_per_brand(): void
    {
        config(['brands.brands.luxvant.app_url' => 'https://luxvant.altcrm.com', 'app.frontend_url' => 'https://oiaproperties.altcrm.com']);

        $this->assertSame('https://luxvant.altcrm.com', BrandUrl::base('luxvant'));
        $this->assertSame('https://oiaproperties.altcrm.com', BrandUrl::base('oia'));
    }

    public function test_links_follow_the_recipient_even_from_the_oia_context(): void
    {
        config(['brands.brands.luxvant.app_url' => 'https://luxvant.altcrm.com', 'app.frontend_url' => 'https://oiaproperties.altcrm.com']);
        app(BrandContext::class)->set(config('brands.brands.oia')); // e.g. a queue worker

        $this->assertSame('https://luxvant.altcrm.com/leads/5', BrandUrl::to('/leads/5', $this->user(900, 'luxvant')));
        $this->assertSame('https://oiaproperties.altcrm.com/leads/5', BrandUrl::to('/leads/5', $this->user(901, 'oia')));
    }

    public function test_super_admin_gets_the_site_in_use(): void
    {
        config(['brands.brands.luxvant.app_url' => 'https://luxvant.altcrm.com']);
        app(BrandContext::class)->set(config('brands.brands.luxvant'));

        $this->assertSame('https://luxvant.altcrm.com', BrandUrl::forUser($this->user(1, 'oia', true)));
    }

    public function test_reset_password_link_uses_luxvant_domain(): void
    {
        config(['brands.brands.luxvant.app_url' => 'https://luxvant.altcrm.com']);
        app(BrandContext::class)->set(config('brands.brands.oia'));

        $mail = (new ResetPassword('tok123'))->toMail($this->user(900, 'luxvant'));

        $this->assertStringStartsWith('https://luxvant.altcrm.com/reset-password?token=tok123', $mail->actionUrl);
    }
}
