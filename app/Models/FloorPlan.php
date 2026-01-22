<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FloorPlan extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function floorPlanable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getImageUrlAttribute(): string
    {
        return $this->image_path ? asset('storage/' . $this->image_path) : '';
    }
}