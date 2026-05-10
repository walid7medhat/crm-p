<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Deal extends Model
{
    use SoftDeletes;

    protected $table = 'deals';

    protected $fillable = [
        'added_by',
        'lead_id',
        'deal_number',
        'deal_type',
        'stage_id',
        'source',
        'deal_name',
        'status',
        'deal_total_amount',
        'currency',
        'deal_commission',
        'agent_share',
        'company_share',
        'responsible_person_id',
        'created_by',
        'updated_by',
        'metadata',
        'lost_reason',
        'listing_id',
    ];

    protected $casts = [
        'metadata' => 'array',
        'deal_total_amount' => 'decimal:2',
        'deal_commission' => 'decimal:2',
        'agent_share' => 'decimal:2',
        'company_share' => 'decimal:2',
    ];

    // ========== Relationships ==========
    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function histories()
    {
        return LeadHistory::query()
            ->where(function ($query) {
                $query->where('deal_id', $this->id)
                      ->orWhere('lead_id', $this->lead_id);
            })
            ->latest();
    }

    public function stage()
    {
        return $this->belongsTo(Stage::class);
    }

    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }

    public function parties()
    {
        return $this->hasMany(DealParty::class);
    }

    public function buyers()
    {
        return $this->parties()->where('party_type', 'buyer');
    }

    public function sellers()
    {
        return $this->parties()->where('party_type', 'seller');
    }

    public function tenants()
    {
        return $this->parties()->where('party_type', 'tenant');
    }

    public function landlords()
    {
        return $this->parties()->where('party_type', 'landlord');
    }

    public function clients()
    {
        return $this->parties()->where('party_type', 'client');
    }

    public function documents()
    {
        return $this->hasMany(DealDocument::class);
    }

    public function responsiblePerson()
    {
        return $this->belongsTo(User::class, 'responsible_person_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function comments()
    {
        return $this->hasMany(DealComment::class)->latest();
    }

    public function activities()
    {
        return $this->hasMany(DealActivity::class)->latest();
    }

    public function pendingActivities()
    {
        return $this->hasMany(DealActivity::class)->pending();
    }

    // ========== Multi Properties ==========
    public function properties()
    {
        return $this->hasMany(DealProperty::class)->orderBy('sort_order');
    }

    public function mainProperty()
    {
        return $this->hasOne(DealProperty::class)->where('sort_order', 0);
    }

    public function getHasMultiplePropertiesAttribute()
    {
        return $this->properties()->count() > 1;
    }

    public function getTotalPurchaseAmountAttribute()
    {
        return $this->properties()->sum('purchase_price');
    }

    // ========== Accessors (from main property) ==========
    public function getBedroomsFormattedAttribute()
    {
        $mainProperty = $this->mainProperty;
        return $mainProperty?->bedrooms ? $mainProperty->bedrooms . ' BR' : 'N/A';
    }

    public function getUnitSizeFormattedAttribute()
    {
        $mainProperty = $this->mainProperty;
        return $mainProperty?->unit_size ? $mainProperty->unit_size . ' sqft' : 'N/A';
    }

    public function getUnitNoAttribute()
    {
        return $this->mainProperty?->unit_no;
    }

    public function getPropertyTypeIdAttribute()
    {
        return $this->mainProperty?->property_type_id;
    }

    public function getAreaIdAttribute()
    {
        return $this->mainProperty?->area_id;
    }

    public function getBedroomsAttribute()
    {
        return $this->mainProperty?->bedrooms;
    }

    // ========== Scopes ==========
    public function scopeOfType($query, $type)
    {
        return $query->where('deal_type', $type);
    }

    public function scopeInStage($query, $stageId)
    {
        return $query->where('stage_id', $stageId);
    }

    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeForResponsible($query, $userId)
    {
        return $query->where('responsible_person_id', $userId);
    }

    public function scopeVisibleFor($query, $user)
    {
        if ($user->hasAnyRole(['manager', 'team_lead', 'admin']) && $user->id != 30 && $user->id != 33) {
            $subordinatesIds = $user->getAllSubordinatesIds();
            $query->whereIn(
                'responsible_person_id',
                array_merge($subordinatesIds, [$user->id])
            );
        } elseif (!$user->hasRole('super_admin') && $user->id != 30 && $user->id != 33) {
            $query->where('responsible_person_id', $user->id);
        }
        return $query;
    }

    public function scopeFilter($query, $request)
    {
        return $query
            // Basic deal filters
            ->when($request->deal_type, fn($q, $v) => $q->where('deal_type', $v))
            ->when($request->stage_id, fn($q, $v) => $q->where('stage_id', $v))
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->responsible_id, fn($q, $v) => $q->where('responsible_person_id', $v))
           ->when($request->modified_by, function ($q, $v) {
                $q->orWhereHas('histories', function ($h) use ($v) {
                    $h->where('user_id', $v)
                      ->orWhere('action', 'updated')
                      ->orWhere('action', 'stage_changed');
                });
            })
            ->when($request->stage_changed_by, function ($q, $v) {
                $q->whereHas('histories', function ($h) use ($v) {
                    $h->where('action', 'stage_changed')
                      ->where('user_id', $v);
                });
            })
            ->when($request->my_deals, function ($q, $v) {
                $q->where(function ($sub) use ($v) {
                    $sub->where('responsible_person_id', auth()->user()->id)
                        ->orWhere('created_by', auth()->user()->id);
                });
            })
            
            // Property filters (through properties relationship)
            ->when($request->project_id, fn($q, $v) => $q->whereHas('properties', fn($p) => $p->where('project_id', $v)))
            ->when($request->area_id, fn($q, $v) => $q->whereHas('properties', fn($p) => $p->where('area_id', $v)))
            ->when($request->developer_id, fn($q, $v) => $q->whereHas('properties', fn($p) => $p->where('developer_id', $v)))
            ->when($request->property_type_id, fn($q, $v) => $q->whereHas('properties', fn($p) => $p->where('property_type_id', $v)))
            ->when($request->bedrooms, fn($q, $v) => $q->whereHas('properties', fn($p) => $p->where('bedrooms', $v)))
            ->when($request->unit_no, fn($q, $v) => $q->whereHas('properties', fn($p) => $p->where('unit_no', 'like', "%$v%")))
            ->when($request->unit_size, fn($q, $v) => $q->whereHas('properties', fn($p) => $p->where('unit_size', $v)))
            
            // Financial filters
            ->when($request->amount, fn($q, $v) => $q->where('deal_total_amount', $v))
            ->when($request->currency, fn($q, $v) => $q->where('currency', $v))
            
            // Date filters
            ->when($request->from_date, fn($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->to_date, fn($q, $v) => $q->whereDate('created_at', '<=', $v))
            
             ->when($request->created_from, fn($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->created_to, fn($q, $v) => $q->whereDate('created_at', '<=', $v))
            
            // Buyer party filters
            ->when($request->buyer_first_name, fn($q, $v) => $q->whereHas('parties', fn($p) => $p->where('party_type', 'buyer')->where('first_name', 'like', "%$v%")))
            ->when($request->buyer_last_name, fn($q, $v) => $q->whereHas('parties', fn($p) => $p->where('party_type', 'buyer')->where('last_name', 'like', "%$v%")))
            ->when($request->buyer_phone, fn($q, $v) => $q->whereHas('parties', fn($p) => $p->where('party_type', 'buyer')->where('phone', 'like', "%$v%")))
            ->when($request->buyer_email, fn($q, $v) => $q->whereHas('parties', fn($p) => $p->where('party_type', 'buyer')->where('email', 'like', "%$v%")))
            ->when($request->buyer_dob, fn($q, $v) => $q->whereHas('parties', fn($p) => $p->where('party_type', 'buyer')->where('date_of_birth', $v)))
            ->when($request->buyer_nationality, fn($q, $v) => $q->whereHas('parties', fn($p) => $p->where('party_type', 'buyer')->where('nationality', $v)))
            ->when($request->buyer_residency_status, fn($q, $v) => $q->whereHas('parties', fn($p) => $p->where('party_type', 'buyer')->where('residency_status', $v)))
            ->when($request->buyer_country, fn($q, $v) => $q->whereHas('parties', fn($p) => $p->where('party_type', 'buyer')->where('country', $v)))
            ->when($request->buyer_city, fn($q, $v) => $q->whereHas('parties', fn($p) => $p->where('party_type', 'buyer')->where('city', 'like', "%$v%")))
            
            // Global search
            ->when($request->search, function($q, $search) {
                $q->where(function($query) use ($search) {
                    $query->where('deal_number', 'like', "%$search%")
                        ->orWhere('deal_name', 'like', "%$search%")
                        ->orWhere('source', 'like', "%$search%")
                        ->orWhere('property_reference', 'like', "%$search%")
                        ->orWhere('property_link', 'like', "%$search%")
                        ->orWhere('currency', 'like', "%$search%")
                        ->orWhere('lost_reason', 'like', "%$search%")
                        ->orWhereHas('responsiblePerson', fn($u) => $u->where('name', 'like', "%$search%")->orWhere('email', 'like', "%$search%"))
                        ->orWhereHas('properties', fn($p) => $p->where('unit_no', 'like', "%$search%")->orWhereHas('area', fn($a) => $a->where('name', 'like', "%$search%"))
                        ->orWhereHas('propertyType', fn($pt) => $pt->where('name', 'like', "%$search%")))
                        ->orWhereHas('parties', fn($p) => $p->where('first_name', 'like', "%$search%")->orWhere('last_name', 'like', "%$search%")->orWhere('email', 'like', "%$search%")->orWhere('phone', 'like', "%$search%"))
                        ->orWhereHas('lead', fn($l) => $l->where('lead_name', 'like', "%$search%")->orWhere('email', 'like', "%$search%")->orWhere('work_phone', 'like', "%$search%"));
                });
            });
    }

    // ========== Helpers ==========
    public function isPrimary()
    {
        return $this->deal_type === 'primary';
    }

    public function isSecondary()
    {
        return $this->deal_type === 'secondary';
    }

    public function isRental()
    {
        return $this->deal_type === 'rental';
    }

    public function isDraft()
    {
        return $this->status === 'draft';
    }

    public function isApproved()
    {
        return $this->status === 'approved';
    }

    public function isCompleted()
    {
        return $this->status === 'completed';
    }
}