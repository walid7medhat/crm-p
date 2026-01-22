<?php

namespace App\Http\Controllers\Api\Listing;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Http\Requests\Listing\LayoutTypeRequest;
use App\Http\Resources\Listing\LayoutTypeResource;
use App\Models\LayoutType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LayoutTypeController extends Controller
{
    // Cache constants
    const CACHE_TTL = 86400;
    const CACHE_PREFIX = 'layout_types_';

    public function __construct()
    {
        $this->middleware('permission:layout_types-list', ['only' => [ 'show']]);
        $this->middleware('permission:layout_types-create', ['only' => ['store']]);
        $this->middleware('permission:layout_types-edit', ['only' => ['update']]);
        $this->middleware('permission:layout_types-delete', ['only' => ['destroy']]);
    }

    /**
     * Get all Layout Types - مع الكاش
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $cacheKey = self::CACHE_PREFIX . 'index';
            
            $layoutTypes = Cache::remember($cacheKey, self::CACHE_TTL, function () {
                return LayoutType::orderBy('name')->get();
            });
            
            return ApiResponse::success(
                LayoutTypeResource::collection($layoutTypes),
                'Layout Types retrieved successfully'
            );
        } catch (\Exception $e) {
            // Fallback بدون cache
            return ApiResponse::success(
                LayoutTypeResource::collection(LayoutType::orderBy('name')->get()),
                'Layout Types retrieved successfully (cache fallback)'
            );
        }
    }

    /**
     * Create a new Layout Type -
     */
    public function store(LayoutTypeRequest $request): JsonResponse
    {
        try {
            $layoutType = LayoutType::create($request->validated() + [
                'added_by' => auth()->user()->id
            ]);

            // مسح الكاش المتعلق بالـ layout types
            $this->clearCache();

            return ApiResponse::success(
                new LayoutTypeResource($layoutType),
                'Layout Type created successfully', 
                201
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create Layout Type: ' . $e->getMessage());
        }
    }

    /**
     * Get a single Layout Type - 
     */
    public function show(LayoutType $layoutType): JsonResponse
    {
        try {
            $cacheKey = self::CACHE_PREFIX . 'show_' . $layoutType->id;
            
            $cachedLayoutType = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($layoutType) {
                return $layoutType;
            });
            
            return ApiResponse::success(
                new LayoutTypeResource($cachedLayoutType),
                'Layout Type retrieved successfully'
            );
        } catch (\Exception $e) {
            // Fallback بدون cache
            return ApiResponse::success(
                new LayoutTypeResource($layoutType),
                'Layout Type retrieved successfully (cache fallback)'
            );
        }
    }

    /**
     * Update a Layout Type - 
     */
    public function update(LayoutTypeRequest $request, LayoutType $layoutType): JsonResponse
    {
        try {
            $layoutType->update($request->validated());

            // مسح الكاش المتعلق بهذا الـ layout type والـ layout types عامة
            $this->clearCache();
            $this->clearSpecificCache($layoutType->id);

            return ApiResponse::success(
                new LayoutTypeResource($layoutType),
                'Layout Type updated successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update Layout Type: ' . $e->getMessage());
        }
    }

    /**
     * Delete a Layout Type - 
     */
    public function destroy(LayoutType $layoutType): JsonResponse
    {
        try {
            $layoutTypeId = $layoutType->id;
            $layoutType->delete();

            $this->clearCache();
            $this->clearSpecificCache($layoutTypeId);

            return ApiResponse::success(null, 'Layout Type deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete Layout Type: ' . $e->getMessage());
        }
    }

   
    private function clearCache(): void
    {
        try {
            Cache::forget(self::CACHE_PREFIX . 'index');
            $this->clearCacheByPattern(self::CACHE_PREFIX . 'show_*');
        } catch (\Exception $e) {
            \Log::warning('Layout types cache clear error: ' . $e->getMessage());
        }
    }

    
    private function clearSpecificCache(int $layoutTypeId): void
    {
        try {
            Cache::forget(self::CACHE_PREFIX . 'show_' . $layoutTypeId);
        } catch (\Exception $e) {
            \Log::warning('Layout type cache clear error: ' . $e->getMessage());
        }
    }

   
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

  
    public function clearCacheManual(): JsonResponse
    {
        try {
            $this->clearCache();
            return ApiResponse::success(null, 'Layout types cache cleared successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to clear cache: ' . $e->getMessage());
        }
    }
}