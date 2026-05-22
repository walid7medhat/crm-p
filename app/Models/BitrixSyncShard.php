<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BitrixSyncShard extends Model
{
    protected $guarded = [];

    protected $casts = [
        'shard_index'     => 'integer',
        'min_bitrix_id'   => 'integer',
        'max_bitrix_id'   => 'integer',
        'cursor'          => 'integer',
        'processed'       => 'integer',
        'new_count'       => 'integer',
        'existing_count'  => 'integer',
        'error_count'     => 'integer',
        'started_at'      => 'datetime',
        'finished_at'     => 'datetime',
    ];

    public function scopeIncomplete($query)
    {
        return $query->whereNotIn('status', ['done', 'cancelled']);
    }
}
