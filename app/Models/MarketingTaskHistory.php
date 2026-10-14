<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingTaskHistory extends Model
{
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
