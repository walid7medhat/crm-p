<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketingStage extends Model
{
    use SoftDeletes;

    public const CATEGORIES = ['production', 'design', 'content'];

    protected $guarded = [];

    protected $casts = [
        'is_done' => 'boolean',
        'is_archive' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function tasks()
    {
        return $this->hasMany(MarketingTask::class, 'stage_id');
    }

    /** First non-archive stage on a board (where new cards land). */
    public static function firstOpen(string $category): ?self
    {
        return static::query()
            ->where('category', $category)
            ->where('is_archive', false)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }

    /** Soft-delete destination for that board. */
    public static function archiveFor(string $category): ?self
    {
        return static::query()
            ->where('category', $category)
            ->where('is_archive', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }
}
