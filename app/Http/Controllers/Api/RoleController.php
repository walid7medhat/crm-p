<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Http\Requests\Role\RoleRequest;
use App\Http\Resources\Role\RoleResource;
use App\Http\Resources\Role\RoleCollection;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\Role\PermissionCollection;
use App\Http\Resources\Role\PermissionResource;
use Illuminate\Support\Facades\Auth;

class RoleController extends Controller
{
    // تعريف الـ protected roles
    protected $protectedRoles = [1, 2, 3, 4, 5];
    // protected $protectedRoles = [];

    public function __construct()
    {
        $this->middleware('permission:roles-list', ['only' => [ 'show']]);
        $this->middleware('permission:roles-create', ['only' => ['store']]);
        $this->middleware('permission:roles-edit', ['only' => ['update']]);
        $this->middleware('permission:roles-delete', ['only' => ['destroy']]);
    }

    /**
     * Get all roles
     */
    public function index(): JsonResponse
    {
        try {
            $roles = Role::with('permissions')->get();
            
            return ApiResponse::success(
                 RoleResource::collection($roles),
                'Roles retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve roles: ' . $e->getMessage());
        }
    }
     
    public function permissions(): JsonResponse
    {
        try {
            $permissions = Permission::whereNotIn('id',[])->orderBy('name')->get();
            
            return ApiResponse::success(
                   PermissionResource::collection($permissions), 
                'Permissions retrieved successfully' 
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve permissions: ' . $e->getMessage()); 
        }
    }

    /**
     * Create a new role
     */
    public function store(RoleRequest $request): JsonResponse
    {
        try {
            $role = Role::create([
                'name' => $request->name,
                'guard_name' => 'api'
            ]);

            if ($request->has('permissions')) {
                $role->syncPermissions($request->permissions);
            }

            return ApiResponse::success(
                new RoleResource($role->load('permissions')),
                'Role created successfully', 
                201
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create role: ' . $e->getMessage());
        }
    }

    /**
     * Get a single role
     */
    public function show(Role $role): JsonResponse
    {
        try {
            $role->load('permissions');
            
            return ApiResponse::success(
                new RoleResource($role),
                'Role retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve role: ' . $e->getMessage());
        }
    }

    /**
     * Update a role
     */
    public function update(RoleRequest $request, Role $role): JsonResponse
    {
        try {
            // التحقق إذا كان الـ role محمي والمستخدم ليس Super Admin
            if (in_array($role->id, $this->protectedRoles) && !$this->isSuperAdmin()) {
                return ApiResponse::error('This role cannot be updated because it is a primary role.');
            }
            
            $role->update([
                'name' => $request->name
            ]);

            if ($request->has('permissions')) {
                $role->syncPermissions($request->permissions);
            }

            return ApiResponse::success(
                new RoleResource($role->load('permissions')),
                'Role updated successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update role: ' . $e->getMessage());
        }
    }

    /**
     * Delete a role
     */
    public function destroy(Role $role): JsonResponse
    {
        try {
            // التحقق إذا كان الـ role محمي والمستخدم ليس Super Admin
            if (in_array($role->id, $this->protectedRoles) && !$this->isSuperAdmin()) {
                return ApiResponse::error('This role cannot be deleted because it is a primary role.');
            }
            
            $role->delete();

            return ApiResponse::success(null, 'Role deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete role: ' . $e->getMessage());
        }
    }

    /**
     * Check if current user is Super Admin
     */
    private function isSuperAdmin(): bool
    {
        $user = Auth::user();
        return $user && $user->hasRole('super_admin');
    }

    /**
     * Check if role is protected
     */
    public function isProtected($roleId): bool
    {
        return in_array($roleId, $this->protectedRoles);
    }
}