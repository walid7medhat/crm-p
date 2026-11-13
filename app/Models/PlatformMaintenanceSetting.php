<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Platform maintenance flag. Intentionally NOT BrandOwned —
 * one row applies across hosts; optional branch scope limits who is blocked.
 */
class PlatformMaintenanceSetting extends Model
{
    public const SCOPE_ALL = 'all';

    public const SCOPE_BRANCHES = 'branches';

    protected $fillable = [
        'is_enabled',
        'scope',
        'branch_user_ids',
        'title',
        'message',
        'estimated_return',
        'updated_by',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'branch_user_ids' => 'array',
    ];

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
