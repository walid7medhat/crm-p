<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Area extends Model
{
    use HasFactory;
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty() 
            ->logAll()       
            ->useLogName('area');
    }

    protected $guarded = [];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];
    
    protected $appends = ['title', 'area_title'];

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function parent()
    {
        return $this->belongsTo(Area::class, 'parent_id');
    }

    public function child()
    {
        return $this->hasMany(Area::class, 'parent_id');
    }

    public function getChildIdsAttribute()
    {
        $ids = [$this->id];

        foreach ($this->child as $child) {
            $ids = array_merge($ids, $child->child_ids);
        }

        return $ids;
    }

    public function getAreaTitleAttribute()
    {
        $parentChain = [];
        $currentParent = $this;

        while ($currentParent) {
            $parentChain[] = $currentParent->name;
            $currentParent = $currentParent->parent;
        }
        array_pop($parentChain);

        return implode(', ', $parentChain);
    }

    public function getTitleAttribute()
    {
        $parentChain = [];
        $currentParent = $this;
        
        while ($currentParent && $currentParent->type != 'country') {
            $parentChain[] = $currentParent->name;
            $currentParent = $currentParent->parent;
        }

        if (count($parentChain) > 3) {
            array_pop($parentChain);
        }

        return implode(', ', $parentChain);
    }

    public function parentRecursive()
    {
        return $this->belongsTo(Area::class, 'parent_id')->with('parentRecursive');
    }

    public function getFullHierarchyAttribute()
    {
        $hierarchy = collect();
        $current = $this;
        
        while ($current) {
            $hierarchy->push([
                'id' => $current->id,
                'name' => $current->name,
                'type' => $current->type,
                'parent_id' => $current->parent_id
            ]);
            $current = $current->parent;
        }
        
        return $hierarchy->reverse()->values();
    }

    /**
     * Get all area names including self, children, and hierarchy
     */
    public function getAllAreaNames(): array
    {
        $names = [];
        
        // Add self names
        if ($this->name) $names[] = $this->name;
        if ($this->area_title) $names[] = $this->area_title;
        if ($this->title) $names[] = $this->title;
        
        // Add children names
        foreach ($this->child as $child) {
            if ($child->name) $names[] = $child->name;
            if ($child->area_title) $names[] = $child->area_title;
            if ($child->title) $names[] = $child->title;
            
            // Recursively get grandchildren names
            foreach ($child->child as $grandchild) {
                if ($grandchild->name) $names[] = $grandchild->name;
                if ($grandchild->area_title) $names[] = $grandchild->area_title;
                if ($grandchild->title) $names[] = $grandchild->title;
            }
        }
        
        // Add hierarchy names
        $hierarchy = $this->full_hierarchy;
        foreach ($hierarchy as $h) {
            if (isset($h['name']) && $h['name']) $names[] = $h['name'];
        }
        
        // Add parent names
        $parent = $this->parent;
        while ($parent) {
            if ($parent->name) $names[] = $parent->name;
            if ($parent->area_title) $names[] = $parent->area_title;
            if ($parent->title) $names[] = $parent->title;
            $parent = $parent->parent;
        }
        
        return array_unique(array_filter($names));
    }

    /**
     * Check if area or any of its children matches the given search terms
     */
    public function matchesAreaTerms(array $searchTerms): bool
    {
        $allNames = $this->getAllAreaNames();
        
        foreach ($searchTerms as $term) {
            $term = strtolower(trim($term));
            foreach ($allNames as $name) {
                if (strpos(strtolower($name), $term) !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Determine if this area should use ADGM Admin Fee
     * Returns true for Maryah Island, Reem Island, or any of their children
     */
    public function isAdgmArea(): bool
    {
        $adgmTerms = ['maryah island', 'reem island'];
        return $this->matchesAreaTerms($adgmTerms);
    }

    /**
     * Determine the admin fee type for this area
     * Returns 'adgm' or 'dari'
     */
    public function getAdminFeeType(): string
    {
        return $this->isAdgmArea() ? 'adgm' : 'dari';
    }
    
    /**
     * Get the admin fee amount based on area type
     */
    public function getAdminFeeAmount(float $dariFee, float $adgmFee): float
    {
        return $this->isAdgmArea() ? $adgmFee : $dariFee;
    }

    public function parentCityName(): ?string
    {
        $this->loadMissing('parent.parent.parent.parent');

        if ($this->type === 'city') {
            return $this->name;
        }

        $p = $this->parent;
        $depth = 0;
        while ($p && $depth < 12) {
            if (($p->type ?? '') === 'city') {
                return $p->name;
            }
            $p = $p->parent;
            $depth++;
        }

        return null;
    }

    public function properties_complete()
    {
        return $this->hasMany(Listing::class, 'area_id');
    }

    public function floorPlanImages()
    {
        return $this->hasMany(FloorPlanImage::class);
    }

    public function project()
    {
        return $this->hasOne(Project::class, 'area_id');
    }
}