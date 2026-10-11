<?php

namespace App\Http\Resources\Lead;

use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'lead_id' => $this->lead_id,
            'user_id' => $this->user_id,
            // Same hover fields as comments — names via resolveDisplayName like UserResource.
            'user_name' => User::resolveDisplayName($this->user),
            'user_avatar' => $this->user->avatar_url ?? null,
            'user_role_name' => $this->user?->roles?->first()?->name,
            'user_position' => $this->user?->employeeProfile?->designation?->name,
            'user_parent_name' => User::resolveDisplayName($this->user?->parent),
            'user_branch_name' => User::resolveDisplayName($this->user?->office),
            'title' => $this->title,
            'reminder_date' => $this->reminder_date->format('Y-m-d H:i:s'),
            'is_completed' => $this->is_completed,
            'status' => $this->is_completed ? 'Completed' : ($this->reminder_date < now() ? 'Overdue' : 'Pending'),
            'reminders'=>$this->reminders,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}