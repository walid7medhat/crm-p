<?php

namespace Tests\Unit\Brand;

use App\Support\Brand\BrandContext;
use App\Support\Brand\BrandIsolation;
use App\Support\Brand\BrandJobPolicy;
use App\Support\Brand\BrandScheduler;
use Tests\TestCase;

class BrandSchedulerIsolationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        BrandIsolation::clearColumnCache();
    }

    public function test_job_and_command_policy_classifications(): void
    {
        $this->assertSame('oia_only', BrandJobPolicy::forJob(\App\Jobs\ProcessBitrixLeadJob::class));
        $this->assertSame('oia_only', BrandJobPolicy::forJob(\App\Jobs\ProcessPropertyFinderEventJob::class));
        $this->assertSame('per_brand', BrandJobPolicy::forJob(\App\Jobs\ProcessLeadAutoAssignmentJob::class));
        $this->assertSame('per_brand', BrandJobPolicy::forCommand('leads:score'));
        $this->assertSame('oia_only', BrandJobPolicy::forCommand('meta:refresh-app-token'));
        $this->assertSame('global_shared', BrandJobPolicy::forCommand('live-watch:cleanup'));
        $this->assertSame('unknown', BrandJobPolicy::forCommand('totally:unknown-cmd'));
    }

    public function test_for_each_brand_runs_sequentially_and_clears_context(): void
    {
        app(BrandContext::class)->set(config('brands.brands.luxvant'));

        $seen = BrandScheduler::forEachBrand(function (string $key) {
            return BrandIsolation::trustedKey();
        });

        $this->assertSame('oia', $seen['oia'] ?? null);
        $this->assertSame('luxvant', $seen['luxvant'] ?? null);
        // Cleared back to default after loop
        $this->assertSame('oia', BrandIsolation::trustedKey());
    }

    public function test_run_once_clears_context_after_failure(): void
    {
        app(BrandContext::class)->set(config('brands.brands.luxvant'));

        // inspire always exists; force a missing command to exercise finally cleanup
        $code = BrandScheduler::runOnce('luxvant', 'this-command-does-not-exist-xyz');
        $this->assertNotSame(0, $code);
        $this->assertSame('oia', BrandIsolation::trustedKey());
    }

    public function test_oia_only_command_runs_only_oia(): void
    {
        $result = BrandScheduler::runCommand('meta:refresh-app-token');
        $this->assertSame(['oia'], $result['ran']);
        $this->assertArrayHasKey('oia', $result['exit_codes']);
        $this->assertArrayNotHasKey('luxvant', $result['exit_codes']);
    }

    public function test_unclassified_command_fails_closed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        BrandScheduler::runCommand('totally:unknown-cmd');
    }

    public function test_require_job_fails_closed_for_unknown(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        BrandJobPolicy::requireJob('App\\Jobs\\DoesNotExistJob');
    }

    public function test_bitrix_job_forces_oia_even_when_dispatched_on_luxvant(): void
    {
        app(BrandContext::class)->set(config('brands.brands.luxvant'));
        $job = new \App\Jobs\ProcessBitrixLeadJob(['ID' => 1], 1);
        $this->assertSame('oia', $job->brandKey);
        $job->restoreBrandContext();
        $this->assertSame('oia', BrandIsolation::trustedKey());
        $job->clearBrandContext();
        $this->assertSame('oia', BrandIsolation::trustedKey());
    }
}
