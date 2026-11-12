<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveWatchEvent extends Model
{
    protected $table = 'live_watch_events';

    protected $fillable = [
        'user_id',
        'event_kind',
        'event_type',
        'module',
        'action',
        'entity_type',
        'entity_id',
        'metadata',
        'session_id',
        'client_event_id',
        'occurred_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'occurred_at' => 'datetime',
        'entity_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
