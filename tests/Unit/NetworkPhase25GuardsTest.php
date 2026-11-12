<?php

namespace Tests\Unit;

use Tests\TestCase;

class NetworkPhase25GuardsTest extends TestCase
{
    public function test_location_route_uses_dedicated_rate_limiter(): void
    {
        $source = file_get_contents(base_path('routes/api.php'));
        $this->assertNotFalse($source);
        $this->assertStringContainsString("throttle:location-tracking", $source);
        $this->assertStringNotContainsString(
            "Route::post('location', [AuthController::class, 'updateLocation'])->middleware('throttle:30,1')",
            $source
        );
    }

    public function test_location_tracking_rate_limiter_is_registered(): void
    {
        $source = file_get_contents(base_path('app/Providers/AppServiceProvider.php'));
        $this->assertNotFalse($source);
        $this->assertStringContainsString("RateLimiter::for('location-tracking'", $source);
    }

    public function test_shell_polling_and_reference_cache_modules_exist(): void
    {
        $this->assertFileExists(base_path('resources/js/composables/useShellPolling.js'));
        $this->assertFileExists(base_path('resources/js/services/referenceDataCache.js'));
        $this->assertFileExists(base_path('resources/js/utils/requestDiagnostics.js'));
        $main = file_get_contents(base_path('resources/js/main.js'));
        $this->assertStringContainsString("import('@iconify-json/lucide/icons.json')", $main);
        $this->assertStringContainsString('addCollection', $main);
    }
}
