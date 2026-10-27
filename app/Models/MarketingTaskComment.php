<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketingTaskComment extends Model
{
    use SoftDeletes;

    protected $guarded = [];
    protected $casts = ['mentions' => 'array'];
    public function task()
    {
        return $this->belongsTo(MarketingTask::class, 'task_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
