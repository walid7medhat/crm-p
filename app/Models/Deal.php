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
        'unit_no',
        'property_type_id',
        'bedrooms',
        'unit_size',
        'property_link',
        'property_reference',
        'project_id',
        'area_id',
        'developer_id',
        'responsible_person_id',
        'created_by',
        'updated_by',
        'metadata','subcommunity_id','lost_reason'
    ];

    protected $casts = [
        'metadata' => 'array',
        'deal_total_amount' => 'decimal:2',
        'deal_commission' => 'decimal:2',
        'agent_share' => 'decimal:2',
        'company_share' => 'decimal:2',
        'unit_size' => 'decimal:2'
    ];

    // Relationships
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

    public function propertyType()
    {
        return $this->belongsTo(PropertyType::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }
    public function subcommunity()
    {
        return $this->belongsTo(Area::class,'subcommunity_id');
    }

    public function developer()
    {
        return $this->belongsTo(Developer::class);
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

    // Scopes
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

    // Accessors
    public function getBedroomsFormattedAttribute()
    {
        return $this->bedrooms ? $this->bedrooms . ' BR' : 'N/A';
    }

    public function getUnitSizeFormattedAttribute()
    {
        return $this->unit_size ? $this->unit_size . ' sqft' : 'N/A';
    }

    public function getAmountFormattedAttribute()
    {
        return $this->deal_total_amount ? number_format($this->deal_total_amount, 2) . ' ' . $this->currency : 'N/A';
    }

    // Helpers
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
    
        public function scopeVisibleFor($query, $user)
            {
                // &&  auth()->user()->id !=30 &&  auth()->user()->id ==33 for owner and suhil
                if ($user->hasAnyRole(['manager', 'team_lead', 'admin']) &&  auth()->user()->id !=30 &&  auth()->user()->id !=33) {

                    $subordinatesIds = $user->getAllSubordinatesIds();

                    $query->whereIn(
                        'responsible_person_id',
                        array_merge($subordinatesIds, [$user->id])
                    );

                } elseif (!$user->hasRole('super_admin') &&  auth()->user()->id !=30 &&  auth()->user()->id !=33) {

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
        ->when($request->modified_by, fn($q, $v) => $q->where('modified_by', $v))
        
        // Property filters
        ->when($request->project_id, fn($q, $v) => $q->where('project_id', $v))
        ->when($request->area_id, fn($q, $v) => $q->where('area_id', $v))
        ->when($request->subcommunity_id, fn($q, $v) => $q->where('subcommunity_id', $v))
        ->when($request->developer_id, fn($q, $v) => $q->where('developer_id', $v))
        ->when($request->property_type_id, fn($q, $v) => $q->where('property_type_id', $v))
        ->when($request->bedrooms, fn($q, $v) => $q->where('bedrooms', $v))
        ->when($request->unit_no, fn($q, $v) => $q->where('unit_no', 'like', "%$v%"))
        ->when($request->unit_size, fn($q, $v) => $q->where('unit_size', $v))
        
        // Financial filters
        ->when($request->amount, fn($q, $v) => $q->where('deal_total_amount', $v))
        ->when($request->currency, fn($q, $v) => $q->where('currency', $v))
        
        // Date filters
        ->when($request->from_date, fn($q, $v) => $q->whereDate('created_at', '>=', $v))
        ->when($request->to_date, fn($q, $v) => $q->whereDate('created_at', '<=', $v))
        
        // Buyer party filters (using whereHas on parties relationship)
        ->when($request->buyer_first_name, function($q, $v) {
            $q->whereHas('parties', function($party) use ($v) {
                $party->where('party_type', 'buyer')
                      ->where('first_name', 'like', "%$v%");
            });
        })
        ->when($request->buyer_last_name, function($q, $v) {
            $q->whereHas('parties', function($party) use ($v) {
                $party->where('party_type', 'buyer')
                      ->where('last_name', 'like', "%$v%");
            });
        })
        ->when($request->buyer_phone, function($q, $v) {
            $q->whereHas('parties', function($party) use ($v) {
                $party->where('party_type', 'buyer')
                      ->where('phone', 'like', "%$v%");
            });
        })
        ->when($request->buyer_email, function($q, $v) {
            $q->whereHas('parties', function($party) use ($v) {
                $party->where('party_type', 'buyer')
                      ->where('email', 'like', "%$v%");
            });
        })
        ->when($request->buyer_dob, function($q, $v) {
            $q->whereHas('parties', function($party) use ($v) {
                $party->where('party_type', 'buyer')
                      ->where('date_of_birth', $v);
            });
        })
        ->when($request->buyer_nationality, function($q, $v) {
            $q->whereHas('parties', function($party) use ($v) {
                $party->where('party_type', 'buyer')
                      ->where('nationality', $v);
            });
        })
        ->when($request->buyer_residency_status, function($q, $v) {
            $q->whereHas('parties', function($party) use ($v) {
                $party->where('party_type', 'buyer')
                      ->where('residency_status', $v);
            });
        })
        ->when($request->buyer_country, function($q, $v) {
            $q->whereHas('parties', function($party) use ($v) {
                $party->where('party_type', 'buyer')
                      ->where('country_of_residence', $v);
            });
        })
        ->when($request->buyer_city, function($q, $v) {
            $q->whereHas('parties', function($party) use ($v) {
                $party->where('party_type', 'buyer')
                      ->where('city_of_residence', 'like', "%$v%");
            });
        })
        
        // Global search (keeps existing functionality)
        ->when($request->search, function($q, $search) {
            $q->where(function($query) use ($search) {
                $query->where('deal_number', 'like', "%$search%")
                    ->orWhere('deal_name', 'like', "%$search%")
                    ->orWhere('source', 'like', "%$search%")
                    ->orWhere('unit_no', 'like', "%$search%")
                    ->orWhere('property_reference', 'like', "%$search%")
                    ->orWhere('property_link', 'like', "%$search%")
                    ->orWhere('currency', 'like', "%$search%")
                    ->orWhere('lost_reason', 'like', "%$search%")
                    ->orWhereHas('responsiblePerson', function($user) use ($search) {
                        $user->where('name', 'like', "%$search%")
                             ->orWhere('email', 'like', "%$search%");
                    })
                    ->orWhereHas('project', function($project) use ($search) {
                        $project->where('title', 'like', "%$search%");
                    })
                    ->orWhereHas('area', function($area) use ($search) {
                        $area->where('name', 'like', "%$search%");
                    })
                    ->orWhereHas('subcommunity', function($sub) use ($search) {
                        $sub->where('name', 'like', "%$search%");
                    })
                    ->orWhereHas('parties', function($party) use ($search) {
                        $party->where('first_name', 'like', "%$search%")
                              ->orWhere('last_name', 'like', "%$search%")
                              ->orWhere('email', 'like', "%$search%")
                              ->orWhere('phone', 'like', "%$search%");
                    })
                    ->orWhereHas('lead', function($lead) use ($search) {
                        $lead->where('lead_name', 'like', "%$search%")
                             ->orWhere('email', 'like', "%$search%")
                             ->orWhere('work_phone', 'like', "%$search%");
                    });
            });
        });
}
}