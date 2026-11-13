<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectImage extends Model
{
    // Shared with Project catalog — stamp only, no read isolation.
    use \App\Support\Brand\Concerns\BrandStampOnly;

    protected $fillable = [
        'project_id',
        'image_path',
        'is_main',
        'sort_order'
    ];

    protected $casts = [
        'is_main' => 'boolean'
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}