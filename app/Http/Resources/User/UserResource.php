<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->email,
            'phone'      => $this->phone ?? null,
            'avatar' => $this->avatar ?  asset('storage/'. $this->avatar) : null,
              'status' => $this->status,
             'parent_id' => $this->parent_id,
            'parent_name' => $this->parent?->name,
            'parent_role'=>ucwords(str_replace('_', ' ',$this->parent?->roles->first()?->name)),
             'parent_avatar' => $this->parent && $this->parent->avatar ?  asset('storage/'. $this->parent->avatar) : null,
            'admin_parent_name'=>ucwords(str_replace('_', ' ',$this->admin_parent?->name)),
            'admin_parent_id'=>$this->admin_parent?->id,
            'added_by' => $this->added_by,
            'added_by_name' => $this->addedBy?->name,
            'role_name' => ucwords(str_replace('_', ' ',$this->roles->first()?->name)),
           'role_id' => $this->roles->first()?->id,
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->pluck('name');
            }),
            'permissions' => $this->whenLoaded('permissions', function () {
                return $this->getAllPermissions()->pluck('name');
            }),
            'created_at' => $this->created_at?->format('Y-m-d H:i'),
                        'last_login_at' => $this->last_login_at,
              // Hierarchical data
            'team_members_count' => $this->children()->count(),
            'can_login' => $this->status === 'active',
            
            // Relationships
            'parent' => new UserResource($this->whenLoaded('parent')),
            'children' => UserResource::collection($this->whenLoaded('children')),
            'addedBy' => new UserResource($this->whenLoaded('addedBy')),
            'is_listing_team'=>($this->is_listing_team && (auth()->user()->hasRole('team_lead') || auth()->user()->hasRole('manager'))) || (auth()->user()->hasRole('super_admin') || auth()->user()->hasRole('admin'))
        ];
    }
}
