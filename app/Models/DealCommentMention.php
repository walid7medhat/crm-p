<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DealCommentMention extends Model
{
    //
       use HasFactory;
    protected $guarded=[];
     public function comment()
    {
        return $this->belongsTo(DealComment::class);
    }

    public function mentionedUser()
    {
        return $this->belongsTo(User::class, 'mentioned_user_id');
    }
}
