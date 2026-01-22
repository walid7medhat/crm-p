<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamHierarchyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->avatar ? asset('storage/'. $this->avatar) : null,
            'status' => $this->status,
            'parent_id' => $this->parent_id,
            'parent_name' => $this->parent?->name,
            'role_name' => $this->roles->first()?->name,
            'role_id' => $this->roles->first()?->id,
            'team_members_count' => $this->children_count,
            'last_login_at' => $this->last_login_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'children' => TeamHierarchyResource::collection($this->whenLoaded('children'))
        ];
    }
}