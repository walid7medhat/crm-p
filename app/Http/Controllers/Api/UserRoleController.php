<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Http\Requests\Role\AssignRoleRequest;
use App\Http\Resources\User\UserResource;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Http\JsonResponse;

class UserRoleController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:assign-role', ['only' => ['assignRole', 'removeRole']]);
    }

    /**
     * Assign role to user
     */
    public function assignRole(AssignRoleRequest $request, User $user): JsonResponse
    {
        try {
            $user->assignRole($request->role);

            return ApiResponse::success(
                new UserResource($user->load('roles', 'permissions')),
                'Role assigned successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to assign role: ' . $e->getMessage());
        }
    }

    /**
     * Remove role from user
     */
  public function removeRole(User $user, string $roleName): JsonResponse
{
    try {
        $role = Role::where('name', $roleName)->first();
        
        if (!$role) {
            return ApiResponse::error('Role not found', 404);
        }

        $user->removeRole($role);

        return ApiResponse::success(
            new UserResource($user->load('roles', 'permissions')),
            'Role removed successfully'
        );
    } catch (\Exception $e) {
        return ApiResponse::error('Failed to remove role: ' . $e->getMessage());
    }
}

    /**
     * Sync roles for user
     */
    public function syncRoles(AssignRoleRequest $request, User $user): JsonResponse
    {
        try {
            $user->syncRoles($request->roles);

            return ApiResponse::success(
                new UserResource($user->load('roles', 'permissions')),
                'Roles synced successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to sync roles: ' . $e->getMessage());
        }
    }

    /**
     * Get user roles and permissions
     */
    public function getUserRoles(User $user): JsonResponse
    {
        try {
            $user->load('roles', 'permissions');
            
            return ApiResponse::success(
                new UserResource($user),
                'User roles retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve user roles: ' . $e->getMessage());
        }
    }
    public function permissions(User $user)
{
    try {
        $permissions = $user->getAllPermissions();
        
        return ApiResponse::success(
            PermissionResource::collection($permissions),
            'User permissions retrieved successfully'
        );
    } catch (\Exception $e) {
        return ApiResponse::error('Failed to retrieve user permissions: ' . $e->getMessage());
    }
}
}