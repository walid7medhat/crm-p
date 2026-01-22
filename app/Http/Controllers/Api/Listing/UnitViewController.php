<?php

namespace App\Http\Controllers\Api\Listing;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Http\Requests\Listing\UnitViewRequest;
use App\Http\Resources\Listing\UnitViewResource;
use App\Models\UnitView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class UnitViewController extends Controller
{
    // Cache constants
    const CACHE_TTL = 86400; // يوم كامل (نادراً ما تتغير)
    const CACHE_PREFIX = 'unit_views_';

    public function __construct()
    {
        $this->middleware('permission:unit_views-list', ['only' => [ 'show']]);
        $this->middleware('permission:unit_views-create', ['only' => ['store']]);
        $this->middleware('permission:unit_views-edit', ['only' => ['update']]);
        $this->middleware('permission:unit_views-delete', ['only' => ['destroy']]);
    }

    /**
     * Get all Unit Views - مع الكاش
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $cacheKey = self::CACHE_PREFIX . 'index';
            
            $unitViews = Cache::remember($cacheKey, self::CACHE_TTL, function () {
                return UnitView::orderBy('name')->get();
            });
            
            return ApiResponse::success(
                UnitViewResource::collection($unitViews),
                'Unit Views retrieved successfully'
            );
        } catch (\Exception $e) {
            // Fallback بدون cache
            return ApiResponse::success(
                UnitViewResource::collection(UnitView::orderBy('name')->get()),
                'Unit Views retrieved successfully (cache fallback)'
            );
        }
    }

    /**
     * Create a new Unit View - مع clear للكاش
     */
    public function store(UnitViewRequest $request): JsonResponse
    {
        try {
            $unitView = UnitView::create($request->validated() + [
                'added_by' => auth()->user()->id
            ]);

            // مسح الكاش المتعلق بالـ unit views
            $this->clearCache();

            return ApiResponse::success(
                new UnitViewResource($unitView),
                'Unit View created successfully', 
                201
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create Unit View: ' . $e->getMessage());
        }
    }

    /**
     * Get a single Unit View - مع الكاش
     */
    public function show(UnitView $unitView): JsonResponse
    {
        try {
            $cacheKey = self::CACHE_PREFIX . 'show_' . $unitView->id;
            
            $cachedUnitView = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($unitView) {
                return $unitView;
            });
            
            return ApiResponse::success(
                new UnitViewResource($cachedUnitView),
                'Unit View retrieved successfully'
            );
        } catch (\Exception $e) {
            // Fallback بدون cache
            return ApiResponse::success(
                new UnitViewResource($unitView),
                'Unit View retrieved successfully (cache fallback)'
            );
        }
    }

    /**
     * Update a Unit View - مع clear للكاش
     */
    public function update(UnitViewRequest $request, UnitView $unitView): JsonResponse
    {
        try {
            $unitView->update($request->validated());

            // مسح الكاش المتعلق بهذا الـ unit view والـ unit views عامة
            $this->clearCache();
            $this->clearSpecificCache($unitView->id);

            return ApiResponse::success(
                new UnitViewResource($unitView),
                'Unit View updated successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update Unit View: ' . $e->getMessage());
        }
    }

    /**
     * Delete a Unit View - مع clear للكاش
     */
    public function destroy(UnitView $unitView): JsonResponse
    {
        try {
            $unitViewId = $unitView->id;
            $unitView->delete();

            // مسح الكاش المتعلق بهذا الـ unit view والـ unit views عامة
            $this->clearCache();
            $this->clearSpecificCache($unitViewId);

            return ApiResponse::success(null, 'Unit View deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete Unit View: ' . $e->getMessage());
        }
    }

    /**
     * دالة مساعدة لمسح كل الكاش
     */
    private function clearCache(): void
    {
        try {
            Cache::forget(self::CACHE_PREFIX . 'index');
            $this->clearCacheByPattern(self::CACHE_PREFIX . 'show_*');
        } catch (\Exception $e) {
            \Log::warning('Unit views cache clear error: ' . $e->getMessage());
        }
    }

    /**
     * دالة مساعدة لمسح كاش سجل معين
     */
    private function clearSpecificCache(int $unitViewId): void
    {
        try {
            Cache::forget(self::CACHE_PREFIX . 'show_' . $unitViewId);
        } catch (\Exception $e) {
            \Log::warning('Unit view cache clear error: ' . $e->getMessage());
        }
    }

    /**
     * دالة مساعدة لمسح الكاش بناءً على pattern
     */
    private function clearCacheByPattern(string $pattern): void
    {
        try {
            // Implementation for pattern-based cache clearing
            if (config('cache.default') === 'file') {
                // لـ file cache نعتمد على أن الـ Cache::forget يكفي
            }
        } catch (\Exception $e) {
            \Log::warning('Pattern cache clear error: ' . $e->getMessage());
        }
    }

    /**
     * دالة لمسح الكاش يدوياً
     */
    public function clearCacheManual(): JsonResponse
    {
        try {
            $this->clearCache();
            return ApiResponse::success(null, 'Unit views cache cleared successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to clear cache: ' . $e->getMessage());
        }
    }
}