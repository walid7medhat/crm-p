<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadIntelligenceScan extends Model
{
    protected $guarded = [];

    protected $casts = [
        'queries_meta' => 'array',
        'report' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'fresh_until' => 'datetime',
        'progress' => 'integer',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(LeadIntelligenceCandidate::class, 'scan_id');
    }

    public function sources(): HasMany
    {
        return $this->hasMany(LeadIntelligenceSource::class, 'scan_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['queued', 'processing'], true);
    }

    public function toPanel(): array
    {
        $report = $this->report ?? [];

        return [
            'id' => $this->id,
            'lead_id' => $this->lead_id,
            'status' => $this->status,
            'progress' => (int) $this->progress,
            'message' => $this->status_message,
            'provider' => $this->provider,
            'polling' => $this->isActive(),
            'reused' => false,
            'identity_status' => $report['identity_status'] ?? null,
            'identity_message' => $report['identity_message'] ?? $this->status_message,
            'manual_search_url' => $report['manual_search_url'] ?? null,
            'limitation' => $report['limitation'] ?? null,
            'comparison' => $report['comparison'] ?? [],
            'sections' => $report['sections'] ?? [],
            'ai' => $report['ai'] ?? ['status' => 'not_configured', 'summary' => null],
            'candidates' => $this->relationLoaded('candidates')
                ? $this->candidates->map(fn (LeadIntelligenceCandidate $candidate) => [
                    'id' => $candidate->id,
                    'title' => $candidate->title,
                    'url' => $candidate->url,
                    'tier' => $candidate->tier,
                    'reasons' => $candidate->reasons ?? [],
                    'uncertainties' => $candidate->uncertainties ?? [],
                    'excerpt' => $candidate->excerpt,
                ])->values()
                : [],
            'started_at' => optional($this->started_at)->toIso8601String(),
            'finished_at' => optional($this->finished_at)->toIso8601String(),
            'fresh_until' => optional($this->fresh_until)->toIso8601String(),
        ];
    }
}
