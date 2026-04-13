<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Helpers\MobileApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Mobile\MobileKanbanDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MobileKanbanController extends Controller
{
    public function show(Request $request, MobileKanbanDataService $service): JsonResponse
    {
        try {
            if (! auth()->user()->can('leads-list')) {
                return MobileApiResponse::error('You do not have permission to view leads', 403);
            }

            $userId = (int) auth()->id();
            $cacheKey = 'mobile.v1.kanban.'.$userId.'.'.hash('sha256', (string) json_encode($request->query->all()));
            $ttl = (int) config('mobile-api.kanban_cache_ttl', 60);

            $payload = Cache::remember($cacheKey, $ttl, function () use ($request, $service) {
                return $service->build($request);
            });

            return MobileApiResponse::success($payload, 'Mobile kanban loaded');
        } catch (\Throwable $e) {
            return MobileApiResponse::error($e->getMessage(), 500);
        }
    }
}
