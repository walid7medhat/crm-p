<?php

namespace App\Models\AiSalesIntelligence;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAgentDailyStat extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'stat_date' => 'date',
            'avg_response_minutes' => 'float',
            'extra' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
