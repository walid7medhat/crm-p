<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForeignAccessEvent extends Model
{
    protected $fillable = [
        'user_id',
        'brand_key',
        'ip',
        'country',
        'country_code',
        'city',
        'region',
        'isp',
        'browser',
        'operating_system',
        'user_agent',
        'session_key',
        'detection_reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'occurred_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isReviewed(): bool
    {
        return $this->status === 'reviewed';
    }
}
