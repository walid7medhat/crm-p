<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadIntelligenceSource extends Model
{
    protected $guarded = [];

    protected $casts = [
        'retrieved_at' => 'datetime',
    ];

    public function scan(): BelongsTo
    {
        return $this->belongsTo(LeadIntelligenceScan::class, 'scan_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(LeadIntelligenceCandidate::class, 'candidate_id');
    }
}
