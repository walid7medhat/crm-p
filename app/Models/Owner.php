<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Owner extends Model
{
    //
       use HasFactory;
    protected $guarded=[];
      public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Check if owner is resident (convenience method)
     */
    public function getIsResidentAttribute(): bool
    {
        return $this->residency_status === 'resident';
    }

  public function properties()
    {
        // إذا كان اسم الجدول هو 'properties' والعمود هو 'owner_id'
        return $this->hasMany(\App\Models\Property::class, 'owner_id');
    }
    /**
     * Get the user who added this owner
     */
    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /**
     * Get the location (area)
     */
    public function location()
    {
        return $this->belongsTo(Area::class, 'location_id');
    }
}
