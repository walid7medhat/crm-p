<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeocodingCache extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_success' => 'boolean',
        'raw_response' => 'array',
    ];
}

