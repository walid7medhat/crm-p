<?php

namespace App\Http\Resources\Deal;

use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'deal_id' => $this->deal_id,
            'user_id' => $this->user_id,
            // display_name ?: short name — same as UserResource.
            'user_name' => User::resolveDisplayName($this->user),
            'user_avatar' => $this->user->avatar_url ?? null,
            'user_role_name' => $this->user?->roles?->first()?->name,
            // Employee profile designation — shown instead of the role when set.
            'user_position' => $this->user?->employeeProfile?->designation?->name,
            'user_parent_name' => User::resolveDisplayName($this->user?->parent),
            'user_branch_name' => User::resolveDisplayName($this->user?->office),
            'comment' => $this->comment,
            'attachments' => CommentAttachmentResource::collection($this->attachments),
            'mentions' => $this->mentions->pluck('mentioned_user_id'),
            'mentioned_users' => $this->mentionedUsers->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => User::resolveDisplayName($user),
                    'email' => $user->email,
                    'avatar' => $user->avatar_url ?? null,
                ];
            }),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}