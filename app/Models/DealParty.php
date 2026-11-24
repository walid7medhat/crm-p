<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DealParty extends Model
{
    protected $table = 'deal_parties';

    protected $fillable = [
        'deal_id',
        // Seller captured on "Sold Out": listing_id set, deal_id NULL until a deal links it
        // (App\Support\Listing\ListingSellerParty).
        'listing_id',
        'deal_property_id',
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

    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }

    public function dealProperty()
    {
        return $this->belongsTo(DealProperty::class);
    }

    /** Seller that came from a sold-out listing (shared with the listing, not typed on the deal). */
    public function isListingSeller(): bool
    {
        return $this->listing_id !== null && $this->party_type === 'seller';
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