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
            $currentUser=$user;
            // Get all users with their relationships
            $users =User::with([
                'roles',
                'permissions',
                'parent' => function($query) {
                    $query->select('id', 'name', 'email');
                },
                'children' => function($query) {
                    $query->withCount('children');
                },
                'addedBy' => function($query) {
                    $query->select('id', 'name');
                }
            ])
            ->when(!($user->hasRole('super_admin')|| $user->hasRole('admin')) ,function($qq)use ($currentUser){
            $qq->where(function($q) use ($currentUser) {
                $q->where('id', $currentUser->id)
                ->orWhere('parent_id', $currentUser->id)
                ->orWhereHas('parent', function($parentQuery) use ($currentUser) {
                    $parentQuery->where('parent_id', $currentUser->id);
                });
            });
            })
            // ->where('status', 'active') // Only active users
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
                    'team_members_count' => $user->children()->count(),
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
                'message' => 'Team hierarchy retrieved successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Team Hierarchy Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve team hierarchy',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
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
            $users = User::with([
                'roles',
                'parent',
                'children' => function($query) {
                    $query->withCount('children');
                }
            ])
            ->when(!($user->hasRole('super_admin')|| $user->hasRole('admin')) ,function($qq)use ($currentUser){
            $qq->where(function($q) use ($currentUser) {
                $q->where('id', $currentUser->id)
                ->orWhere('parent_id', $currentUser->id)
                ->orWhereHas('parent', function($parentQuery) use ($currentUser) {
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
}