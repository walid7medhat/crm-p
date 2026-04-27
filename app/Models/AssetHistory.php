<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetHistory extends Model
{
    protected $fillable = [
        'asset_id', 'user_id', 'action', 'details', 'performed_by'
    ];
    
    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
    
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}