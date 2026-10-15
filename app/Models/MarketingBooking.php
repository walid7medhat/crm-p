<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketingBooking extends Model
{
    use SoftDeletes;

    /** Types a user can request. */
    public const SHOOT_TYPES = [
        'photo' => 'Photography',
        'video' => 'Videography',
        'photo_video' => 'Photo + Video',
    ];

    /** Labels for display, including types no longer offered (older bookings). */
    public const TYPE_LABELS = self::SHOOT_TYPES + ['drone' => 'Drone'];

    public const STATUSES = ['new', 'contacted', 'confirmed', 'cancelled'];

    protected $guarded = [];

    protected $casts = [
        'booking_date' => 'date:Y-m-d',
        'status_changed_at' => 'datetime',
        'bedrooms' => 'integer',
         'units' => 'array',   
    ];

    protected $hidden = ['ip', 'user_agent'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function task()
    {
        return $this->belongsTo(MarketingTask::class, 'task_id');
    }

    public function handler()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public static function bedroomsLabel(?int $n): ?string
    {
        if ($n === null) {
            return null;
        }

        return $n === 0 ? 'Studio' : $n.' bedroom'.($n === 1 ? '' : 's');
    }

    /** PB-2026-0001 style, sequential per year. */
    public static function nextReference(): string
    {
        $year = now()->year;
        $last = static::withTrashed()->where('reference', 'like', "PB-{$year}-%")->max('reference');
        $n = $last ? ((int) substr($last, -4)) + 1 : 1;

        return sprintf('PB-%d-%04d', $year, $n);
    }

    /** [{unit_number, bedrooms, bedrooms_label}] — falls back to the legacy single unit. */
    public static function unitsOf(self $b): array
    {
        $units = $b->units ?: ($b->unit_number !== null || $b->bedrooms !== null
            ? [['unit_number' => $b->unit_number, 'bedrooms' => $b->bedrooms]]
            : []);

        return collect($units)->map(fn ($u) => [
            'unit_number' => $u['unit_number'] ?? null,
            'bedrooms' => $u['bedrooms'] ?? null,
            'bedrooms_label' => self::bedroomsLabel($u['bedrooms'] ?? null),
        ])->values()->all();
    }
}
