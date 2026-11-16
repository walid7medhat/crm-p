<?php

namespace App\Support\Branch;

/** Models that follow the Abu Dhabi / Dubai view switch (Lead, Deal, Listing). */
trait BranchFiltered
{
    public static function bootBranchFiltered(): void
    {
        static::addGlobalScope('branchView', new BranchViewScope());
    }
}
