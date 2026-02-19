<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DealParty extends Model
{
    protected $table = 'deal_parties';

    protected $fillable = [
        'deal_id',
        'party_type',
        'party_role',
        'first_name',
        'last_name',
        'date_of_birth',
        'phone',
        'email',
        'nationality',
        'residency_status',
        'city',
        'country',
        'language',
        'amount'
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'amount' => 'decimal:2'
    ];

    // Relationships
    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }

    public function documents()
    {
        return $this->hasMany(DealDocument::class, 'deal_party_id');
    }

    // Accessors
    public function getFullNameAttribute()
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getAmountFormattedAttribute()
    {
        return $this->amount ? number_format($this->amount, 2) . ' AED' : 'N/A';
    }

    // Scopes
    public function scopeBuyers($query)
    {
        return $query->where('party_type', 'buyer');
    }

    public function scopeSellers($query)
    {
        return $query->where('party_type', 'seller');
    }

    public function scopeTenants($query)
    {
        return $query->where('party_type', 'tenant');
    }

    public function scopeLandlords($query)
    {
        return $query->where('party_type', 'landlord');
    }

    public function scopePrimary($query)
    {
        return $query->where('party_role', 'primary');
    }

    public function scopeSecondary($query)
    {
        return $query->where('party_role', 'secondary');
    }
}