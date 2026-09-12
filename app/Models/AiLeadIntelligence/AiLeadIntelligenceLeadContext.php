<?php

namespace App\Models\AiLeadIntelligence;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiLeadIntelligenceLeadContext extends Model
{
    protected $table = 'ai_lead_intelligence_lead_contexts';

    protected $fillable = [
        'lead_id',
        'fingerprint',
        'urgency_bucket',
        'is_high_priority',
        'is_at_risk',
        'is_neglected',
        'requires_action_today',
        'matching_listings_count',
        'dashboard_card',
        'context',
        'last_run_id',
        'analyzed_at',
    ];

    protected $casts = [
        'dashboard_card' => 'array',
        'context' => 'array',
        'is_high_priority' => 'boolean',
        'is_at_risk' => 'boolean',
        'is_neglected' => 'boolean',
        'requires_action_today' => 'boolean',
        'analyzed_at' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AiLeadIntelligenceRun::class, 'last_run_id');
    }
}
