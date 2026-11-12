<?php

namespace Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;

/**
 * Guards multi-host white-label API resolution (OIA + Luxvant same app).
 * Absolute VITE / APP_URL hosts must not be baked into client endpoint maps.
 */
class ApiBaseUrlContractTest extends TestCase
{
    public function test_api_base_helper_prefers_same_origin_in_browser_prod_path(): void
    {
        $src = file_get_contents(dirname(__DIR__, 3) . '/resources/js/config/apiBaseUrl.js');

        $this->assertNotFalse($src);
        $this->assertStringContainsString("return '/api'", $src);
        $this->assertStringContainsString('import.meta.env.DEV', $src);
        $this->assertStringContainsString('window.__API_BASE_URL__', $src);
        $this->assertStringNotContainsString('oiaproperties.altcrm.com', $src);
        $this->assertDoesNotMatchRegularExpression('/https?:\\/\\/[a-z0-9.-]*altcrm/i', $src);
    }

    public function test_api_endpoints_resolve_via_runtime_helper_not_baked_vite_const(): void
    {
        $src = file_get_contents(dirname(__DIR__, 3) . '/resources/js/config/api.js');

        $this->assertNotFalse($src);
        $this->assertStringContainsString("from './apiBaseUrl.js'", $src);
        $this->assertStringContainsString('getApiBaseUrl', $src);
        $this->assertStringNotContainsString(
            'const API_BASE_URL = import.meta.env.VITE_API_BASE_URL',
            $src
        );
        $this->assertStringContainsString('get PROJECTS()', $src);
        $this->assertStringContainsString('get USERS()', $src);
    }

    public function test_welcome_blade_injects_relative_api_base_not_app_url(): void
    {
        $src = file_get_contents(dirname(__DIR__, 3) . '/resources/views/welcome.blade.php');

        $this->assertNotFalse($src);
        $this->assertStringContainsString("window.__API_BASE_URL__ = '/api';", $src);
        $this->assertStringNotContainsString("window.__API_BASE_URL__ = \"{{ url('/api') }}\"", $src);
        $this->assertStringContainsString('window.__APP_ORIGIN__ = window.location.origin;', $src);
    }

    public function test_axios_plugin_reexports_shared_helper(): void
    {
        $src = file_get_contents(dirname(__DIR__, 3) . '/resources/js/plugins/axios.js');

        $this->assertNotFalse($src);
        $this->assertStringContainsString("from '../config/apiBaseUrl.js'", $src);
        $this->assertStringContainsString('export { getApiBaseUrl, getAppOrigin }', $src);
    }
}
