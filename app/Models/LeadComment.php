<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\TouchesLeadEngagement;

class LeadComment extends Model
{
    use HasFactory;
    use SoftDeletes; use TouchesLeadEngagement;
    protected $guarded = [];
        protected $dates = ['deleted_at']; 
    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** The top-level comment this one replies to (null = top-level comment). */
    public function parent()
    {
        return $this->belongsTo(LeadComment::class, 'parent_id');
    }

    /** Replies on this comment, oldest first (conversation order). */
    public function replies()
    {
        return $this->hasMany(LeadComment::class, 'parent_id')->oldest();
    }

    /**
     * May $user reply on this (top-level) comment?
     * Whoever can see the lead (and so its comments) can reply: the author, the lead's
     * responsible person, their team lead / manager / any parent, branch_admin,
     * show-branch-leads, admin, super_admin. Accountants are read-only — no replies.
     */
    public function canBeRepliedBy(?User $user): bool
    {
        if (! $user || $this->parent_id !== null) {
            return false;
        }

        if ($user->isAccountant()) {
            return false;
        }

        if ((int) $this->user_id === (int) $user->id) {
            return true;
        }

        // Same lead for every comment in the list — check visibility once per request.
        $key = 'lead_comment_can_reply_' . $user->id . '_' . $this->lead_id;
        $attributes = request()->attributes;
        if (! $attributes->has($key)) {
            $attributes->set($key, $this->lead ? $user->canViewLead($this->lead) : false);
        }

        return (bool) $attributes->get($key);
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