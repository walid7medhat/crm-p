<?php

namespace App\Models\AiSalesIntelligence;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAgentAlert extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'is_read' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}