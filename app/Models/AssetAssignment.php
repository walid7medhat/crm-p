<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetAssignment extends Model
{
    protected $table = 'asset_assignments';
    
    protected $fillable = [
        'asset_id',
        'user_id',
        'assigned_by',
        'handover_date',
        'return_date',
        'notes',
        'status',
    ];
    
    protected $casts = [
        'handover_date' => 'date:Y-m-d',
        'return_date' => 'date:Y-m-d',
    ];
    
    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
    
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}