<?php

namespace App\Support\Branch;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/** Global scope: only rows of the picked branch while BranchView is active. */
class BranchViewScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! BranchView::active()) {
            return;
        }

        BranchView::constrain($builder, $model->getTable());
    }
}
