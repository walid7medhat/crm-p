<?php

namespace App\Models\AiLeadIntelligence;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiLeadIntelligenceRun extends Model
{
    protected $table = 'ai_lead_intelligence_runs';

    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'status',
        'triggered_by',
        'total_eligible',
        'processed',
        'skipped_unchanged',
        'failed_leads',
        'batch_size',
        'cursor_after_id',
        'overview',
        'dashboard_payload',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'overview' => 'array',
        'dashboard_payload' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function triggeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function isInProgress(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_RUNNING], true);
    }
}
