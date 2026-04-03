<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Listing extends Model
{
    use HasFactory;

    protected $guarded = [
       
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'mortgage_amount' => 'decimal:2',
        'size_sqft' => 'integer',
        'size_sqmt' => 'integer',
        'number_of_bedrooms' => 'integer',
        'number_of_bathrooms' => 'integer',
        'assigned_at'=>'datetime',
        'converted_at'=>'datetime',
        //  'rented_until' => 'date',
          'payment_plan' => 'array',
             'floor_plans_source' => 'array',
        

    ];
 public function getFloorPlansSourcesAttribute()
    {
        $sources = [
            'from_project' => 0,
            'uploaded' => 0,
            'total' => 0
        ];
        
        foreach ($this->floorPlans as $floorPlan) {
            if ($floorPlan->is_from_project) {
                $sources['from_project']++;
            } else {
                $sources['uploaded']++;
            }
            $sources['total']++;
        }
        
        return $sources;
    }
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->reference_number)) {
                $model->reference_number = static::generateReferenceNumber();
            }
        });
    }

    public static function generateReferenceNumber()
    {
        $unique = false;
        $referenceNumber = '';

        while (!$unique) {
            $firstPart = str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
            
            $secondPart = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $thirdPart = $letters[rand(0, 25)] . $letters[rand(0, 25)];
            
            $referenceNumber = $firstPart . '-' . $secondPart . $thirdPart;
            
            $unique = !static::where('reference_number', $referenceNumber)->exists();
        }

        return $referenceNumber;
    }

    // Relationships
    public function propertyType(): BelongsTo
    {
        return $this->belongsTo(PropertyType::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }
    public function old_area(): BelongsTo
    {
        return $this->belongsTo(OldArea::class);
    }
    public function project()
    {
        return $this->belongsTo(Project::class);
    }
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function developer(): BelongsTo
    {
        return $this->belongsTo(Developer::class);
    }
    
    public function unit_view(): BelongsTo
    {
        return $this->belongsTo(UnitView::class);
    }
    
    public function layout_type(): BelongsTo
    {
        return $this->belongsTo(LayoutType::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function floorPlans(): MorphMany
    {
        return $this->morphMany(FloorPlan::class, 'floor_planable')->orderBy('order');
    }
    
    public function galleryImages(): MorphMany
    {
        return $this->morphMany(GalleryImage::class, 'imageable')->ordered();
    }
    
    public function isOwner($user)
    {
        if (!$user) return false;
        
        return $this->agent_id == $user->id || auth()->user()->hasRole('super_admin');
    }
    
    /**
     * Check if user can edit the listing
     */
    public function canEdit($user)
    {
        if (!$user) return false;
        
        if ($user->hasRole('super_admin')) {
            return true;
        }
        
        if ($this->isOwner($user)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if user can delete the listing
     */
    public function canDelete($user)
    {
        if (!$user) return false;
        
        return $this->canEdit($user);
    }
    
    /**
     * Check if listing is under user's hierarchy
     */
    public function isUnderUserHierarchy($user)
    {
        $subordinateIds = $this->getSubordinateIds($user->id);
        $allIds = array_merge($subordinateIds, [$user->id]);
        
        return in_array($this->added_by, $allIds) || 
               in_array($this->agent_id, $allIds);
    }
    
    /**
     * Get all subordinate IDs for a user
     */
    private function getSubordinateIds($userId)
    {
        return User::where('parent_id', $userId)
            ->pluck('id')
            ->toArray();
    }
    
    public function comments()
    {
        return $this->hasMany(ListingComment::class);
    }
    
    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
    
    public function convertedBy()
    {
        return $this->belongsTo(User::class, 'converted_by');
    }
    
    public function canUserManageAccessRequests(User $user): bool
    {
        // Check if user owns the listing
        if (!$this->isOwnedBy($user)) {
            return false;
        }

        // Check user's permissions
        return $user->canManageAccessRequests();
    }
    public function isOwnedBy($user){
         if ($user->hasRole('manager')) {
            return $user->listing_team == 1;
        }else{
            $manager = $user->getManagerAttribute();
            if( $manager && $manager->listing_team != 1){
                return $this->isOwner($user);
            }
        }
        return false;
    }
//  public function setRentedStatusAttribute($value)
//     {
//         // if ($this->listing_status === 'Rent') {
//         //     $this->attributes['rented_status'] = $value;
//         // } else {
//             $this->attributes['rented_status'] = null;
//             $this->attributes['rented_until'] = null;
//         // }
//     }
    
//     public function setRentedUntilAttribute($value)
//     {
//         if ($this->rented_status === 'Rented' && $this->listing_status === 'Rent') {
//             $this->attributes['rented_until'] = $value;
//         } else {
//             $this->attributes['rented_until'] = null;
//         }
//     }
    
    public function isRented(): bool
    {
        return $this->rented_status === 'Rented' 
            && $this->listing_status === 'Rent'
            && $this->rented_until > now();
    }
    
    public function scopeAvailableForRent($query)
    {
        return $query->where('listing_status', 'Rent')
                    ->where(function($q) {
                        $q->where('rented_status', '!=', 'Rented') 
                          ->orWhereNull('rented_status')
                          ->orWhere('rented_until', '<', now());
                    });
    }
    
    public function scopeCurrentlyRented($query)
    {
        return $query->where('listing_status', 'Rent')
                    ->where('rented_status', 'Rented') 
                    ->where('rented_until', '>', now());
    }

    public function setPaymentPlanAttribute($value)
    {
        if (is_null($value)) {
            $this->attributes['payment_plan'] = null;
        } elseif (is_string($value)) {
            try {
                json_decode($value, true);
                $this->attributes['payment_plan'] = $value;
            } catch (\Exception $e) {
                $this->attributes['payment_plan'] = json_encode([$value]);
            }
        } elseif (is_array($value)) {
            $this->attributes['payment_plan'] = json_encode($value);
        } else {
            $this->attributes['payment_plan'] = json_encode([$value]);
        }
    }

    public function getPaymentPlansArrayAttribute()
    {
        return $this->payment_plan; 
    }

    public function hasPaymentPlan($plan)
    {
        return in_array($plan, $this->payment_plan);
    }

    public function addPaymentPlan($plan)
    {
        $currentPlans = $this->payment_plan;
        if (!in_array($plan, $currentPlans)) {
            $currentPlans[] = $plan;
            $this->payment_plan = $currentPlans;
        }
        return $this;
    }

    public function removePaymentPlan($plan)
    {
        $currentPlans = $this->payment_plan;
        $key = array_search($plan, $currentPlans);
        if ($key !== false) {
            unset($currentPlans[$key]);
            $this->payment_plan = array_values($currentPlans); 
        }
        return $this;
    }
    public function accessRequests(){
        return $this->hasMany(ListingAccessRequest::class,'listing_id');
    }
    public function additionalDocuments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ListingAdditionalDocument::class)->orderBy('order');
    }
    
    public function offers()
    {
        return $this->hasMany(PropertyOffer::class,'property_id');
    }
    
    public function latestOffer()
    {
        return $this->hasOne(PropertyOffer::class,'property_id')->latestOfMany();
    }
    
    
    
    public function hotDealRequests()
    {
        return $this->hasMany(HotDealRequest::class);
    }
    
    public function getPendingHotDealRequest()
    {
        return $this->hotDealRequests()->where('status', 'pending')->first();
    }
    
    public function hasPendingHotDealRequest()
    {
        return $this->hotDealRequests()->where('status', 'pending')->exists();
    }
    
    public function isHotDealApproved()
    {
        return $this->is_hot_deal && $this->hotDealRequests()
            ->where('status', 'approved')
            ->whereNotNull('approved_at')
            ->exists();
    }
}