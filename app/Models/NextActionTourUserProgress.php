<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NextActionTourUserProgress extends Model
{
    protected $table = 'next_action_tour_user_progress';

    protected $fillable = [
        'launch_id',
        'user_id',
        'views',
        'last_shown_at',
        'completed_at',
        'dismissed_at',
    ];

    protected $casts = [
        'views' => 'integer',
        'last_shown_at' => 'datetime',
        'completed_at' => 'datetime',
        'dismissed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
