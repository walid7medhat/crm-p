<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Project extends Model
{
    protected $fillable = [
        'title',
        'developer_id',
        'from_price',
        'to_price',
        'from_sqft',
        'to_sqft',
        'status',
        'about',
        'added_by',
        'area_id'
    ];

    protected $casts = [
        'from_price' => 'decimal:2',
        'to_price' => 'decimal:2',
        'from_sqft' => 'decimal:2',
        'to_sqft' => 'decimal:2'
    ];

    public function developer()
    {
        return $this->belongsTo(Developer::class);
    }
       public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'project_features');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProjectImage::class)->orderBy('sort_order');
    }

    public function mainImage()
    {
        return $this->hasOne(ProjectImage::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}