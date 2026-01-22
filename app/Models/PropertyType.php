<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PropertyType extends Model
{
    //
        use HasFactory;
    protected $guarded=[];
   public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }
         
    public function parent(){
        return $this->belongsTo(PropertyType::class,'parent_id');
      }
  
      public function children(){
          return $this->hasMany(PropertyType::class,'parent_id');
      }
      
      public function listings(){
          return $this->hasMany(Listing::class,'property_type_id');
      }
}
