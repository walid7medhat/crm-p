<?php

namespace Tests\Unit\Brand;

use App\Support\Brand\BrandResolver;
use PHPUnit\Framework\TestCase;

class BrandResolverTest extends TestCase
{
    private BrandResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new BrandResolver();

        // Lightweight config stub without booting the full Laravel app.
        $brands = require dirname(__DIR__, 3) . '/config/brands.php';
        $GLOBALS['__brand_test_config'] = $brands;

        if (!function_exists('App\\Support\\Brand\\config')) {
            // BrandResolver uses config() helper — skip if unavailable outside Laravel.
        }
    }

    public function test_config_contains_oia_and_luxvant(): void
    {
        $config = require dirname(__DIR__, 3) . '/config/brands.php';

        $this->assertArrayHasKey('oia', $config['brands']);
        $this->assertArrayHasKey('luxvant', $config['brands']);
        $this->assertSame('#733E87', $config['brands']['oia']['colors']['primary']);
        $this->assertSame('#003c38', $config['brands']['luxvant']['colors']['primary']);
        $this->assertContains('luxvant.altcrm.com', $config['brands']['luxvant']['hosts']);
        $this->assertContains('super_admin', $config['brands']['luxvant']['access']['roles']);
        $this->assertSame('open', $config['brands']['oia']['access']['mode']);
        $this->assertSame('allowlist', $config['brands']['luxvant']['access']['mode']);
    }

    public function test_luxvant_logo_assets_exist(): void
    {
        $base = dirname(__DIR__, 3) . '/public/assets/images/brands/luxvant';

        $this->assertFileExists($base . '/logo-green.png');
        $this->assertFileExists($base . '/logo-white.png');
        $this->assertFileExists($base . '/favicon.svg');
        $this->assertFileExists($base . '/logo-mark.svg');
    }
}
