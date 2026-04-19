<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentScore extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'score',
        'tier',
        'breakdown',
        'calculated_at',
    ];

    protected $casts = [
        'score' => 'float',
        'breakdown' => 'array',
        'calculated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
