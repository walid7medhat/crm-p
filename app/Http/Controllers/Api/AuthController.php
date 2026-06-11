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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
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

    public function forgotPassword(Request $request): JsonResponse
{
    $request->validate([
        'email' => 'required|email|exists:users,email',
    ]);

    $status = Password::sendResetLink($request->only('email'));

    return $status === Password::RESET_LINK_SENT
        ? ApiResponse::success(null, 'Reset link sent to your email')
        : ApiResponse::error('Unable to send reset link');
}

public function resetPassword(Request $request): JsonResponse
{
    $request->validate([
        'email' => 'required|email',
        'token' => 'required',
        'password' => 'required|min:6|confirmed',
    ]);

    $status = Password::reset(
        $request->only('email', 'password', 'password_confirmation', 'token'),
        function ($user, $password) {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();
        }
    );

    return $status === Password::PASSWORD_RESET
        ? ApiResponse::success(null, 'Password reset successfully')
        : ApiResponse::error('Failed to reset password');
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

        // Check if user exists
        $user = User::where('email', $credentials['email'])->first();

        if (!$user) {
            return ApiResponse::error('Email address is not registered.', 404);
        }

        // Check password
        if (!Hash::check($credentials['password'], $user->password)) {
            return ApiResponse::error('The password you entered is incorrect.', 401);
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

        // Create token
        $token = auth()->login($user);

        // Resolve the user's location. Prefer the exact GPS coordinates sent by the
        // browser (street-level); fall back to IP geolocation (city-level) when the
        // user denied location access or the browser didn't provide it.
        $ip = $request->ip();
        $lat = $request->input('latitude');
        $lng = $request->input('longitude');

        if (is_numeric($lat) && is_numeric($lng)) {
            $location = \App\Helpers\LocationHelper::fromCoords($lat, $lng);
        } else {
            $location = \App\Helpers\LocationHelper::fromIp($ip);
        }

        $address = \App\Helpers\LocationHelper::toAddress($location);

        // Update last login details
        $user->update([
            'last_login_at'       => now(),
            'last_login_ip'       => $ip,
            'last_login_location' => $address ?? $user->last_login_location,
            'last_login_lat'      => $location['lat'] ?? $user->last_login_lat,
            'last_login_lng'      => $location['lon'] ?? $user->last_login_lng,
        ]);

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
