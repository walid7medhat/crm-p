<?php

namespace App\Http\Resources\Lead;

use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'lead_id' => $this->lead_id,
            'user_id' => $this->user_id,
            'user_name' => $this->user->name ?? null,
            'user_avatar' => $this->user->avatar_url ?? null,
            'comment' => $this->comment,
            'attachments' => CommentAttachmentResource::collection($this->attachments),
            'mentions' => $this->mentions->pluck('mentioned_user_id'),
            'mentioned_users' => $this->mentionedUsers->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar_url ?? null,
                ];
            }),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}