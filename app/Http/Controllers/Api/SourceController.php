<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Http\Requests\Lead\SourceRequest;
use App\Http\Resources\Lead\SourceResource;
use App\Models\Source;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SourceController extends Controller
{
    // Cache constants
    const CACHE_TTL = 3600; 
    const CACHE_PREFIX = 'sources_';

    public function __construct()
    {
        $this->middleware('permission:sources-list', ['only' => [ 'show']]);
        $this->middleware('permission:sources-create', ['only' => ['store']]);
        $this->middleware('permission:sources-edit', ['only' => ['update']]);
        $this->middleware('permission:sources-delete', ['only' => ['destroy']]);
    }

    /**
     * Get all sources 
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $cacheKey = self::CACHE_PREFIX . 'index_' . md5(serialize($request->all()));
            
            $Sources = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($request) {
                $query = Source::orderBy('id', 'desc')->get();
              
                
                return $query;
            });
            
            return ApiResponse::success(
                SourceResource::collection($Sources),
                'sources retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->fallbackIndex($request, $e);
        }
    }

    /**
     */
    public function store(SourceRequest $request): JsonResponse
    {
        try {
            $Source = Source::create($request->validated());

            $this->clearAllCache();

            return ApiResponse::success(
                new SourceResource($Source),
                'source created successfully', 
                201
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create source: ' . $e->getMessage());
        }
    }

    /**
     */
    public function show(Source $Source): JsonResponse
    {
        try {
            $cacheKey = self::CACHE_PREFIX . 'show_' . $Source->id;
            
            $cachedSource = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($Source) {
                return $Source;
            });
            
            return ApiResponse::success(
                new SourceResource($cachedSource),
                'source retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::success(
                new SourceResource($Source),
                'source retrieved successfully (cache fallback)'
            );
        }
    }

    /**
     */
    public function update(SourceRequest $request, Source $Source): JsonResponse
    {
        try {
            // Prevent circular reference
            if ($request->parent_id == $Source->id) {
                return ApiResponse::error('source cannot be its own parent');
            }

            $oldParentId = $Source->parent_id;
            $Source->update($request->validated());

            $this->clearAllCache();

            return ApiResponse::success(
                new SourceResource($Source),
                'source updated successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update source: ' . $e->getMessage());
        }
    }

    /**
     */
    public function destroy(Source $Source): JsonResponse
    {
        try {
            // Check if source has children
           
            
            $SourceId = $Source->id;
            $Source->delete();

            $this->clearAllCache();

            return ApiResponse::success(null, 'source deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete source: ' . $e->getMessage());
        }
    }

  

    /**
     */
    private function clearAllCache(): void
    {
        try {
            if (config('cache.default') === 'redis') {
                Cache::tags([self::CACHE_PREFIX . 'tag'])->flush();
            } else {
                $this->clearCacheByPattern(self::CACHE_PREFIX . '*');
            }
            
            \Log::info('sources cache cleared successfully');
        } catch (\Exception $e) {
            \Log::warning('sources cache clear error: ' . $e->getMessage());
        }
    }

    /**
     */
    private function clearCacheByPattern(string $pattern): void
    {
        try {
            if (config('cache.default') === 'redis') {
                $redis = Cache::getRedis();
                $cursor = 0;
                
                do {
                    list($cursor, $keys) = $redis->scan($cursor, 'MATCH', $pattern, 'COUNT', 100);
                    if (!empty($keys)) {
                        $redis->del($keys);
                    }
                } while ($cursor != 0);
            } else {
                Cache::flush();
            }
        } catch (\Exception $e) {
            \Log::warning('Pattern cache clear error: ' . $e->getMessage());
            Cache::flush();
        }
    }

    /**
     */
    private function fallbackIndex(Request $request, \Exception $e = null): JsonResponse
    {
        $query = Source::withCount('children');
        
        if ($request->has('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        } 
        
        if ($request->has('root_only') && $request->root_only == 1) {
            $query->whereNull('parent_id');
        }
        elseif ($request->has('non_root_only') && $request->non_root_only == 1) {
            $query->whereNotNull('parent_id');
        }
        
        if ($request->has('with_children')) {
            $query->with('children');
        }
        
        $Sources = $query->orderBy('parent_id', 'desc')->get();
        
        return ApiResponse::success(
            SourceResource::collection($Sources),
            'sources retrieved successfully (cache fallback)'
        );
    }

    /**
     */
    public function clearCacheManual(): JsonResponse
    {
        try {
            $this->clearAllCache();
            return ApiResponse::success(null, 'sources cache cleared successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to clear cache: ' . $e->getMessage());
        }
    }
}