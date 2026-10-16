<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\User\UserResource;
use App\Http\Resources\User\TeamHierarchyResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
class TeamController extends Controller
{
    /**
     * Get complete team hierarchy
     */
    public function getTeamHierarchy(Request $request)
    {
        try {
            $user = $request->user();
            $currentUser = $user;
        $branchUserIds = $user->hasRole('branch_admin') ? $user->getBranchUserIds() : null;

                    $users = User::query()
                        ->with(['roles:id,name'])
                        ->withCount('children')
                        ->when(! ($user->hasRole('super_admin') || $user->hasRole('admin') ), function ($qq) use ($currentUser, $branchUserIds) {
                                if ($branchUserIds) {
                                    $qq->whereIn('id', $branchUserIds) ->orWhereNull('parent_id');
                                    return;
                                }

                                $qq->where(function ($q) use ($currentUser) {
                                    $q->where('id', $currentUser->id)
                                        ->orWhere('parent_id', $currentUser->id)
                                        ->orWhereHas('parent', function ($parentQuery) use ($currentUser) {
                                            $parentQuery->where('parent_id', $currentUser->id);
                                        });
                                });
                            })
                ->get();

            $branches = $this->branchNodesFor($users);
            $childrenOf = $users->groupBy('parent_id');

            $payload = $users->map(function ($row) use ($branches, $childrenOf) {
                $branch = $branches[$row->id] ?? null;
                $kids = $childrenOf->get($row->id, collect());

                return [
                    'id' => $row->id,
                    'name' => User::resolveDisplayName($row),
                    'email' => $row->email,
                    'phone' => $row->phone,
                    'avatar' => $row->getRawOriginal('avatar') ? asset('storage/'.$row->getRawOriginal('avatar')) : null,
                    'status' => $row->status,
                    'parent_id' => $row->parent_id,
                    'parent_name' => null,
                    'role_name' => ucwords(str_replace('_', ' ', $row->roles->first()?->name)),
                    'role_id' => $row->roles->first()?->id,
                    'role_key' => $row->roles->first()?->name,
                    'branch_id' => $branch['id'] ?? null,
                    'branch_name' => $branch['name'] ?? null,
                    'team_members_count' => (int) $row->children_count,
                    'last_login_at' => $row->last_login_at,
                    'created_at' => $row->created_at,
                    'children' => $kids->map(function ($child) use ($branches) {
                        $childBranch = $branches[$child->id] ?? null;

                        return [
                            'id' => $child->id,
                            'name' => User::resolveDisplayName($child),
                            'email' => $child->email,
                            'phone' => $child->phone,
                            'parent_id' => $child->parent_id,
                            'role_name' => ucwords(str_replace('_', ' ', $child->roles->first()?->name)),
                            'role_key' => $child->roles->first()?->name,
                            'avatar' => $child->getRawOriginal('avatar') ? asset('storage/'.$child->getRawOriginal('avatar')) : null,
                            'status' => $child->status,
                            'branch_id' => $childBranch['id'] ?? null,
                            'branch_name' => $childBranch['name'] ?? null,
                            'team_members_count' => (int) $child->children_count,
                            'last_login_at' => $child->last_login_at,
                        ];
                    })->values(),
                ];
            })->values();

            $byId = $payload->keyBy('id');
            $payload = $payload->map(function ($row) use ($byId) {
                $row['parent_name'] = $row['parent_id'] ? ($byId[$row['parent_id']]['name'] ?? null) : null;

                return $row;
            })->values();

            return response()->json([
                'success' => true,
                'data' => $payload,
                'can_reassign' => $this->canManageAssignments($user),
                'message' => 'Team hierarchy retrieved successfully',
            ]);
        } catch (\Exception $e) {
            \Log::error('Team Hierarchy Error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve team hierarchy',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Move a sales employee onto a manager or team leader.
     * Branch scope and the existing hierarchy rules are enforced here.
     */
    public function reassign(Request $request)
    {
        $actor = $request->user();

        if (! $this->canManageAssignments($actor)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to reassign employees.',
            ], 403);
        }

        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'parent_id' => 'nullable|integer|exists:users,id|different:user_id',
        ]);

        try {
            $result = DB::transaction(function () use ($actor, $data) {
                $employee = User::query()->lockForUpdate()->findOrFail($data['user_id']);
                $parent = ! empty($data['parent_id'])
                    ? User::query()->lockForUpdate()->findOrFail($data['parent_id'])
                    : null;

                $employee->load('roles');
                $parent?->load('roles');
                $this->loadAncestorChain($employee);
                if ($parent) {
                    $this->loadAncestorChain($parent);
                }
                $this->loadAncestorChain($actor);

                if ($this->isChartLeader($employee)) {
                    return [422, 'Managers and team leaders stay in place. Move sales employees between teams.'];
                }

                if (! $this->canTouch($actor, $employee, false)) {
                    return [403, 'You cannot change this employee.'];
                }

                if ($parent === null) {
                    if (! $this->canTouch($actor, $employee, false)) {
                        return [403, 'You cannot change this employee.'];
                    }
                } else {
                    if (! $this->isChartDestination($parent)) {
                        return [422, 'Employees can only be assigned to a manager or a team leader.'];
                    }
                    if (! $this->canTouch($actor, $parent, true)) {
                        return [403, 'You cannot assign employees to that team.'];
                    }
                    if ($this->crossesBranch($actor, $employee, $parent)) {
                        return [403, 'You cannot assign employees across branches.'];
                    }
                    $subordinates = $employee->getAllSubordinatesIds();
                    if (in_array((int) $parent->id, array_map('intval', $subordinates), true)) {
                        return [422, 'That assignment would create a reporting loop.'];
                    }
                }

                $previousParentId = $employee->parent_id ? (int) $employee->parent_id : null;
                $nextParentId = $parent ? (int) $parent->id : null;

                if ($previousParentId === $nextParentId) {
                    return [200, 'Already on this team.', $employee, $previousParentId];
                }

                $employee->parent_id = $nextParentId;
                $employee->save();

                return [200, $parent ? 'Employee assigned.' : 'Employee removed from the team.', $employee->fresh(['roles', 'parent']), $previousParentId];
            });

            [$status, $message, $employee, $previousParentId] = array_pad($result, 4, null);

            if ($status !== 200) {
                return response()->json(['success' => false, 'message' => $message], $status);
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'id' => $employee->id,
                    'parent_id' => $employee->parent_id,
                    'parent_name' => $employee->parent ? User::resolveDisplayName($employee->parent) : null,
                    'previous_parent_id' => $previousParentId,
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Employee or team was not found.'], 404);
        } catch (\Exception $e) {
            \Log::error('Team reassign error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Could not update the assignment.',
            ], 500);
        }
    }

    /**
     * Get current user's team only
     */
    public function getMyTeam(Request $request)
    {
        try {
            $currentUser = $request->user();
            $user=auth()->user();
            // Get users that report to current user or are in the same hierarchy
                        $branchUserIds = $user->hasRole('branch_admin') ? $user->getBranchUserIds() : null;

            $users = User::with([
                'roles',
                'parent',
                'children' => function($query) {
                    $query->withCount('children');
                }
            ])

            ->when(!($user->hasRole('super_admin') || $user->hasRole('admin')), function ($qq) use ($currentUser, $branchUserIds) {
                if ($branchUserIds) {
                    $qq->whereIn('id', $branchUserIds) ->orWhereNull('parent_id');;
                    return;
                }

                $qq->where(function ($q) use ($currentUser) {
                    $q->where('id', $currentUser->id)
                        ->orWhere('parent_id', $currentUser->id)
                        ->orWhereHas('parent', function ($parentQuery) use ($currentUser) {
                            $parentQuery->where('parent_id', $currentUser->id);
                        });
                });
            })
            // ->where('status', 'active')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar ? asset('storage/'. $user->avatar) : null,
                    'status' => $user->status,
                    'parent_id' => $user->parent_id,
                    'parent_name' => $user->parent?->name,
                    'role_name' => $user->roles->first()?->name,
                    'team_members_count' => $user->children()->count(),
                    'last_login_at' => $user->last_login_at,
                    'created_at' => $user->created_at,
                    'children' => $user->children
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $users,
                'message' => 'My team retrieved successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('My Team Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve your team',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get team statistics
     */
    public function getTeamStats(Request $request)
    {
        try {
            $currentUser = $request->user();
            
            $stats = [
                'total_members' =>User::where('status', 'active')->count(),
                'online_users' =>User::where('status', 'active')
                    ->where('last_login_at', '>=', now()->subMinutes(15))
                    ->count(),
                'managers_count' =>User::whereHas('roles', function($query) {
                    $query->where('name', 'like', '%manager%');
                })->where('status', 'active')->count(),
                'active_users' =>User::where('status', 'active')->count(),
                'my_team_count' =>User::where('parent_id', $currentUser->id)
                    ->where('status', 'active')
                    ->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Team statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Team Stats Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve team statistics'
            ], 500);
        }
    }

    private function canManageAssignments(User $actor): bool
    {
        if ($actor->hasAnyRole(['super_admin', 'admin', 'manager', 'team_lead', 'branch_admin'])) {
            return true;
        }

        return $actor->checkPermissionTo('users-edit');
    }

    private function isChartLeader(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'manager', 'team_lead', 'branch_admin']);
    }

    private function isChartDestination(User $user): bool
    {
        return $user->hasAnyRole(['manager', 'team_lead']);
    }

    private function canTouch(User $actor, User $target, bool $allowSelf): bool
    {
        if ($actor->hasAnyRole(['super_admin', 'admin'])) {
            return true;
        }

        if ((int) $actor->id === (int) $target->id) {
            return $allowSelf;
        }

        if ($actor->hasRole('branch_admin')) {
            return in_array((int) $target->id, array_map('intval', $actor->getBranchUserIds()), true);
        }

        if ($actor->hasAnyRole(['manager', 'team_lead'])) {
            return User::query()->where('id', $target->id)->where(function ($q) use ($actor) {
                $q->where('id', $actor->id)
                    ->orWhere('parent_id', $actor->id)
                    ->orWhereHas('parent', function ($parentQuery) use ($actor) {
                        $parentQuery->where('parent_id', $actor->id);
                    });
            })->exists();
        }

        return false;
    }

    private function crossesBranch(User $actor, User $employee, User $parent): bool
    {
        if ($actor->hasAnyRole(['super_admin', 'admin'])) {
            return false;
        }

        $employeeBranch = $employee->getBranchNode();
        $parentBranch = $parent->getBranchNode();
        $actorBranch = $actor->getBranchNode();

        if ($employeeBranch && $parentBranch && (int) $employeeBranch->id !== (int) $parentBranch->id) {
            return true;
        }
        if ($actorBranch && $parentBranch && (int) $actorBranch->id !== (int) $parentBranch->id) {
            return true;
        }
        if ($actorBranch && $employeeBranch && (int) $actorBranch->id !== (int) $employeeBranch->id) {
            return true;
        }

        return false;
    }

    private function loadAncestorChain(User $user): void
    {
        $chain = [];
        $path = 'parent';
        for ($i = 0; $i < 8; $i++) {
            $chain[] = $path;
            $path .= '.parent';
        }
        $user->load($chain);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, User>  $users
     * @return array<int, array{id: int, name: string}|null>
     */
    private function branchNodesFor($users): array
    {
        $byId = [];
        foreach ($users as $user) {
            $byId[(int) $user->id] = $user;
        }

        $pending = [];
        foreach ($byId as $user) {
            $parentId = $user->parent_id ? (int) $user->parent_id : null;
            if ($parentId && ! isset($byId[$parentId])) {
                $pending[$parentId] = $parentId;
            }
        }

        for ($guard = 0; $pending && $guard < 8; $guard++) {
            $extra = User::query()->whereIn('id', array_values($pending))->get(['id', 'name', 'display_name', 'parent_id']);
            $pending = [];
            foreach ($extra as $row) {
                $byId[(int) $row->id] = $row;
                $parentId = $row->parent_id ? (int) $row->parent_id : null;
                if ($parentId && ! isset($byId[$parentId])) {
                    $pending[$parentId] = $parentId;
                }
            }
        }

        $cache = [];
        $resolve = function (int $id) use (&$resolve, &$cache, $byId) {
            if (array_key_exists($id, $cache)) {
                return $cache[$id];
            }
            $seen = [];
            $currentId = $id;
            while (isset($byId[$currentId])) {
                if (isset($seen[$currentId])) {
                    return $cache[$id] = null;
                }
                $seen[$currentId] = true;
                $node = $byId[$currentId];
                $parentId = $node->parent_id ? (int) $node->parent_id : null;
                if (! $parentId || ! isset($byId[$parentId])) {
                    return $cache[$id] = null;
                }
                $parent = $byId[$parentId];
                if ($parent->parent_id === null) {
                    return $cache[$id] = [
                        'id' => (int) $node->id,
                        'name' => User::resolveDisplayName($node),
                    ];
                }
                $currentId = (int) $parent->id;
            }

            return $cache[$id] = null;
        };

        $resolved = [];
        foreach ($users as $user) {
            $resolved[(int) $user->id] = $resolve((int) $user->id);
        }

        return $resolved;
    }
}