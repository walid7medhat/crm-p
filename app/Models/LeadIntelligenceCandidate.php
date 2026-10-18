<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadIntelligenceCandidate extends Model
{
    protected $guarded = [];

    protected $casts = [
        'reasons' => 'array',
        'uncertainties' => 'array',
    ];

    public function scan(): BelongsTo
    {
        return $this->belongsTo(LeadIntelligenceScan::class, 'scan_id');
    }
}
