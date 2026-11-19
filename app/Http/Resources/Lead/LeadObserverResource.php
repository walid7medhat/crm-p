<?php

namespace App\Http\Resources\Lead;

use App\Models\User;
use App\Support\Brand\BrandIsolation;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadObserverResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'lead_id' => $this->lead_id,
            'user_id' => $this->user_id,
            'added_by' => $this->added_by,
            'user' => $this->whenLoaded('user', fn () => $this->formatObserverUser($this->user)),
            'added_by_user' => $this->whenLoaded('addedBy', fn () => $this->formatObserverUser($this->addedBy)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Fast show: identity-only payload (same shape as LeadResource::formatLeadUser).
     * Full UserResource walks admin_parent/office and is too heavy for modal open.
     *
     * @return array<string, mixed>|null|\App\Http\Resources\User\UserResource
     */
    protected function formatObserverUser($user)
    {
        if (! $user instanceof User) {
            return null;
        }

        if (! LeadResource::isFastShow()) {
            return new \App\Http\Resources\User\UserResource($user);
        }

        if (! BrandIsolation::userBelongsToCurrentBrand($user)) {
            return null;
        }

        $user->loadMissing(['roles:id,name']);
        $roleName = $user->roles->first()?->name;

        return [
            'id' => $user->id,
            'name' => User::resolveDisplayName($user),
            'display_name' => $user->display_name,
            'email' => $user->email,
            'avatar' => $user->avatar ? asset('storage/'.$user->avatar) : null,
            'status' => $user->status,
            'parent_id' => $user->parent_id,
            'parent_name' => null,
            'admin_parent_id' => null,
            'admin_parent_name' => null,
            'office_name' => null,
            'role_name' => $roleName ? ucwords(str_replace('_', ' ', $roleName)) : null,
            'branch' => null,
            'branch_name' => null,
            'position' => null,
            'bitrix24_id' => $user->bitrix24_id,
            'is_external' => false,
        ];
    }
}
