<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesPerformance extends Model
{
    protected $table = 'sales_performance';

    protected $guarded = [];

    protected $casts = [
        'score' => 'float',
        'response_time' => 'float',
        'conversion_rate' => 'float',
        'deals_total' => 'integer',
        'deals_closed' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }
}
