<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LeadComment extends Model
{
    use HasFactory;
    
    protected $guarded = [];
    
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
        return $this->hasMany(CommentAttachment::class,'comment_id');
    }

    public function mentions()
    {
        return $this->hasMany(CommentMention::class,'comment_id');
    }

    public function mentionedUsers()
    {
        return $this->belongsToMany(User::class, 'comment_mentions', 'comment_id', 'mentioned_user_id');
    }

    /**
     * Add attachments to comment
     */
    public function addAttachments($files)
    {
        foreach ($files as $file) {
            // Make sure we have the required array keys
            $this->attachments()->create([
                'file_name' => $file['file_name'] ?? $file['name'] ?? 'untitled',
                'file_path' => $file['file_path'] ?? $file['path'] ?? '',
                'file_type' => $file['file_type'] ?? $file['type'] ?? 'unknown',
                'file_size' => $file['file_size'] ?? $file['size'] ?? 0,
            ]);
        }
    }

    /**
     * Add mentions to comment
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