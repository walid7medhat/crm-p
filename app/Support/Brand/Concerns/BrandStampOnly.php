<?php

namespace App\Support\Brand\Concerns;

use App\Support\Brand\BrandContext;
use App\Support\Brand\BrandIsolation;

/**
 * Stamps brand_key on create without a global read scope.
 *
 * Used for:
 * - User: auth / shared identity; membership via BrandCrmScope + constrain lists
 * - Shared catalog (Project + images): visible on every brand host; brand_key is
 *   attribution only so each company can still gate writes via permissions
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
