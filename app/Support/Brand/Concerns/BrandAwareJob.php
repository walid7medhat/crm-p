<?php

namespace App\Support\Brand\Concerns;

use App\Support\Brand\BrandContext;
use App\Support\Brand\BrandCrmScope;
use App\Support\Brand\BrandIsolation;
use App\Support\Brand\BrandResolver;

/**
 * Serialize trusted brand_key on the job and restore BrandContext in the worker.
 *
 * Call initializeBrandContext() in the job constructor so retries retain the
 * original brand. Wrap handle body with runWithBrandContext() so context is
 * cleared in finally (including on failure).
 */
trait BrandAwareJob
{
    public string $brandKey = 'oia';

    public function initializeBrandContext(): void
    {
        $this->brandKey = BrandIsolation::trustedKey();
    }

    public function restoreBrandContext(): void
    {
        BrandCrmScope::reset();
        BrandIsolation::clearColumnCache();

        // Fail closed / force policy: oia_only jobs never restore Luxvant context.
        $policy = \App\Support\Brand\BrandJobPolicy::forJob(static::class);
        if ($policy === 'oia_only') {
            $this->brandKey = 'oia';
        } elseif ($policy === 'luxvant_only') {
            $this->brandKey = 'luxvant';
        } elseif ($policy === 'unknown') {
            \App\Support\Brand\BrandJobPolicy::requireJob(static::class);
        }

        $brand = config('brands.brands.'.$this->brandKey);
        if (! is_array($brand) || ! isset($brand['key'])) {
            $brand = BrandResolver::defaultBrand();
        }

        app(BrandContext::class)->set($brand);
    }

    public function clearBrandContext(): void
    {
        BrandCrmScope::reset();
        BrandIsolation::clearColumnCache();
        app(BrandContext::class)->set(BrandResolver::defaultBrand());
    }

    /**
     * @template T
     * @param  callable():T  $callback
     * @return T
     */
    public function runWithBrandContext(callable $callback)
    {
        $this->restoreBrandContext();

        try {
            return $callback();
        } finally {
            $this->clearBrandContext();
        }
    }
}
