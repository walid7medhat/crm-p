<?php

namespace App\Http\Resources\Deal\Concerns;

use App\Models\User;

trait FormatsResponsiblePersonForDisplay
{
    /** @var array<int, array{admin_parent_id: int|null, admin_parent_name: string|null, office_name: string|null}> */
    protected static array $hierarchyCache = [];

    protected function resolveHierarchy(User $user): array
    {
        if (! array_key_exists($user->id, static::$hierarchyCache)) {
            $adminParent = $user->admin_parent;
            $office = $user->office;

            static::$hierarchyCache[$user->id] = [
                'admin_parent_id' => $adminParent?->id,
                'admin_parent_name' => User::resolveDisplayName($adminParent),
                'office_name' => User::resolveDisplayName($office),
            ];
        }

        return static::$hierarchyCache[$user->id];
    }

    protected function formatLeadUser($user): ?array
    {
        if (! $user instanceof User) {
            return null;
        }

        $user->loadMissing([
            'roles:id,name',
            'parent:id,name,display_name,avatar,parent_id',
            'employeeProfile.companyBranch:id,name',
            'employeeProfile.designation:id,name',
        ]);

        $roleName = $user->roles->first()?->name;
        $branchName = $user->employeeProfile?->companyBranch?->name;
        $hierarchy = $this->resolveHierarchy($user);

        return [
            'id' => $user->id,
            'name' => User::resolveDisplayName($user),
            'display_name' => $user->display_name,
            'email' => $user->email,
            'avatar' => $user->avatar ? asset('storage/'.$user->avatar) : null,
            'status' => $user->status,
            'parent_id' => $user->parent_id,
            'parent_name' => User::resolveDisplayName($user->parent),
            'admin_parent_id' => $hierarchy['admin_parent_id'],
            'admin_parent_name' => $hierarchy['admin_parent_name'],
            'office_name' => $hierarchy['office_name'],
            'role_name' => $roleName ? ucwords(str_replace('_', ' ', $roleName)) : null,
            'branch' => $branchName,
            'branch_name' => $branchName,
            'position' => $user->employeeProfile?->designation?->name,
            'bitrix24_id' => $user->bitrix24_id,
            'is_external' => false,
        ];
    }
}