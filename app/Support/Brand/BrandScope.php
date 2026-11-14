<?php

namespace App\Support\Brand;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Server-enforced company isolation.
 *
 * Luxvant (strict tables, e.g. listings): only brand_key = luxvant.
 * Luxvant (CRM leads/deals while BrandCrmScope is active): brand_key = luxvant
 *   OR NULL — legacy Admin Luxvant branch rows were left unclassified by the
 *   isolation migration; BrandCrmScope then limits them to that branch tree.
 * OIA: brand_key = oia OR NULL (legacy / unclassified remain OIA-visible).
 *
 * Super Admin does not bypass this scope unless an explicit audited exception
 * is enabled via config (default: false).
 */
class BrandScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! BrandIsolation::columnReady($model->getTable())) {
            return;
        }

        if (BrandIsolation::superAdminCrossBrandEnabled()) {
            return;
        }

        $column = $model->qualifyColumn('brand_key');
        $key = app(BrandContext::class)->key();

        // Leads / deals follow their responsible person's company.
        if (in_array($model->getTable(), BrandIsolation::CRM_TABLES, true)) {
            BrandIsolation::applyCrmBrandRule($builder, $model->getTable(), $column, $key);

            return;
        }

        if ($key === 'luxvant') {
            if (BrandIsolation::luxvantAllowsUnclassifiedCrmRows($model->getTable())) {
                $builder->where(function (Builder $q) use ($column) {
                    $q->where($column, 'luxvant')->orWhereNull($column);
                });

                return;
            }

            $builder->where($column, 'luxvant');

            return;
        }

        // OIA (and any non-luxvant default): include legacy NULL rows.
        $builder->where(function (Builder $q) use ($column) {
            $q->where($column, 'oia')->orWhereNull($column);
        });
    }
}
