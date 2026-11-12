<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\LiveWatch\LiveWatchEventRecorder;
use App\Services\LiveWatch\LiveWatchPresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class LiveWatchClientController extends Controller
{
    public function __construct(
        private LiveWatchPresenceService $presence,
        private LiveWatchEventRecorder $recorder,
    ) {
    }

    public function clientConfig(Request $request): JsonResponse
    {
        return ApiResponse::success($this->presence->clientConfig($request->user()));
    }

    public function heartbeat(Request $request): JsonResponse
    {
        if ($this->isImpersonated($request)) {
            return ApiResponse::success([
                'tracked' => false,
                'is_online' => false,
                'ignored' => 'impersonated',
            ]);
        }

        $data = $request->validate([
            'session_id' => 'required|string|min:8|max:64',
            'device_label' => 'nullable|string|max:64',
        ]);

        $result = $this->presence->heartbeat(
            $request->user(),
            $data['session_id'],
            $data['device_label'] ?? null,
        );

        return ApiResponse::success($result);
    }

    public function leave(Request $request): JsonResponse
    {
        if ($this->isImpersonated($request)) {
            return ApiResponse::success(['tracked' => false]);
        }

        $data = $request->validate([
            'session_id' => 'required|string|min:8|max:64',
        ]);

        return ApiResponse::success(
            $this->presence->leave($request->user(), $data['session_id'])
        );
    }

    public function navigation(Request $request): JsonResponse
    {
        if ($this->isImpersonated($request)) {
            return ApiResponse::success(['recorded' => false, 'ignored' => 'impersonated']);
        }

        $data = $request->validate([
            'session_id' => 'nullable|string|max:64',
            'client_event_id' => 'nullable|string|max:64',
            'action' => 'required|string|in:enter,leave,open,close,login,logout',
            'event_type' => 'required|string|max:64',
            'module' => 'nullable|string|max:64',
            'entity_type' => 'nullable|string|max:64',
            'entity_id' => 'nullable|integer|min:1',
            'path' => 'nullable|string|max:120',
            'occurred_at' => 'nullable|date',
        ]);

        // Strip any query string if a client mistakenly sends one.
        $path = isset($data['path']) ? Str::before((string) $data['path'], '?') : null;
        $path = $path ? Str::limit($path, 120, '') : null;

        $event = $this->recorder->record([
            'user_id' => $request->user()->id,
            'event_kind' => 'navigation',
            'event_type' => $data['event_type'],
            'module' => $data['module'] ?? null,
            'action' => $data['action'],
            'entity_type' => $data['entity_type'] ?? null,
            'entity_id' => $data['entity_id'] ?? null,
            'session_id' => $data['session_id'] ?? null,
            'client_event_id' => $data['client_event_id'] ?? null,
            'occurred_at' => $data['occurred_at'] ?? now(),
            'metadata' => array_filter([
                'path' => $path,
            ], fn ($v) => $v !== null && $v !== ''),
        ]);

        return ApiResponse::success([
            'recorded' => $event !== null,
            'id' => $event?->id,
        ]);
    }

    private function isImpersonated(Request $request): bool
    {
        if ($request->attributes->get('impersonated')) {
            return true;
        }

        try {
            return (bool) JWTAuth::parseToken()->getPayload()->get('impersonated_by');
        } catch (\Throwable) {
            return false;
        }
    }
}
