<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MotivationMessage extends Model
{
    protected $table = 'motivation_messages';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'is_enabled' => 'boolean',
        ];
    }
}
