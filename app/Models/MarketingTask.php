<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketingTask extends Model
{
    use SoftDeletes;

    public const TYPES = ['video', 'photo', 'reel', 'event','unit_shoot'];
    public const DELIVERABLES = ['campaign', 'post', 'story', 'linkedin', 'cover', 'sales_story'];
    public const CHANNELS = ['instagram', 'story', 'youtube', 'linkedin', 'unit_video', 'campaigns'];
    public const DEPARTMENTS = ['listing', 'leasing', 'homya', 'marketing'];

    protected $guarded = [];

    protected $casts = [
        'deliverables' => 'array',
        'channels' => 'array',
        'is_approved' => 'boolean',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
        'due_date' => 'date:Y-m-d',
        'position' => 'integer',
    ];

    public function stage()
    {
        return $this->belongsTo(MarketingStage::class, 'stage_id')->withTrashed();
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function shooter()
    {
        return $this->belongsTo(User::class, 'shooting_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'editing_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function links()
    {
        return $this->hasMany(MarketingTaskLink::class, 'task_id')->latest('id');
    }

    public function comments()
    {
        return $this->hasMany(MarketingTaskComment::class, 'task_id');
    }

    public function histories()
    {
        return $this->hasMany(MarketingTaskHistory::class, 'task_id');
    }

    public function posts()
    {
        return $this->hasMany(MarketingCalendarPost::class, 'task_id');
    }
}
