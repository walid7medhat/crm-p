<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DealProperty extends Model
{
    use HasFactory;
    
    protected $table = 'deal_properties';
    
    protected $fillable = [
        'deal_id',
        'sort_order',
        // Basic Info
        'unit_no',
        'property_type_id',
        'bedrooms',
        'unit_size',
        // Location
        'area_id',
        'project_id',
        // Developer
        'developer_id',
        'developer_name',
        'developer_phone',
        // Financials (لكل Property)
        'budget_from',
        'budget_to',
        'purchase_price',
        'rental_price',
        'commission',
        // Documents (JSON)
        'payment_proof',
        'spa_document',
        'contract_document',
        'ejari_document',
    ];
    
       protected $casts = [
        'payment_proof' => 'array',  
        'spa_document' => 'array',   
        'budget_from' => 'decimal:2',
        'budget_to' => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'commission' => 'decimal:2',
    ];
    
    // ========== Relationships ==========
    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }
    
    public function propertyType()
    {
        return $this->belongsTo(PropertyType::class);
    }
    
    public function area()
    {
        return $this->belongsTo(Area::class);
    }
    
    public function project()
    {
        return $this->belongsTo(Project::class);
    }
    
    public function developer()
    {
        return $this->belongsTo(Developer::class);
    }
    
    // ========== Accessors ==========
    public function getDisplayNameAttribute()
    {
        $parts = [];
        if ($this->unit_no) $parts[] = "Unit {$this->unit_no}";
        if ($this->propertyType) $parts[] = $this->propertyType->name;
        if ($this->bedrooms) {
            $parts[] = $this->bedrooms == 'studio' ? 'Studio' : "{$this->bedrooms} Bed";
        }
        if ($this->area) $parts[] = $this->area->name;
        return implode(' - ', $parts) ?: 'New Property';
    }
    
    public function getBudgetRangeAttribute()
    {
        if ($this->budget_from && $this->budget_to) {
            return number_format($this->budget_from) . ' - ' . number_format($this->budget_to);
        }
        if ($this->budget_from) return 'From ' . number_format($this->budget_from);
        if ($this->budget_to) return 'Up to ' . number_format($this->budget_to);
        return null;
    }
}