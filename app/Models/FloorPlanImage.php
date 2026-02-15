<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FloorPlanImage extends Model
{
    protected $fillable = [
        'project_id',
        'image_path',
        'sort_order','name','area_id'
    ];

    /**
     * Get the project that owns the floor plan image.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
   public function area()
    {
        return $this->belongsTo(Area::class);
    }
    /**
     * Get the image URL.
     */
    public function getImageUrlAttribute(): string
    {
        if (!$this->image_path) {
            return asset('images/default-floor-plan.jpg');
        }

        if (str_starts_with($this->image_path, 'http')) {
            return $this->image_path;
        }

        return asset('storage/' . $this->image_path);
    }
}