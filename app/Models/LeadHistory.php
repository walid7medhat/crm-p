<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeadHistory extends Model
{
    use SoftDeletes;

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
    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }
}
