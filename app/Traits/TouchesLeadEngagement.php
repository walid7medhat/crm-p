<?php

namespace App\Traits;

use App\Models\Lead;

trait TouchesLeadEngagement
{
    protected static function bootTouchesLeadEngagement(): void
    {
        static::created(function ($model) {
            if ($model->lead_id) {
                Lead::find($model->lead_id)?->touchEngagement();
            }
        });
    }
}