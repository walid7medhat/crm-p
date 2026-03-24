<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HotDealRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'listing_id',
        'requested_by',
        'approved_by',
        'status',
        'comments',
        'approved_at',
        'rejected_at'
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime'
    ];

    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}