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

            // Leads / deals belong to the company of their responsible person
            // (e.g. a Property Finder lead created by the OIA webhook but assigned
            // to a Luxvant agent is a Luxvant lead).
            static::applyResponsibleBrand($model, false);
        });

        static::updating(function ($model) {
            if (! BrandIsolation::columnReady($model->getTable())) {
                return;
            }

            // Prevent brand reassignment via mass assignment / API tampering.
            if ($model->isDirty('brand_key')) {
                $model->brand_key = $model->getOriginal('brand_key');
            }

            // Reassigned lead / deal moves to the new responsible person's company.
            if ($model->isDirty('responsible_person_id')) {
                static::applyResponsibleBrand($model, true);
            }
        });
    }

    /**
     * Leads / deals: brand follows the responsible person.
     * Luxvant member → luxvant. On reassignment away from Luxvant → oia.
     */
    protected static function applyResponsibleBrand($model, bool $reassigned): void
    {
        if (! in_array($model->getTable(), ['leads', 'deals'], true)) {
            return;
        }

        $responsible = (int) ($model->responsible_person_id ?? 0);
        if ($responsible <= 0) {
            return;
        }

        if (\App\Support\Brand\BrandCrmScope::isLuxvantMember($responsible)) {
            $model->brand_key = 'luxvant';
        } elseif ($reassigned && $model->brand_key === 'luxvant') {
            $model->brand_key = 'oia';
        }
    }

    /**
     * Query without company scope (classification tooling / explicit admin only).
     */
    public function scopeWithoutBrandScope($query)
    {
        return $query->withoutGlobalScope(BrandScope::class);
    }
}
