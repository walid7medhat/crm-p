<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MotivationUserState extends Model
{
    protected $table = 'motivation_user_states';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rotation_offset' => 'integer',
            'offset_shared' => 'boolean',
            'unique_slot' => 'integer',
            'cycle_reset_day_index' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
