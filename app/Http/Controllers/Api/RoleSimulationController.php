<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Roles\PermissionCatalog;
use App\Services\Roles\RoleAccessSimulator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;

class RoleSimulationController extends Controller
{
    public function __construct(
        private PermissionCatalog $catalog,
        private RoleAccessSimulator $simulator,
    ) {
        $this->middleware('role_or_permission:super_admin|roles-list|roles-create|roles-edit');
    }

    public function catalog(): JsonResponse
    {
        $names = Permission::query()->where('guard_name', 'api')->orderBy('name')->pluck('name')->all();

        return ApiResponse::success(
            $this->catalog->present($names),
            'Permission catalog retrieved successfully'
        );
    }

    public function searchUsers(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2) {
            return ApiResponse::success([], 'Enter at least 2 characters');
        }

        $like = '%'.addcslashes($term, '%_\\').'%';
        $editor = Auth::user();
        $visibleIds = $this->visibleUserIds($editor);

        $users = User::query()
            ->with(['roles:id,name'])
            ->when($visibleIds !== null, fn ($query) => $query->whereIn('id', $visibleIds))
            ->where(function ($query) use ($like) {
                $query->where('name', 'like', $like)
                    ->orWhere('display_name', 'like', $like)
                    ->orWhere('email', 'like', $like);
            })
            ->orderBy('name')
            ->limit(15)
            ->get()
            ->map(function (User $user) {
                return [
                    'id' => $user->id,
                    'name' => User::resolveDisplayName($user),
                    'email' => $user->email,
                    'roles' => $user->roles->pluck('name')->values(),
                    'branch_label' => $user->admin_parent?->name,
                    'is_listing_team' => (bool) $user->is_listing_team,
                ];
            })
            ->values();

        return ApiResponse::success($users, 'Employees retrieved successfully');
    }

    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'permissions' => 'present|array|max:200',
            'permissions.*' => 'string|max:255',
            'user_id' => 'required|integer|min:1',
        ]);

        $known = Permission::query()
            ->where('guard_name', 'api')
            ->whereIn('name', $validated['permissions'])
            ->pluck('name')
            ->all();

        $unknown = array_values(array_diff($validated['permissions'], $known));
        if ($unknown !== []) {
            return ApiResponse::error(
                'Unknown permissions cannot be simulated: '.implode(', ', $unknown),
                422
            );
        }

        $editor = Auth::user();
        $visibleIds = $this->visibleUserIds($editor);
        $subject = User::query()->with(['roles:id,name', 'permissions:id,name'])->find($validated['user_id']);

        if (! $subject || ($visibleIds !== null && ! in_array((int) $subject->id, array_map('intval', $visibleIds), true))) {
            return ApiResponse::error('You cannot simulate access for this employee', 403);
        }

        $result = $this->simulator->simulate(
            [
                'user_id' => (int) $subject->id,
                'name' => User::resolveDisplayName($subject),
                'current_roles' => $subject->roles->pluck('name')->all(),
                'direct_permissions' => $subject->permissions->pluck('name')->all(),
                'is_listing_team' => (bool) $subject->is_listing_team,
                'branch_label' => $subject->admin_parent?->name,
                'parent_id' => $subject->parent_id ? (int) $subject->parent_id : null,
            ],
            (string) ($validated['name'] ?? ''),
            $known,
        );

        $result['persisted'] = false;

        return ApiResponse::success($result, 'Role preview calculated. No records were changed.');
    }

    /**
     * Same population as UserController::index, not the wider profile check.
     * Null means the editor may search every user (super_admin, user 30, or users-code).
     *
     * @return array<int, int>|null
     */
    private function visibleUserIds(?User $editor): ?array
    {
        if (! $editor) {
            return [];
        }

        if ($editor->hasRole('super_admin') || (int) $editor->id === 30 || $editor->can('users-code')) {
            return null;
        }

        if ((int) $editor->id === 33 || $editor->hasRole('branch_admin') || $editor->hasRole('admin')) {
            return array_map('intval', $editor->getBranchUserIds());
        }

        return array_map('intval', $editor->getAllSubordinatesIds());
    }
}
