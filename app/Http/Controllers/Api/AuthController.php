<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\User\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Helpers\ApiResponse;
use App\Http\Resources\User\NotificationResource;
use Illuminate\Http\JsonResponse;
use App\Notifications\NewSalesAgentNotification;
class AuthController extends Controller
{
     public function register(RegisterRequest $request): JsonResponse
    {
        try {
           $parent = User::find($request->parent_id);
        if ($parent && !$parent->hasRole('manager') && !$parent->hasRole('team_lead')) {
            return response()->json([
                'success' => false,
                'message' => 'Selected supervisor must be a manager or team lead'
            ], 400);
        }
            $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'status' => 'in_active',
            // 'parent_id' => $request->parent_id,
        ]);
        if($parent){
         $this->sendParentNotification($parent, $user);
        }
    $superAdmins = User::whereHas('roles', function ($query) {
            $query->where('name', 'super_admin');
        })->get();

        // أرسل notification لكل super_admin
        foreach ($superAdmins as $superAdmin) {
          $this->sendParentNotification($superAdmin, $user);
        }

            $user->assignRole('sales');

            $token = JWTAuth::fromUser($user);

            return response()->json([
                'success' => true,
                'message' => 'Sales agent registered successfully',
                'data' => [
                    'user' => $user,
                    'token' => $token
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed: ' . $e->getMessage()
            ], 500);
        }
    }
    private function sendParentNotification(User $parent, User $newSales)
    {
        try {
        
            $parent->notify(new NewSalesAgentNotification($newSales));
            
        
            \Log::info("Notification sent to parent {$parent->name} about new sales agent {$newSales->name}");

        } catch (\Exception $e) {
            \Log::error('Failed to send parent notification: ' . $e->getMessage());
        }
    }
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $credentials = $request->validated();

            // Check if user exists and is active
            $user = User::where('email', $credentials['email'])->first();
            
            if (!$user) {
                return ApiResponse::error('Invalid credentials', 401);
            }

            // Check user status
            if ($user->status !== 'active') {
                $statusMessage = match($user->status) {
                    'in_active' => 'Your account is inactive. Please contact administrator.',
                    'blocked' => 'Your account has been blocked. Please contact administrator.',
                    default => 'Your account is not active.',
                };
                return ApiResponse::error($statusMessage, 403);
            }

            // Attempt login
            if (! $token = auth()->attempt($credentials)) {
                return ApiResponse::error('Invalid credentials', 401);
            }

            // Update last login
            $user->update(['last_login_at' => now()]);

            $user->load('roles', 'permissions');

            return ApiResponse::success([
                'user' => new UserResource($user),
                'token' => $token,
            ], 'Login successful');

        } catch (\Exception $e) {
            return ApiResponse::error('Login failed: ' . $e->getMessage());
        }
    }


    public function profile()
    {
            $user = auth()->user()->load('roles', 'permissions');
        return ApiResponse::success(new UserResource($user), 'User data retrieved successfully');
    }

    public function logout()
    {
        auth()->logout();
        return ApiResponse::success(null, 'Logged out successfully');
    }


    public function notifications(): JsonResponse
{
    try {
        $user = auth()->user();
        
        $notifications = $user->notifications()
            ->orderBy('created_at', 'desc')
            ->get();

        return ApiResponse::success(
            NotificationResource::collection($notifications), // هنا
            'Notifications retrieved successfully'
        );

    } catch (\Exception $e) {
        return ApiResponse::error('Failed to retrieve notifications: ' . $e->getMessage());
    }
}
public function markAllNotificationsAsRead(): JsonResponse
{
    try {
        auth()->user()->unreadNotifications->markAsRead();
        return ApiResponse::success(null, 'All notifications marked as read');
    } catch (\Exception $e) {
        return ApiResponse::error('Failed to mark notifications as read: ' . $e->getMessage());
    }
}

public function markNotificationAsRead($id): JsonResponse
{
    try {
        $notification = auth()->user()->notifications()->where('id', $id)->first();
        
        if ($notification) {
            $notification->markAsRead();
            return ApiResponse::success(null, 'Notification marked as read');
        }
        
        return ApiResponse::error('Notification not found');
    } catch (\Exception $e) {
        return ApiResponse::error('Failed to mark notification as read: ' . $e->getMessage());
    }
}
// في AuthController أو NotificationController
public function deleteNotification($id): JsonResponse
{
    try {
        $notification = auth()->user()->notifications()->where('id', $id)->first();
        
        if ($notification) {
            $notification->delete();
            return ApiResponse::success(null, 'Notification deleted successfully');
        }
        
        return ApiResponse::error('Notification not found');
    } catch (\Exception $e) {
        return ApiResponse::error('Failed to delete notification: ' . $e->getMessage());
    }
}
}
