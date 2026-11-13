<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Support\Brand\Concerns\BrandOwned;

class LiveWatchSetting extends Model
{
    use BrandOwned;

    protected $table = 'live_watch_settings';

    protected $fillable = [
        'monitored_user_id',
        'heartbeat_interval_seconds',
        'offline_timeout_seconds',
        'current_transition_id',
        'online_since',
        'is_online',
        'updated_by',
    ];

    protected $casts = [
        'online_since' => 'datetime',
        'is_online' => 'boolean',
        'heartbeat_interval_seconds' => 'integer',
        'offline_timeout_seconds' => 'integer',
    ];

    public function monitoredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'monitored_user_id');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function current(): self
    {
        $defaults = [
            'heartbeat_interval_seconds' => (int) config('live_watch.heartbeat_interval_seconds', 30),
            'offline_timeout_seconds' => (int) config('live_watch.offline_timeout_seconds', 90),
            'is_online' => false,
        ];

        return static::query()->firstOrCreate([], $defaults);
    }
}
