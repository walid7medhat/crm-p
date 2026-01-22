<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ListingAccessRequest extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'responded_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'viewing_date' => 'date',
        'viewing_time' => 'datetime:H:i',

    ];

    // Request types constants
    const TYPE_OWNER_DATA = 'owner_data';
    const TYPE_UNIT_NUMBER  = 'unit_number';
       const TYPE_Viewing  = 'viewing';
    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->reference_number)) {
                $model->reference_number = self::generateReferenceNumber();
            }
        });
    }

    protected static function generateReferenceNumber(): string
    {
       $numbers = rand(100, 999);    
        $letters = strtoupper(Str::random(2)); 
    
        return "REQ-{$numbers}{$letters}";
    }
    
    // Relationships
    
      /**
     * Get the user who wrote the review.
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
    
    
    public function handledBy()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }/**
     * Check if request has review.
     */
    public function hasReview(): bool
    {
        return !empty($this->review);
    }
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('requested_by', $userId);
    }

    public function scopeOwnerDataRequests($query)
    {
        return $query->where('request_type', self::TYPE_OWNER_DATA);
    }

    public function scopeOwnerNumberRequests($query)
    {
        return $query->where('request_type', self::TYPE_UNIT_NUMBER);
    }
     public function scopeViewingRequests($query)
    {
        return $query->where('request_type', self::TYPE_Viewing);
    }

    // Helper methods
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isOwnerDataRequest(): bool
    {
        return $this->request_type === self::TYPE_OWNER_DATA;
    }

    public function isOwnerNumberRequest(): bool
    {
        return $this->request_type === self::TYPE_UNIT_NUMBER;
    }
    public function isViewingRequest(): bool
    {
        return $this->request_type === self::TYPE_Viewing;
    }

    public function getRequestTypeLabel(): string
    {
        return match($this->request_type) {
            self::TYPE_OWNER_DATA => 'Owner Full Data',
            self::TYPE_UNIT_NUMBER => 'Owner Phone Number',
            self::TYPE_Viewing => 'Property Viewing',
            default => 'Unknown'
        };
    }

    public function markAsApproved(?string $response = null): void
    {
        $this->update([
            'status' => 'approved',
            'owner_response' => $response,
            'responded_at' => now()
        ]);
    }

    public function markAsRejected(?string $response = null): void
    {
        $this->update([
            'status' => 'rejected',
            'owner_response' => $response,
            'responded_at' => now()
        ]);
    }
     public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
      public function convertedBy()
    {
        return $this->belongsTo(User::class, 'converted_by');
    } 
    public function getViewingDetailsAttribute()
    {
        if ($this->request_type !== 'viewing') {
            return null;
        }
        
        return [
            'date' => $this->viewing_date,
            'time' => $this->viewing_time,
        
            'formatted_date' => $this->viewing_date?->format('d M Y'),
            'formatted_time' => $this->viewing_time?->format('h:i A'),
            'full_datetime' => $this->viewing_date && $this->viewing_time 
                ? $this->viewing_date->format('Y-m-d') . ' ' . $this->viewing_time
                : null,
        ];
    }
}