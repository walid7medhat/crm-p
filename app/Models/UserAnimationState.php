<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class UserAnimationState extends Model
{
    use LogsActivity;

    protected $fillable = [
        'user_id',
        'animation_id',
        'enabled',
        'version',
        'updated_by',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'version' => 'integer',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['user_id', 'animation_id', 'enabled', 'version', 'updated_by'])
            ->logOnlyDirty()
            ->useLogName('user_animation');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
