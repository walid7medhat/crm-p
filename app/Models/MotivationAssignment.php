<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MotivationAssignment extends Model
{
    protected $table = 'motivation_assignments';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'assigned_on' => 'date',
            'message_number' => 'integer',
            'dismissed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
