<?php

namespace Tests\Unit\Brand;

use Tests\TestCase;

class OfferBrandAssetsTest extends TestCase
{
    public function test_luxvant_gm_logo_files_exist_for_offer_pdf(): void
    {
        $green = public_path('assets/images/brands/luxvant/logo-green.png');
        $white = public_path('assets/images/brands/luxvant/logo-white.png');

        $this->assertFileExists($green);
        $this->assertFileExists($white);
        $this->assertGreaterThan(1000, filesize($green));
        $this->assertGreaterThan(1000, filesize($white));
    }

    public function test_oia_offer_logo_files_unchanged(): void
    {
        $this->assertFileExists(public_path('assets/images/oiaLogo.jpg'));
        $this->assertFileExists(public_path('assets/images/pngLogo.png'));
        $this->assertFileExists(public_path('assets/images/LogoWhite.png'));
    }

    public function test_brand_config_points_at_luxvant_gm_assets(): void
    {
        $logos = config('brands.brands.luxvant.logos');
        $this->assertSame('/assets/images/brands/luxvant/logo-green.png', $logos['primary']);
        $this->assertSame('/assets/images/brands/luxvant/logo-green.png', $logos['primary_png']);
        $this->assertSame('/assets/images/brands/luxvant/logo-white.png', $logos['on_dark']);
    }
}
