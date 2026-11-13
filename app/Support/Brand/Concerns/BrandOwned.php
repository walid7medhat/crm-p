<?php

namespace App\Support\Brand\Concerns;

use App\Support\Brand\BrandContext;
use App\Support\Brand\BrandIsolation;
use App\Support\Brand\BrandScope;

/**
 * Company-owned Eloquent models. brand_key is always stamped from BrandContext
 * on create and cannot be overridden by client input.
 */
trait BrandOwned
{
    public static function bootBrandOwned(): void
    {
        static::addGlobalScope(new BrandScope);

        static::creating(function ($model) {
            if (! BrandIsolation::columnReady($model->getTable())) {
                return;
            }

            // Force trusted server brand — ignore any client-supplied value.
            $model->brand_key = app(BrandContext::class)->key();
        });

        static::updating(function ($model) {
            if (! BrandIsolation::columnReady($model->getTable())) {
                return;
            }

            // Prevent brand reassignment via mass assignment / API tampering.
            if ($model->isDirty('brand_key')) {
                $model->brand_key = $model->getOriginal('brand_key');
            }
        });
    }

    /**
     * Query without company scope (classification tooling / explicit admin only).
     */
    public function scopeWithoutBrandScope($query)
    {
        return $query->withoutGlobalScope(BrandScope::class);
    }
}
