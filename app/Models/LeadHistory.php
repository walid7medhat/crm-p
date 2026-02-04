<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadHistory extends Model
{
    //
    protected $table="lead_histories";
    protected $guarded=[];
      protected $casts = [
        'changes' => 'array',
    ];
       public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }
}
