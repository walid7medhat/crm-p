<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LeadComment extends Model
{
    //
       use HasFactory;
    protected $guarded=[];
     public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

   
    public function attachments()
    {
        return $this->hasMany(CommentAttachment::class);
    }

    public function mentions()
    {
        return $this->hasMany(CommentMention::class);
    }

    public function mentionedUsers()
    {
        return $this->belongsToMany(User::class, 'comment_mentions', 'comment_id', 'mentioned_user_id');
    }

    /**
     * add attachments
     */
    public function addAttachments($files)
    {
        foreach ($files as $file) {
            $this->attachments()->create([
                'file_name' => $file['name'],
                'file_path' => $file['path'],
                'file_type' => $file['type'],
                'file_size' => $file['size']
            ]);
        }
    }

    /**
     * add mentions
     */
    public function addMentions($userIds)
    {
        foreach ($userIds as $userId) {
            $this->mentions()->create([
                'mentioned_user_id' => $userId
            ]);
        }
    }
}
