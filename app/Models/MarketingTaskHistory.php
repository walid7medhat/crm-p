<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Support\Brand\Concerns\BrandOwned;

class MarketingTaskHistory extends Model
{
    use BrandOwned;

    const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'changes' => 'array',
    ];

    public function task()
    {
        return $this->belongsTo(MarketingTask::class, 'task_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
