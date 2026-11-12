<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\LiveWatchEvent;
use App\Models\User;
use App\Services\LiveWatch\LiveWatchEventRecorder;
use App\Services\LiveWatch\LiveWatchPresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiveWatchController extends Controller
{
    public function __construct(
        private LiveWatchPresenceService $presence,
        private LiveWatchEventRecorder $recorder,
    ) {
    }

    public function settings(): JsonResponse
    {
        return ApiResponse::success($this->presence->presenceSnapshot());
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'monitored_user_id' => 'nullable|integer|exists:users,id',
            'heartbeat_interval_seconds' => 'nullable|integer|min:15|max:120',
            'offline_timeout_seconds' => 'nullable|integer|min:45|max:600',
        ]);

        $userId = array_key_exists('monitored_user_id', $data)
            ? ($data['monitored_user_id'] !== null ? (int) $data['monitored_user_id'] : null)
            : $this->presence->settings()->monitored_user_id;

        if ($userId !== null) {
            $target = User::query()->find($userId);
            if (! $target || $target->status === 'blocked') {
                return ApiResponse::error('Selected user is not valid for monitoring.', 422);
            }
        }

        $this->presence->setMonitoredUser(
            $userId,
            $request->user(),
            $data['heartbeat_interval_seconds'] ?? null,
            $data['offline_timeout_seconds'] ?? null,
        );

        return ApiResponse::success($this->presence->presenceSnapshot(), 'Live Watch settings updated');
    }

    public function presence(): JsonResponse
    {
        return ApiResponse::success($this->presence->presenceSnapshot());
    }

    public function users(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('search', ''));
        $query = User::query()
            ->select(['id', 'name', 'email', 'status'])
            ->where('status', '!=', 'blocked')
            ->orderBy('name')
            ->limit(40);

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', '%'.$q.'%')
                    ->orWhere('email', 'like', '%'.$q.'%')
                    ->orWhere('id', $q);
            });
        }

        return ApiResponse::success($query->get());
    }

    public function timeline(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'module' => 'nullable|string|max:64',
            'event_type' => 'nullable|string|max:64',
            'event_kind' => 'nullable|in:navigation,business',
            'per_page' => 'nullable|integer|min:10|max:100',
        ]);

        $settings = $this->presence->settings();
        $monitoredId = (int) ($settings->monitored_user_id ?? 0);
        if ($monitoredId === 0) {
            return ApiResponse::success([
                'items' => [],
                'meta' => ['total' => 0, 'monitored_user_id' => null],
            ]);
        }

        $query = LiveWatchEvent::query()
            ->where('user_id', $monitoredId)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        if (! empty($data['date_from'])) {
            $query->where('occurred_at', '>=', $data['date_from']);
        }
        if (! empty($data['date_to'])) {
            $query->where('occurred_at', '<=', $data['date_to'].' 23:59:59');
        }
        if (! empty($data['module'])) {
            $query->where('module', $data['module']);
        }
        if (! empty($data['event_type'])) {
            $query->where('event_type', $data['event_type']);
        }
        if (! empty($data['event_kind'])) {
            $query->where('event_kind', $data['event_kind']);
        }

        $perPage = (int) ($data['per_page'] ?? 50);
        $page = $query->paginate($perPage);

        return ApiResponse::success([
            'items' => $page->items(),
            'meta' => [
                'total' => $page->total(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'monitored_user_id' => $monitoredId,
            ],
        ]);
    }
}
