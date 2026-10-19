<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NextActionTourCampaign extends Model
{
    protected $fillable = [
        'launch_id',
        'is_active',
        'started_by',
        'started_at',
        'stopped_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'started_at' => 'datetime',
        'stopped_at' => 'datetime',
    ];

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }
}
