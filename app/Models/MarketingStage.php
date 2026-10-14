<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketingStage extends Model
{
    use SoftDeletes;

    public const CATEGORIES = ['production', 'design', 'content'];

    protected $guarded = [];

    protected $casts = [
        'is_done' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function tasks()
    {
        return $this->hasMany(MarketingTask::class, 'stage_id');
    }
}
