<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LeadParticipant extends Model
{
    //
      use HasFactory;
    protected $guarded=[];
    
     public function lead()
    {
        return $this->belongsTo(Lead::class);
    }
}
