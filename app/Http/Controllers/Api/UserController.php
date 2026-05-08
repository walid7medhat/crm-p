<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Http\Requests\User\UserRequest;
use App\Http\Resources\User\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use App\Notifications\UserStatusUpdated;
use Illuminate\Support\Facades\Mail;
use App\Mail\AccountActivatedMail;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:users-create', ['only' => ['store']]);
        // $this->middleware('permission:users-edit', ['only' => ['update']]);
        $this->middleware('permission:users-delete', ['only' => ['destroy']]);
    }

    /**
     * Get all users with hierarchical structure
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Start the query
            $query = User::with([
                'roles',
                'permissions',
                'parent',
                'addedBy',
                'children'
            ])
            ->when($request->has('status'), function($query) use ($request) {
                $query->where('status', $request->status);
            });
            if(!$request->has('agents') && !$request->has('chat')){
            
                    // Apply hierarchical filtering based on user role
                    if ($user->hasRole('sales')) {
                        // Sales agents can only see themselves and their team members
                        $query->where(function($q) use ($user) {
                            $q->where('id', $user->id)
                            ->orWhere('parent_id', $user->id);
                        });
                    } elseif ($user->hasRole('team_lead')) {
                        // Team leaders can see their team and themselves
                        $query->where(function($q) use ($user) {
                            $q->where('id', $user->id)
                            ->orWhere('parent_id', $user->id)
                            ->orWhereHas('parent', function($parentQuery) use ($user) {
                                $parentQuery->where('id', $user->id);
                            });
                        });
                    } elseif ($user->hasRole('manager')) {
                        // Sales managers can see their entire hierarchy
                        $query->where(function($q) use ($user) {
                            $q->where('id', $user->id)
                            ->orWhere('parent_id', $user->id)
                            ->orWhereHas('parent', function($parentQuery) use ($user) {
                                $parentQuery->where('parent_id', $user->id);
                            });
                        });
                    }
                }
            if($request->has('parent_id')){
                $parent=$request->parent_id;
                $query->where(function($q) use ($parent) {
                    $q->where('id', $parent)
                      ->orWhere('parent_id', $parent)
                      ->orWhereHas('parent', function($parentQuery) use ($parent) {
                          $parentQuery->where('parent_id', $parent);
                      });
                });
            }
            // Super Admin can see all users automatically
            
            // Search filter
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }
            
            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            // Filter by role
            if ($request->has('role')) {
                $query->whereHas('roles', function($q) use ($request) {
                    $q->where('name', $request->role);
                });
            }

            if($request->has('agents')){
                  $query->whereHas('listings',function($q){
                            // $q->where('is_active',true)->whereNotIn('status',['converted','draft'])->where('is_archived',false);
                  });
            }
            $users = $query->orderBy('created_at','desc')->where('id','!=',auth()->user()->id)->get();
            
            return ApiResponse::success(
                UserResource::collection($users),
                'Users retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve users: ' . $e->getMessage());
        }
    }
    public function permissions(User $user): JsonResponse
{
    try {
        $permissions = $user->getAllPermissions(); // من spatie/permission

        return ApiResponse::success(
            $permissions,
            'User permissions retrieved successfully'
        );
    } catch (\Exception $e) {
        return ApiResponse::error('Failed to retrieve user permissions: ' . $e->getMessage());
    }
}


 public function getUsersWithChildren(Request $request)
    {
        try {
            $users = User::with([
                'roles',
                'permissions',
                'parent' => function($query) {
                    $query->select('id', 'name', 'email');
                },
                'children' => function($query) {
                    $query->withCount('children')
                          ->with('roles');
                }
            ])
            ->withCount('children')
            // ->where('status', 'active')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'avatar' => $user->avatar ? asset('storage/'. $user->avatar) : null,
                    'status' => $user->status,
                    'parent_id' => $user->parent_id,
                    'parent_name' => $user->parent?->name,
                    'role_name' => $user->roles->first()?->name,
                    'role_id' => $user->roles->first()?->id,
                    'team_members_count' => $user->children_count,
                    'last_login_at' => $user->last_login_at,
                    'created_at' => $user->created_at,
                    'children' => $user->children->map(function ($child) {
                        return [
                            'id' => $child->id,
                            'name' => $child->name,
                            'email' => $child->email,
                            'role_name' => $child->roles->first()?->name,
                            'avatar' => $child->avatar ? asset('storage/'. $child->avatar) : null,
                            'status' => $child->status,
                            'team_members_count' => $child->children_count,
                            'last_login_at' => $child->last_login_at,
                        ];
                    })
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $users,
                'message' => 'Users with children retrieved successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Users with Children Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve users with children'
            ], 500);
        }
    }

    /**
     * Create a new user
     */
    public function store(UserRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            // Handle avatar upload
            if ($request->hasFile('avatar')) {
                $avatar = $request->file('avatar');
                $avatarPath = $avatar->store("users/avatars", 'public');
                $data['avatar'] = $avatarPath;
            }
            
            // Hash password
            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }
            
            // Set parent_id and created_by
            $data['parent_id'] = $data['parent_id'] ?? Auth::id();
            $data['added_by'] = Auth::id();
            
            // إزالة role_id من البيانات علشان مايتحطش في الداتابيز
            unset($data['role_id']);
            
            // Create user
            $user = User::create($data);
            
            // Assign role if provided
            if ($request->has('role_id')) {
                $role = Role::find($request->role_id);
                if ($role) {
                    $user->assignRole($role);
                }
            }

            $user->load(['roles', 'parent']);

            return ApiResponse::success(
                new UserResource($user),
                'User created successfully', 
                201
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create user: ' . $e->getMessage());
        }
    }


    /**
     * Get a single user with hierarchical access control
     */
public function show(User $user): JsonResponse
{
    try {
        $currentUser = Auth::user();

        // منع عرض بروفايل admin و super_admin لغيرهم
        if (
            $user->hasRole(['super_admin', 'admin']) &&
            !$currentUser->hasRole(['super_admin', 'admin'])
        ) {
            return ApiResponse::error('Access denied', 403);
        }

        $user->load(['roles', 'parent', 'children']);

        return ApiResponse::success(
            new UserResource($user),
            'User retrieved successfully'
        );
    } catch (\Exception $e) {
        return ApiResponse::error('Failed to retrieve user: ' . $e->getMessage());
    }
}


    /**
     * Update a user with hierarchical access control
     */
    public function update(UserRequest $request, User $user): JsonResponse
    {
        try {
            $currentUser = Auth::user();
            
            // Check hierarchical access
            if (!$this->canAccessUser($currentUser, $user)) {
                return ApiResponse::error('Access denied', 403);
            }
            
            $data = $request->validated();
                if (!$currentUser->hasRole(['super_admin', 'admin'])) {
                        unset($data['status']);
                
                }
            // Handle avatar upload
            if ($request->hasFile('avatar')) {
                // Delete old avatar
                if ( $user->getRawOriginal('avatar') &&     $user->getRawOriginal('avatar') !== 'users/user.png' &&     Storage::disk('public')->exists($user->avatar)) {
                    Storage::disk('public')->delete($user->avatar);
                }
                
                // Store new avatar
                $avatar = $request->file('avatar');
                $avatarPath = $avatar->store("users/avatars", 'public');
                $data['avatar'] = $avatarPath;
            }
            
            // Hash password if provided
            if (isset($data['password']) && $data['password']) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }
            
            unset($data['role_id']);

            // Update role if provided and user has permission
            if ($request->has('role_id') && $currentUser->can('roles-assign')) {
                $role = Role::where('id',$request->role_id)->first();
                if ($role) {

                    // استبدال الدور القديم بالجديد (هذا ما يبدو أنك تريده)
                    $user->syncRoles([$role->name]);
                    
                    // أو إذا كنت تريد الاحتفاظ بالأدوار القديمة وإضافة الجديد
                    // $user->assignRole($role->name);
                }
            }
            $user->update($data);
            $user->load(['roles', 'parent']);

            return ApiResponse::success(
                new UserResource($user),
                'User updated successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update user: ' . $e->getMessage());
        }
    }

    /**
     * Delete a user with hierarchical access control
     */
    public function destroy(User $user): JsonResponse
    {
        try {
            $currentUser = Auth::user();
            
            // Check hierarchical access
            if (!$this->canAccessUser($currentUser, $user)) {
                return ApiResponse::error('Access denied', 403);
            }
            
            // Prevent users from deleting themselves
            if ($user->id === $currentUser->id) {
                return ApiResponse::error('You cannot delete your own account', 422);
            }
            
            // Prevent deletion of users with team members
            if ($user->children()->exists()) {
                return ApiResponse::error('Cannot delete user with team members. Please reassign team members first.', 422);
            }
            
            // Delete avatar if exists
            if ( $user->getRawOriginal('avatar') &&     $user->getRawOriginal('avatar') !== 'users/user.png' &&     Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            
            $user->delete();

            return ApiResponse::success(null, 'User deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete user: ' . $e->getMessage());
        }
    }

    /**
     * Get available managers for hierarchical structure
     */
    public function getAvailableManagers(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $query = User::whereHas('roles', function($q) {
                $q->whereIn('name', ['super_admin','admin', 'manager', 'team_lead']);
            })->where('status', 'active');
            
            // Apply hierarchical filtering
            if ($user->hasRole('manager')) {
                $query->where(function($q) use ($user) {
                    $q->where('id', $user->id)
                      ->orWhere('parent_id', $user->id);
                });
            } elseif ($user->hasRole('team_lead')) {
                $query->where('id', $user->id);
            }
            // Super Admin can see all managers
            
            $managers = $query->get(['id', 'name', 'email']);
            
            return ApiResponse::success(
                $managers,
                'Available managers retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve managers: ' . $e->getMessage());
        }
    }

    /**
     * Get user statistics with hierarchical filtering
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $query = User::query();
            
            // Apply hierarchical filtering
            if ($user->hasRole('sales')) {
                $query->where('id', $user->id);
            } elseif ($user->hasRole('team_lead')) {
                $query->where(function($q) use ($user) {
                    $q->where('id', $user->id)
                      ->orWhere('parent_id', $user->id);
                });
            } elseif ($user->hasRole('manager')) {
                $query->where(function($q) use ($user) {
                    $q->where('id', $user->id)
                      ->orWhere('parent_id', $user->id)
                      ->orWhereHas('parent', function($parentQuery) use ($user) {
                          $parentQuery->where('parent_id', $user->id);
                      });
                });
            }
            
            $totalUsers = $query->count();
            $activeUsers = $query->where('status', 'active')->count();
            $inactiveUsers = $query->where('status', 'in_active')->count();
            $blockedUsers = $query->where('status', 'blocked')->count();

            $stats = [
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'inactive_users' => $inactiveUsers,
                'blocked_users' => $blockedUsers,
                'active_percentage' => $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100, 2) : 0,
            ];

            return ApiResponse::success(
                $stats,
                'Users statistics retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve users statistics: ' . $e->getMessage());
        }
    }

    /**
     * Get team members for a user (direct children only)
     */
    public function getTeamMembers(User $user): JsonResponse
    {
        try {
            $currentUser = Auth::user();
            
            // Check hierarchical access
            if (!$this->canAccessUser($currentUser, $user)) {
                return ApiResponse::error('Access denied', 403);
            }
            
            $teamMembers = $user->children()
                ->with(['roles', 'parent'])
                ->orderBy('created_at', 'desc')
                ->get();
            
            return ApiResponse::success(
                UserResource::collection($teamMembers),
                'Team members retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve team members: ' . $e->getMessage());
        }
    }

    /**
     * Get ALL team members under a user (full hierarchy: team leads + sales + etc.)
     * Returns a flat list of every user who reports to this user, at any level.
     */
    public function getTeamMembersRecursive(User $user): JsonResponse
    {
        try {
            $currentUser = Auth::user();

            if (!$this->canAccessUser($currentUser, $user)) {
                return ApiResponse::error('Access denied', 403);
            }

            $descendantIds = $this->collectDescendantIds($user->id);

            if (empty($descendantIds)) {
                return ApiResponse::success([], 'Team members retrieved successfully');
            }

            $teamMembers = User::whereIn('id', $descendantIds)
                ->with(['roles', 'parent'])
                ->get()
                ->sortBy(function ($u) use ($descendantIds) {
                    $pos = array_search($u->id, $descendantIds);
                    return $pos !== false ? $pos : 9999;
                })
                ->values();

            return ApiResponse::success(
                UserResource::collection($teamMembers),
                'Team members retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve team members: ' . $e->getMessage());
        }
    }

    /**
     * Collect all descendant user IDs (everyone under this user, any depth).
     */
    private function collectDescendantIds(int $parentId): array
    {
        $ids = [];
        $queue = User::where('parent_id', $parentId)->pluck('id')->toArray();
        while (!empty($queue)) {
            $ids = array_merge($ids, $queue);
            $queue = User::whereIn('parent_id', $queue)->pluck('id')->toArray();
        }
        return $ids;
    }

    /**
     * Check if current user can access target user based on hierarchy
     */
    private function canAccessUser(User $currentUser, User $targetUser): bool
    {
        // Super Admin can access all users
        if ($currentUser->hasRole('admin') || $currentUser->hasRole('super_admin')) {
            return true;
        }
        
        // Users can always access themselves
        if ($currentUser->id === $targetUser->id) {
            return true;
        }
        
        // Check hierarchical access based on roles
        if ($currentUser->hasRole('manager')) {
            // Sales Manager can access their direct reports and their reports
            return $this->isInUserHierarchy($currentUser, $targetUser);
        } elseif ($currentUser->hasRole('team_lead')) {
            // Team Leader can access their direct reports
            return $targetUser->parent_id === $currentUser->id;
        } elseif ($currentUser->hasRole('sales')) {
            // Sales agents can only access themselves
            return false;
        }
        
        return false;
    }

    /**
     * Check if target user is in current user's hierarchy
     */
    private function isInUserHierarchy(User $currentUser, User $targetUser): bool
        {
            return User::where(function($q) use ($currentUser) {
                $q->where('id', $currentUser->id)
                ->orWhere('parent_id', $currentUser->id)
                ->orWhereHas('parent', function($parentQuery) use ($currentUser) {
                    $parentQuery->where('parent_id', $currentUser->id);
                });
            })->where('id', $targetUser->id)->exists();
        }
     public function getOnlineUsers(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Start the query
            $query = User::with(['roles', 'parent'])
                ->where('status', 'active')
                ->whereNotNull('last_login_at')
                ->where('last_login_at', '>=', Carbon::now()->subMinutes(15));
            
            // Apply hierarchical filtering based on user role
            if ($user->hasRole('sales')) {
                $query->where('id', $user->id);
            } elseif ($user->hasRole('team_lead')) {
                $query->where(function($q) use ($user) {
                    $q->where('id', $user->id)
                      ->orWhere('parent_id', $user->id);
                });
            } elseif ($user->hasRole('manager')) {
                $query->where(function($q) use ($user) {
                    $q->where('id', $user->id)
                      ->orWhere('parent_id', $user->id)
                      ->orWhereHas('parent', function($parentQuery) use ($user) {
                          $parentQuery->where('parent_id', $user->id);
                      });
                });
            }
            // Super Admin can see all online users
            
            $onlineUsers = $query->orderBy('last_login_at', 'desc')->get();
            
            return ApiResponse::success(
                UserResource::collection($onlineUsers),
                'Online users retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve online users: ' . $e->getMessage());
        }
    }

    /**
     * Get users login activity (last 24 hours, 7 days, etc.)
     */
    public function getLoginActivity(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $timeRange = $request->get('range', '24h'); // 24h, 7d, 30d
            
            $startDate = match($timeRange) {
                '7d' => Carbon::now()->subDays(7),
                '30d' => Carbon::now()->subDays(30),
                default => Carbon::now()->subHours(24),
            };
            
            $query = User::with(['roles', 'parent'])
                ->where('status', 'active')
                ->whereNotNull('last_login_at')
                ->where('last_login_at', '>=', $startDate);
            
            // Apply hierarchical filtering
            if ($user->hasRole('sales')) {
                $query->where('id', $user->id);
            } elseif ($user->hasRole('team_lead')) {
                $query->where(function($q) use ($user) {
                    $q->where('id', $user->id)
                      ->orWhere('parent_id', $user->id);
                });
            } elseif ($user->hasRole('manager')) {
                $query->where(function($q) use ($user) {
                    $q->where('id', $user->id)
                      ->orWhere('parent_id', $user->id)
                      ->orWhereHas('parent', function($parentQuery) use ($user) {
                          $parentQuery->where('parent_id', $user->id);
                      });
                });
            }
            
            $loginActivity = $query->orderBy('last_login_at', 'desc')->get();
            
            $stats = [
                'total_logins' => $loginActivity->count(),
                'time_range' => $timeRange,
                'start_date' => $startDate,
                'users' => UserResource::collection($loginActivity)
            ];

            return ApiResponse::success(
                $stats,
                'Login activity retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve login activity: ' . $e->getMessage());
        }
    }
    public function updateStatus(Request $request, $id): JsonResponse
{
    try {
        // Validation
        $request->validate([
            'status' => 'required|in:active,in_active,blocked'
        ]);

        // Find user
        $user = User::find($id);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Prevent updating super admin (user id 1)
        if ($user->id == 1) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update super admin status'
            ], 403);
        }

        // Check if current user has permission (only super_admin and admin)
        $currentUser = Auth::user();
        if (!$currentUser->hasRole(['super_admin', 'admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update user status'
            ], 403);
        }

        // Update status
        $user->update([
            'status' => $request->status
        ]);
        if ($request->status === 'active') {
             Mail::to($user->email)->send(
                new AccountActivatedMail($user->email, $user->name)
            );
        }
        // Reload user with relationships
        $user->load(['roles', 'parent']);

        return response()->json([
            'success' => true,
            'message' => 'User status updated successfully',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'role_name' => $user->roles->first()? $user->roles->first()->name : 'Unknown Role',
                'parent_name' => $user->parent->name ?? null,
            ]
        ]);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
        
    } catch (\Exception $e) {
        \Log::error('Error updating user status: ' . $e->getMessage());
        
        return response()->json([
            'success' => false,
            'message' => 'Failed to update user status',
            'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
        ], 500);
    }
}
 public function getByRole($role, Request $request): JsonResponse
{
    try {
        $validRoles = ['manager', 'team_lead', 'sales'];
        
        if (!in_array($role, $validRoles)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid role'
            ], 400);
        }

        $users = User::role($role)
            ->where('status', 'active')
            ->select('id', 'name', 'email', 'parent_id')
            ->orderBy('name')
            ->get()
            ->map(function ($user) use ($role) {
                $userData = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $role
                ];

                // للـ Team Leads نضيف manager_id
                if ($role === 'team_lead') {
                    $userData['manager_id'] = $user->parent_id;
                }

                return $userData;
            });

        return response()->json([
            'success' => true,
            'message' => 'Users retrieved successfully',
            'data' => $users
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to retrieve users: ' . $e->getMessage()
        ], 500);
    }
}

public function updateBiometricCode(Request $request, $id)
{
    $request->validate([
        'biometric_code' => [
            'nullable',
            'string',
            'max:50',
            Rule::unique('users', 'biometric_code')->ignore($id),
        ]
    ]);

    $user = User::findOrFail($id);

    if (!auth()->user()->can('users-code')) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $user->biometric_code = $request->biometric_code;
    $user->save();

    return response()->json([
        'message' => 'Biometric code updated',
        'data' => $user
    ]);
}
}