<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feature extends Model
{
    //
    protected $guarded=[];
    public function addedBy(){
        return $this->belongsTo(User::class,'added_by');
    }
      public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_feature');
    }
}
