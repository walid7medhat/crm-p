<?php

namespace App\Support\Brand;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Server-enforced company isolation.
 *
 * Luxvant: only brand_key = luxvant (NULL/ambiguous never visible).
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

        if ($key === 'luxvant') {
            $builder->where($column, 'luxvant');

            return;
        }

        // OIA (and any non-luxvant default): include legacy NULL rows.
        $builder->where(function (Builder $q) use ($column) {
            $q->where($column, 'oia')->orWhereNull($column);
        });
    }
}
