<?php

namespace App\Http\Resources\User;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    protected static function formatRoleLabel(?string $role): ?string
    {
        if ($role === null || $role === '') {
            return null;
        }

        return ucwords(str_replace('_', ' ', $role));
    }

    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => User::resolveDisplayName($this->resource),
            'display_name' => $this->display_name,
            'email'      => $this->email,
            'phone'      => $this->phone ?? null,
            'avatar' => $this->avatar ?  asset('storage/'. $this->avatar) : null,
            'background_id' => $this->background_id,
            'background_url' => $this->background_url,
              'status' => $this->status,
             'parent_id' => $this->parent_id,
            'parent_name' => User::resolveDisplayName($this->parent),
            'parent_role' => static::formatRoleLabel($this->parent?->roles->first()?->name),
             'parent_avatar' => $this->parent && $this->parent->avatar ?  asset('storage/'. $this->parent->avatar) : null,
            'admin_parent_name' => static::formatRoleLabel($this->admin_parent?->name),
            'office_name' => static::formatRoleLabel($this->office?->name),
            'admin_parent_id'=>$this->admin_parent?->id,
            'added_by' => $this->added_by,
            'added_by_name' => User::resolveDisplayName($this->addedBy),
            'role_name' => static::formatRoleLabel($this->roles->first()?->name),
           'role_id' => $this->roles->first()?->id,
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->pluck('name');
            }),
            'permissions' => $this->whenLoaded('permissions', function () {
                return $this->getAllPermissions()->pluck('name');
            }),
            'created_at' => $this->created_at?->format('Y-m-d H:i'),
                        'last_login_at' => $this->last_login_at,
            'last_login_location' => $this->last_login_location,
            'last_login_lat' => $this->last_login_lat,
            'last_login_lng' => $this->last_login_lng,
            'last_login_ip' => $this->last_login_ip,
              // Hierarchical data
            'team_members_count' => $this->children()->count(),
            'can_login' => $this->status === 'active',
            
            // Relationships
            'parent' => new UserResource($this->whenLoaded('parent')),
            'children' => UserResource::collection($this->whenLoaded('children')),
            'addedBy' => new UserResource($this->whenLoaded('addedBy')),
            'is_listing_team'=>($this->is_listing_team ) || (auth()->check() &&(auth()->user()->hasRole('super_admin') || auth()->user()->hasRole('admin'))),
            'biometric_code'=>$this->biometric_code,
            'branch' => $this->employeeProfile?->companyBranch?->name,
            'position' => $this->employeeProfile?->designation?->name,
            'department' => $this->employeeProfile?->department?->name,
        ];
    }
}
