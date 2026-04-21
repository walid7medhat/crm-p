<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
class Developer extends Model
{
     use HasFactory;
   use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        // use $listing->activities
        return LogOptions::defaults()
            ->logOnlyDirty() 
            ->logAll()       
            ->useLogName('developer');
    }
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}