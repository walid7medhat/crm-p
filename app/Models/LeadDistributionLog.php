<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadDistributionLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'lead_id',
        'assigned_to',
        'score_at_assignment',
        'method',
        'meta',
        'created_at',
    ];

    protected $casts = [
        'score_at_assignment' => 'float',
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
