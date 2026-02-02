<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Area extends Model
{
    //
    use HasFactory;
    protected $guarded=[];
       public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }
    //   protected static function boot()
    // {
    //     parent::boot();

        // static::saving(function ($city) {
        //     // Determine type based on parent_id structure
        //     if (is_null($city->parent_id)) {
        //         $city->type = 'country';
        //     } elseif ($city->parent && is_null($city->parent->parent_id)) {
        //         $city->type = 'city';
        //     } elseif ($city->parent && $city->parent->parent && is_null($city->parent->parent->parent_id)) {
        //         $city->type = 'area';
        //     }  elseif ($city->parent && $city->parent->parent && $city->parent->parent->parent && is_null($city->parent->parent->parent->parent_id)) { 
        //         $city->type = 'community';
        //     } elseif ($city->parent && $city->parent->parent && $city->parent->parent->parent && $city->parent->parent->parent->parent && is_null($city->parent->parent->parent->parent->parent_id)) {
        //         $city->type = 'sub_community';
        //     }else{
        //         $city->type = 'cluster';
        //     }
        // });
    // }

     
    public function parent(){
        return $this->belongsTo(Area::class,'parent_id');
      }
  
      public function child(){
          return $this->hasMany(Area::class,'parent_id');
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
    // Collecting the names from the current area upwards
    $parentChain = [];
    $currentParent = $this;

    // Traverse up to the root (collect names)
    while ($currentParent ) {
            $parentChain[] = $currentParent->name;
            $currentParent = $currentParent->parent;
        
    }
        array_pop($parentChain);


    // Reverse the array and join with commas
    return implode(', ', $parentChain);
}
public function getTitleAttribute()
{
    // Collecting the names from the current area upwards
    $parentChain = [];
    $currentParent = $this;
    if( $currentParent->type != 'city' ){
        // Traverse up to the root (collect names)
        while ($currentParent && $currentParent->type !='city' && $currentParent->type!='country') {
                $parentChain[] = $currentParent->name;
                $currentParent = $currentParent->parent;
            
        }
    }else{
       $parentChain[] = $currentParent->name;  
    }
        // array_pop($parentChain);


    // Reverse the array and join with commas
    return implode(', ', $parentChain);
}
/**
 * Get area hierarchy attribute
 */

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
 public function properties_complete(){
        return $this->hasMany(Listing::Class,'area_id');
    }
}
