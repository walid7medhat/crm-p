<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CommentAttachment extends Model
{
    //
       use HasFactory;
    protected $guarded=[];
    public function comment()
    {
        return $this->belongsTo(LeadComment::class);
    }
}
