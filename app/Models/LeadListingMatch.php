<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadListingMatch extends Model
{
    public const STATUS_NEW = 'new';
    public const STATUS_SHORTLISTED = 'shortlisted';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_SENT = 'sent';

    public const BUCKET_NEW = 'new';
    public const BUCKET_PRICE_DROP = 'price_drop';
    public const BUCKET_REVIVE = 'revive';

    protected $guarded = [];

    protected $casts = [
        'reasons' => 'array',
        'score' => 'integer',
        'matched_price' => 'decimal:2',
        'previous_price' => 'decimal:2',
        'decided_at' => 'datetime',
        'sent_at' => 'datetime',
        'notified_at' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** Matches the agent still has to look at, plus ones they shortlisted but haven't sent. */
    public function scopeOpen($query)
    {
        return $query->whereIn('status', [self::STATUS_NEW, self::STATUS_SHORTLISTED]);
    }
}
