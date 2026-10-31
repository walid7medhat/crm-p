<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserLocationHistory;
use App\Services\Auth\LoginLocationUpdate;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationTrackingController extends Controller
{
    public function online(Request $request): JsonResponse
    {
        $freshMinutes = max(1, (int) config('location_tracking.fresh_minutes', 10));
        $onlineSince = now()->subMinutes(15);

        $users = User::query()
            ->with('roles')
            ->where('status', 'active')
            ->whereNotNull('last_login_at')
            ->where('last_login_at', '>=', $onlineSince)
            ->orderByDesc('last_login_at')
            ->get();

        $payload = $users->map(function (User $user) use ($freshMinutes) {
            return $this->latestLocationPayload($user, $freshMinutes, online: true);
        });

        return ApiResponse::success([
            'fresh_minutes' => $freshMinutes,
            'online_window_minutes' => 15,
            'background_tracking' => false,
            'users' => $payload,
        ], 'Online location snapshot');
    }

    public function history(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $freshMinutes = max(1, (int) config('location_tracking.fresh_minutes', 10));
        $perPage = (int) ($request->input('per_page') ?: 50);

        $query = UserLocationHistory::query()
            ->where('user_id', $user->id)
            ->orderByDesc('captured_at')
            ->orderByDesc('id');

        if ($request->filled('from')) {
            $query->where('captured_at', '>=', Carbon::parse($request->input('from')));
        }
        if ($request->filled('to')) {
            $query->where('captured_at', '<=', Carbon::parse($request->input('to')));
        }

        $page = $query->paginate($perPage);

        $history = collect($page->items())->map(function (UserLocationHistory $row) {
            return [
                'id' => $row->id,
                'latitude' => (float) $row->latitude,
                'longitude' => (float) $row->longitude,
                'accuracy' => $row->accuracy,
                'captured_at' => $row->captured_at?->toIso8601String(),
                'recorded_at' => $row->recorded_at?->toIso8601String(),
                'address' => $row->address,
                'source' => $row->source,
                'session_started_at' => $row->session_started_at?->toIso8601String(),
                'precise' => LoginLocationUpdate::isPrecise($row->accuracy),
            ];
        });

        $online = $user->last_login_at
            && $user->last_login_at->gte(now()->subMinutes(15));

        return ApiResponse::success([
            'user' => [
                'id' => $user->id,
                'name' => User::resolveDisplayName($user),
                'email' => $user->email,
                'online' => (bool) $online,
            ],
            'latest' => $this->latestLocationPayload($user, $freshMinutes, online: (bool) $online),
            'fresh_minutes' => $freshMinutes,
            'background_tracking' => false,
            'history' => $history,
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ], 'Location history');
    }

    private function latestLocationPayload(User $user, int $freshMinutes, bool $online): array
    {
        $source = LoginLocationUpdate::source($user);
        $hasDevice = $source === 'device'
            && $user->last_login_lat !== null
            && $user->last_login_lng !== null;

        $capturedAt = $user->last_login_location_at;
        $isFresh = $hasDevice
            && $capturedAt instanceof Carbon
            && $capturedAt->gte(now()->subMinutes($freshMinutes));

        // Never label a point "live" unless the employee is also online by presence.
        $live = $online && $isFresh;

        return [
            'id' => $user->id,
            'name' => User::resolveDisplayName($user),
            'email' => $user->email,
            'role_name' => $user->roles->first()?->name,
            'online' => $online,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'has_device_location' => $hasDevice,
            'location_live' => $live,
            'location_status' => $live ? 'live' : ($hasDevice ? 'last_known' : 'unavailable'),
            'latitude' => $hasDevice ? (float) $user->last_login_lat : null,
            'longitude' => $hasDevice ? (float) $user->last_login_lng : null,
            'accuracy' => $hasDevice ? $user->last_login_accuracy : null,
            'captured_at' => $hasDevice ? $capturedAt?->toIso8601String() : null,
            'address' => $hasDevice ? $user->last_login_location : null,
            'source' => $hasDevice ? 'device' : null,
            'precise' => $hasDevice ? LoginLocationUpdate::isPrecise($user->last_login_accuracy) : false,
        ];
    }
}
