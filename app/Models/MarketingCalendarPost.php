<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketingCalendarPost extends Model
{
    use SoftDeletes;

    public const PLATFORMS = ['instagram_story', 'instagram', 'sales', 'tiktok', 'linkedin', 'snapchat', 'youtube'];

    protected $guarded = [];

    protected $casts = [
        'scheduled_date' => 'date:Y-m-d',
        'is_posted' => 'boolean',
        'posted_at' => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(MarketingTask::class, 'task_id');
    }

    public function link()
    {
        return $this->belongsTo(MarketingTaskLink::class, 'link_id');
    }

    public function poster()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
