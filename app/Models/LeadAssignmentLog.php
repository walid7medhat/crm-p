<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadAssignmentLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'score_used' => 'float',
        'attendance_score' => 'float',
        'performance_score' => 'float',
        'load_score' => 'float',
        'fairness_score' => 'float',
        'explanation' => 'array',
        'probability_of_close' => 'float',
        'was_exploration' => 'boolean',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
