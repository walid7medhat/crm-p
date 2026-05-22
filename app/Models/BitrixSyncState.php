<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BitrixSyncState extends Model
{
    protected $guarded = [];

    protected $casts = [
        'cursor'                  => 'integer',
        'total'                   => 'integer',
        'processed'               => 'integer',
        'new_count'               => 'integer',
        'existing_count'          => 'integer',
        'error_count'             => 'integer',
        'skip_existing'           => 'boolean',
        'user_id'                 => 'integer',
        'started_at'              => 'datetime',
        'finished_at'             => 'datetime',
        'leads_per_sec'           => 'float',
        'eta_seconds'             => 'integer',
        'last_progress_at'        => 'datetime',
        'last_processed_snapshot' => 'integer',
        'parallel_shards'         => 'integer',
        'shards_completed'        => 'integer',
        'meta'                    => 'array',
    ];
}
