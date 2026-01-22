<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;
class GalleryImage extends Model
{
    //
    protected $guarded =[];
    
    protected $casts = [
        'order' => 'integer'
    ];

    /**
     * Get the parent imageable model
     */
    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Accessor for image URL
     */
    public function getImageUrlAttribute(): string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : '';
    }

    /**
     * Scope ordered images
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order')->orderBy('created_at');
    }
}
