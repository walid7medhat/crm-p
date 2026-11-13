<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Support\Brand\Concerns\BrandOwned;

class MarketingTaskLink extends Model
{
    use BrandOwned;

    protected $guarded = [];

    public function task()
    {
        return $this->belongsTo(MarketingTask::class, 'task_id');
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
