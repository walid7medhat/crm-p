<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScoringRule extends Model
{
    protected $fillable = [
        'factor_name',
        'weight',
        'low_value',
        'medium_value',
        'high_value',
        'direction',
    ];

    protected $casts = [
        'weight' => 'float',
        'low_value' => 'float',
        'medium_value' => 'float',
        'high_value' => 'float',
    ];
}
