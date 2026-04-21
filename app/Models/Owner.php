<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
class Owner extends Model
{
     use HasFactory;
   use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        // use $listing->activities
        return LogOptions::defaults()
            ->logOnlyDirty() 
            ->logAll()       
            ->useLogName('owner');
    }
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

    public function additionalDocuments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OwnerAdditionalDocument::class)->orderBy('order');
    }
}
