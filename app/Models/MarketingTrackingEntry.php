<?php

namespace App\Models;

use App\Support\Brand\Concerns\BrandOwned;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketingTrackingEntry extends Model
{
    use SoftDeletes;
    use BrandOwned;

    public const CATEGORIES = ['blogs', 'oia', 'all'];

    public const SHEET_GROUPS = ['blogs', 'projects', 'developers', 'new_domains'];

    public const REGIONS = ['abu_dhabi', 'dubai'];

    /** Users allowed to open Tracking Sheets even without admin/super_admin. */
    public const EXTRA_ALLOWED_USER_IDS = [58];

    protected $guarded = [];

    protected $casts = [
        'is_published' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function userCanAccess(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasAnyRole(['super_admin', 'admin'])) {
            return true;
        }

        return in_array((int) $user->id, self::EXTRA_ALLOWED_USER_IDS, true);
    }
}
