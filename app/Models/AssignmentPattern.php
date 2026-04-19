<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentPattern extends Model
{
    protected $guarded = [];

    protected $casts = [
        'success_rate' => 'float',
        'avg_close_time_hours' => 'float',
        'samples' => 'integer',
        'context_source' => 'string',
        'context_budget_range' => 'string',
        'context_property_type' => 'string',
        'context_nationality' => 'string',
        'context_intent' => 'string',
    ];

    public function sales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }
}
