<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesTemporalStat extends Model
{
    protected $table = 'sales_temporal_stats';

    protected $guarded = [];

    protected $casts = [
        'assignments_count' => 'integer',
        'wins_count' => 'integer',
    ];

    public function sales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public static function recordAssignment(int $salesId, ?Carbon $at = null): void
    {
        $at ??= now();
        $weekday = (int) $at->dayOfWeek;
        $hour = (int) $at->hour;

        $row = static::query()->firstOrNew([
            'sales_id' => $salesId,
            'weekday' => $weekday,
            'hour' => $hour,
        ]);
        $row->assignments_count = (int) ($row->assignments_count ?? 0) + 1;
        $row->save();
    }

    public static function recordWin(int $salesId, ?Carbon $at = null): void
    {
        $at ??= now();
        $weekday = (int) $at->dayOfWeek;
        $hour = (int) $at->hour;

        $row = static::query()->firstOrNew([
            'sales_id' => $salesId,
            'weekday' => $weekday,
            'hour' => $hour,
        ]);
        $row->wins_count = (int) ($row->wins_count ?? 0) + 1;
        $row->save();
    }
}
