<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Monitoring\RequestMonitorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RequestMonitorController extends Controller
{
    private const WINDOWS = [1, 5, 15, 60];

    public function index(Request $request, RequestMonitorService $service): JsonResponse
    {
        if (! $request->user()?->hasRole('super_admin')) {
            return ApiResponse::error('Forbidden.', 403);
        }

        $minutes = (int) $request->get('minutes', 5);
        if (! in_array($minutes, self::WINDOWS, true)) {
            $minutes = 5;
        }

        try {
            $ttl = max(0, (int) config('request_monitor.cache_seconds', 15));
            $data = $ttl > 0
                ? Cache::remember("request-monitor:{$minutes}", $ttl, fn () => $service->snapshot($minutes))
                : $service->snapshot($minutes);

            return ApiResponse::success($data, 'Request monitor snapshot');
        } catch (\Throwable $e) {
            Log::warning('request monitor failed', ['error' => $e->getMessage()]);

            return ApiResponse::error('Could not read the server logs: '.$e->getMessage(), 500);
        }
    }
}
