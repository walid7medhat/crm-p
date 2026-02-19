<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Deal extends Model
{
    use SoftDeletes;

    protected $table = 'deals';

    protected $fillable = [
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
        'metadata'
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
}