<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Background extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active'  => 'boolean',
    ];

    protected $appends = ['url'];

    /**
     * Full public URL for the stored image.
     */
    public function getUrlAttribute(): ?string
    {
        return $this->path ? asset('storage/' . $this->path) : null;
    }

    /**
     * Only backgrounds users are allowed to pick from.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * The system-wide default background (used when a user hasn't chosen one).
     * Cached per request to avoid N× identical lookups from UserResource.
     */
    public static function default(): ?self
    {
        static $cached = false;
        static $default = null;

        if ($cached === false) {
            $default = static::where('is_default', true)->first();
            $cached = true;
        }

        return $default;
    }

    public function users()
    {
        return $this->hasMany(User::class, 'background_id');
    }
}
