<?php

namespace Tests\Unit;

use App\Models\SystemCampaign;
use Carbon\Carbon;
use Tests\TestCase;

class SystemCampaignFrequencyTest extends TestCase
{
    public function test_cooldown_blocks_a_repeat_until_the_interval_has_passed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-29 12:00:00'));

        $campaign = new SystemCampaign([
            'frequency_hours' => 2,
            'is_active' => true,
        ]);

        $this->assertFalse($campaign->isWithinCooldown(null));
        $this->assertTrue($campaign->isWithinCooldown(Carbon::parse('2026-09-29 11:00:00')));
        $this->assertFalse($campaign->isWithinCooldown(Carbon::parse('2026-09-29 09:59:00')));

        Carbon::setTestNow();
    }

    public function test_frequency_label_uses_the_same_hour_structure(): void
    {
        $this->assertSame('Every 1 hour', \App\Support\SystemCampaignCatalog::frequencyLabel(1));
        $this->assertSame('Every 5 hours', \App\Support\SystemCampaignCatalog::frequencyLabel(5));
        $this->assertSame(['sales_active'], \App\Support\SystemCampaignCatalog::keys());
    }
}
