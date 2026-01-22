<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListingComment extends Model
{
    //
     protected $fillable = [
        'listing_id',
        'user_id',
        'comment',
        'rating',
        'parent_id',
        'is_approved'
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_approved' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(ListingComment::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(ListingComment::class, 'parent_id')->with('replies');
    }

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    public function scopeMainComments($query)
    {
        return $query->whereNull('parent_id');
    }
}
