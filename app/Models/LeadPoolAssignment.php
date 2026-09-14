<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadPoolAssignment extends Model
{
    protected $fillable = [
        'user_id',
        'lead_id',
        'batch_id',
        'assigned_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}