<?php

namespace App\Support\Brand\Concerns;

use App\Support\Brand\BrandContext;
use App\Support\Brand\BrandIsolation;

/**
 * Stamps brand_key on create without a global read scope.
 * Used for User so authentication and shared identity lookups keep working;
 * membership is enforced via BrandCrmScope + BrandIsolation::constrain on lists.
 */
trait BrandStampOnly
{
    public static function bootBrandStampOnly(): void
    {
        static::creating(function ($model) {
            if (! BrandIsolation::columnReady($model->getTable())) {
                return;
            }

            // Always stamp from trusted host context (ignore client brand_key).
            $model->brand_key = app(BrandContext::class)->key();
        });

        static::updating(function ($model) {
            if (! BrandIsolation::columnReady($model->getTable())) {
                return;
            }

            if ($model->isDirty('brand_key')) {
                $model->brand_key = $model->getOriginal('brand_key');
            }
        });
    }
}
