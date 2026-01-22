<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LayoutType extends Model
{
    //
            use HasFactory;
    protected $guarded=[];
   public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
