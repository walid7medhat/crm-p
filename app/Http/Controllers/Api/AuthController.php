<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\User\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Helpers\ApiResponse;
use App\Http\Resources\User\NotificationResource;
use App\Services\Auth\LocationTrackingService;
use App\Services\LiveWatch\LiveWatchEventRecorder;
use App\Services\LiveWatch\LiveWatchPresenceService;
use Illuminate\Http\JsonResponse;
use App\Notifications\BirthdaySelfNotification;
use App\Notifications\NewSalesAgentNotification;
use App\Support\Brand\BrandContext;
use App\Support\Brand\BrandResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

            // Never mint a JWT for in_active registrations — account must be activated first.
            return response()->json([
                'success' => true,
                'message' => 'Sales agent registered successfully. An administrator must activate your account before you can sign in.',
                'data' => [
                    'user' => $user,
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Registration failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Registration failed.',
            ], 500);
        }
    }

    public function forgotPassword(Request $request): JsonResponse
{
    $request->validate([
        'email' => 'required|email',
    ]);

    $email = strtolower(trim((string) $request->input('email')));
    $generic = 'If that email is registered, you will receive a link to reset your password shortly.';

    // Avoid user enumeration: same success response whether or not the email exists.
    $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
    if ($user) {
        Password::sendResetLink(['email' => $user->email]);
    } else {
        Log::info('forgot-password requested for unknown email', [
            'email_hash' => hash('sha256', $email),
            'ip' => $request->ip(),
        ]);
    }

    return ApiResponse::success(null, $generic);
}

public function resetPassword(Request $request): JsonResponse
{
    $request->validate([
        'email' => 'required|email',
        'token' => 'required',
        'password' => 'required|min:6|confirmed',
    ]);

    $credentials = $request->only('email', 'password', 'password_confirmation', 'token');
    $setPassword = function ($user, $password) {
        $user->forceFill([
            'password' => Hash::make($password),
            'remember_token' => Str::random(60),
        ])->save();
    };

    $status = Password::reset($credentials, $setPassword);

    // Links from the "your account" email live 7 days (account_setup broker, same token table);
    // the default broker rejects them after 60 minutes, so retry with the longer one.
    if ($status === Password::INVALID_TOKEN) {
        $status = Password::broker('account_setup')->reset($credentials, $setPassword);
    }

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
        $email = strtolower(trim((string) $credentials['email']));

        // Bound lookup; compare against a real bcrypt dummy when missing to reduce timing skew.
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        static $dummyHash = null;
        $dummyHash ??= Hash::make('invalid-dummy-password');
        $hash = $user?->password ?: $dummyHash;
        $passwordOk = Hash::check($credentials['password'], $hash);

        // Uniform response for unknown email and wrong password (anti-enumeration).
        if (! $user || ! $passwordOk) {
            return ApiResponse::error('Invalid email or password.', 401);
        }

        if ($user->status !== 'active') {
            $statusMessage = match ($user->status) {
                'in_active' => 'Your account is inactive. Please contact administrator.',
                'blocked' => 'Your account has been blocked. Please contact administrator.',
                default => 'Your account is not active.',
            };

            return ApiResponse::error($statusMessage, 403);
        }

        // Host-based brand gate (Luxvant allowlist). Same Super Admin user id/email/password.
        $brand = app(BrandContext::class)->get();
        $brandResolver = app(BrandResolver::class);
        if (! $brandResolver->userMayAccess($brand, $user)) {
            Log::warning('brand login denied', [
                'brand' => $brand['key'] ?? null,
                'user_id' => $user->id,
                'email' => $user->email,
                'host' => $request->getHost(),
            ]);

            return ApiResponse::error(
                'Your account is not authorized for '.($brand['company_name'] ?? 'this workspace').'.',
                403
            );
        }

        // Create token with brand claim for host/JWT consistency checks.
        $token = JWTAuth::claims([
            'brand_key' => $brand['key'] ?? 'oia',
        ])->fromUser($user);

        // Always stamp last login time/IP (location/geolocation stays optional via auth/location).
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $user->refresh();
        $user->load('roles', 'permissions');

        try {
            app(LiveWatchEventRecorder::class)->record([
                'user_id' => $user->id,
                'event_kind' => 'navigation',
                'event_type' => 'auth.login',
                'module' => 'auth',
                'action' => 'login',
                'client_event_id' => 'login-'.$user->id.'-'.now()->timestamp,
                'metadata' => ['path' => '/login'],
            ]);
        } catch (\Throwable $e) {
            Log::debug('live watch login event skipped', ['error' => $e->getMessage()]);
        }

        $payload = [
            'user' => new UserResource($user),
            'token' => $token,
            'brand' => app(BrandContext::class)->toFrontendArray(),
        ];

        $publicKey = (string) config('services.web_push.public_key');
        $privateKey = (string) config('services.web_push.private_key');
        if ($publicKey !== '' && $privateKey !== '') {
            $payload['web_push'] = [
                'prompt' => true,
                'public_key' => $publicKey,
            ];
        }

        return ApiResponse::success($payload, 'Login successful');

    } catch (\Exception $e) {
        Log::error('Login failed', ['error' => $e->getMessage()]);

        return ApiResponse::error('Login failed.');
    }
}
public function updateLocation(Request $request, LocationTrackingService $tracking): JsonResponse
{
    $request->validate([
        'latitude' => 'required|numeric|between:-90,90',
        'longitude' => 'required|numeric|between:-180,180',
        'accuracy' => 'nullable|numeric|min:0|max:1000000',
        'captured_at' => 'nullable|date',
        // Client hint: becoming active/online again (not a login). Server also
        // detects new sessions from the CRM online window when this is omitted.
        'session_start' => 'nullable|boolean',
    ]);

    // Super admin "switch account" sessions must not overwrite the user's real location.
    if ($request->attributes->get('impersonated')) {
        return ApiResponse::success([
            'location_updated' => false,
            'history_recorded' => false,
        ], 'Location ignored while switched account');
    }

    // Always the authenticated account. A user id in the body is ignored.
    $result = $tracking->record(auth()->user(), $request);

    $message = 'Location unchanged';
    if ($result['location_updated'] && $result['history_recorded']) {
        $message = 'Location saved';
    } elseif ($result['location_updated']) {
        $message = 'Location updated';
    }

    return ApiResponse::success($result, $message);
}

    /**
     * Foreground tracking knobs for the SPA (interval only — history thresholds stay server-side).
     */
    public function locationSettings(): JsonResponse
    {
        return ApiResponse::success([
            'interval_seconds' => (int) config('location_tracking.interval_seconds', 180),
            'fresh_minutes' => (int) config('location_tracking.fresh_minutes', 10),
            'online_window_minutes' => (int) config('location_tracking.online_window_minutes', 15),
            'background_tracking' => false,
            'note' => 'Location follows CRM online presence while the app is open and visible. Background GPS is not supported in the browser/PWA. A new login is not required.',
        ]);
    }
    /**
     * "Switch account" — super_admin (any user) and user #30 (sales users only, see
     * User::canSwitchInto). Mints a JWT for the target user, same
     * shape as login(), so the frontend can drop it straight into localStorage.
     * The frontend is responsible for stashing the super admin's own token first
     * so "Return to Super Admin" can restore it without a second login.
     */
    public function impersonate(User $user): JsonResponse
    {
        try {
            $currentUser = auth()->user();

            if (!$currentUser || !$currentUser->canSwitchInto($user)) {
                return ApiResponse::error('You are not allowed to switch to this account', 403);
            }

            if ((int) $currentUser->id === (int) $user->id) {
                return ApiResponse::error('You are already using this account', 422);
            }

            if ($user->status !== 'active') {
                return ApiResponse::error('Cannot switch to an inactive account', 422);
            }

            $brand = app(BrandContext::class)->get();
            if (!app(BrandResolver::class)->userMayAccess($brand, $user)) {
                return ApiResponse::error(
                    'This account is not authorized for ' . ($brand['company_name'] ?? 'this workspace') . '.',
                    403
                );
            }

            // Tag the token so presence (last_login_at) and device location are not
            // touched while the super admin is browsing as this user.
            $token = auth()->claims([
                'impersonated_by' => (int) $currentUser->id,
                'brand_key' => $brand['key'] ?? 'oia',
            ])->login($user);
            $user->load('roles', 'permissions');

            \Log::info('User switched account', [
                'super_admin_id' => $currentUser->id,
                'super_admin_name' => $currentUser->name,
                'target_user_id' => $user->id,
                'target_user_name' => $user->name,
            ]);

            return ApiResponse::success([
                'user' => new UserResource($user),
                'token' => $token,
            ], 'Switched account successfully');

        } catch (\Exception $e) {
            return ApiResponse::error('Failed to switch account: ' . $e->getMessage());
        }
    }

    public function profile()
    {
            $user = auth()->user()->load('roles', 'permissions');
        return ApiResponse::success(new UserResource($user), 'User data retrieved successfully');
    }

    public function logout()
    {
        $user = auth()->user();
        if ($user) {
            try {
                app(LiveWatchEventRecorder::class)->record([
                    'user_id' => $user->id,
                    'event_kind' => 'navigation',
                    'event_type' => 'auth.logout',
                    'module' => 'auth',
                    'action' => 'logout',
                    'client_event_id' => 'logout-'.$user->id.'-'.now()->timestamp,
                    'metadata' => ['path' => '/logout'],
                ]);
                // Drop all presence sessions for this user on explicit logout.
                \App\Models\LiveWatchSession::query()->where('user_id', $user->id)->delete();
                app(LiveWatchPresenceService::class)->reconcilePresence();
            } catch (\Throwable $e) {
                Log::debug('live watch logout event skipped', ['error' => $e->getMessage()]);
            }
        }

        auth()->logout();
        return ApiResponse::success(null, 'Logged out successfully');
    }


    public function notifications(): JsonResponse
{
    try {
        $user = auth()->user();
        $request = request();

        // Badge only. Avoid loading every row on page open.
        if ($request->boolean('unread_count')) {
            return ApiResponse::success([
                'unread_count' => $user->unreadNotifications()->count(),
            ], 'Notifications retrieved successfully');
        }

        $notificationsQuery = $user->notifications()->orderBy('created_at', 'desc');

        // Birthday popup asks for one type instead of the full history.
        if ($request->filled('type')) {
            $notificationsQuery->where('type', (string) $request->query('type'));
        }

        $notifications = $notificationsQuery->get();

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

    /**
     * Check whether the authenticated user has a birthday today.
     * Celebration overlay/banner is only for that user — not all staff.
     */
    public function todaysBirthdays(): JsonResponse
    {
        try {
            $authUser = auth()->user();
            $today = now();
            $users = User::query()
                ->activeBirthdayOn($today)
                ->orderBy('id')
                ->get(['id', 'name']);

            $count = $users->count();
            $isMyBirthday = $authUser
                && $users->contains(fn (User $u) => (int) $u->id === (int) $authUser->id);

            $firstName = null;
            if ($isMyBirthday) {
                try {
                    $this->ensureBirthdaySelfNotification($authUser, $today);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Birthday greeting notification skipped: '.$e->getMessage());
                }
                $full = trim((string) ($authUser->name ?? ''));
                if ($full !== '') {
                    $parts = preg_split('/\s+/u', $full, 2) ?: [];
                    $firstName = ($parts[0] ?? '') !== '' ? $parts[0] : null;
                }
            }

            return ApiResponse::success([
                'has_birthday' => (bool) $isMyBirthday,
                'count' => $count,
                'first_name' => $firstName,
            ], 'Today\'s birthdays checked');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to check today\'s birthdays: ' . $e->getMessage());
        }
    }

    /**
     * The 08:00 job may not have run yet. Create the in-app greeting when the
     * birthday user opens the system so the celebration does not depend on it.
     */
    private function ensureBirthdaySelfNotification(User $user, $today): void
    {
        Cache::lock('birthday-self:'.$user->id.':'.$today->toDateString(), 10)->get(function () use ($user, $today) {
            $already = $user->notifications()
                ->where('type', BirthdaySelfNotification::class)
                ->whereDate('created_at', $today->toDateString())
                ->exists();

            if (! $already) {
                $user->notify(new BirthdaySelfNotification());
            }
        });
    }
}
