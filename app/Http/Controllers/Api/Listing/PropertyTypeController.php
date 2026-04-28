<?php

namespace App\Http\Controllers\Api\Listing;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Http\Requests\Listing\PropertyTypeRequest;
use App\Http\Resources\Listing\PropertyTypeResource;
use App\Models\PropertyType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PropertyTypeController extends Controller
{
    // Cache constants
    const CACHE_TTL = 3600; 
    const CACHE_PREFIX = 'property_types_';

    public function __construct()
    {
        $this->middleware('permission:property_types-list', ['only' => [ 'show']]);
        $this->middleware('permission:property_types-create', ['only' => ['store']]);
        $this->middleware('permission:property_types-edit', ['only' => ['update']]);
        $this->middleware('permission:property_types-delete', ['only' => ['destroy']]);
    }

    /**
     * Get all property types 
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $cacheKey = self::CACHE_PREFIX . 'index_' . md5(serialize($request->all()));
            
            $propertyTypes = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($request) {
                $query = PropertyType::withCount('children');
                
                // Filter by parent_id if provided (null for root types)
                if ($request->has('parent_id')) {
                    $query->where('parent_id', $request->parent_id);
                } 
                
                // Filter for root types only
                if ($request->has('root_only') && $request->root_only == 1) {
                    $query->whereNull('parent_id');
                }
                // Filter for non-root types only
                elseif ($request->has('non_root_only') && $request->non_root_only == 1) {
                    $query->whereNotNull('parent_id');
                }
                
                // Load relationships based on query parameters
                if ($request->has('with_children')) {
                    $query->with('children');
                }
                if ($request->has('has_listings')) {
                    $query->whereHas('listings');
                }
                if($request->has('resdintial')){
                     $query->where('parent_id',10)->orWhere('id',31);
                }
                
                return $query->orderBy('parent_id', 'desc')->get();
            });
            
            return ApiResponse::success(
                PropertyTypeResource::collection($propertyTypes),
                'Property types retrieved successfully'
            );
        } catch (\Exception $e) {
            // Fallback بدون cache
            return $this->fallbackIndex($request, $e);
        }
    }

    /**
     * Create a new property type - مع clear للكاش
     */
    public function store(PropertyTypeRequest $request): JsonResponse
    {
        try {
            $propertyType = PropertyType::create($request->validated() + [
                'added_by' => auth()->user()->id
            ]);

            $this->clearAllCache();

            return ApiResponse::success(
                new PropertyTypeResource($propertyType->loadCount('children')),
                'Property type created successfully', 
                201
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create property type: ' . $e->getMessage());
        }
    }

    /**
     */
    public function show(PropertyType $propertyType): JsonResponse
    {
        try {
            $cacheKey = self::CACHE_PREFIX . 'show_' . $propertyType->id;
            
            $cachedPropertyType = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($propertyType) {
                return $propertyType->loadCount('children');
            });
            
            return ApiResponse::success(
                new PropertyTypeResource($cachedPropertyType),
                'Property type retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::success(
                new PropertyTypeResource($propertyType->loadCount('children')),
                'Property type retrieved successfully (cache fallback)'
            );
        }
    }

    /**
     */
    public function update(PropertyTypeRequest $request, PropertyType $propertyType): JsonResponse
    {
        try {
            // Prevent circular reference
            if ($request->parent_id == $propertyType->id) {
                return ApiResponse::error('Property type cannot be its own parent');
            }

            $oldParentId = $propertyType->parent_id;
            $propertyType->update($request->validated());

            $this->clearAllCache();

            return ApiResponse::success(
                new PropertyTypeResource($propertyType->loadCount('children')),
                'Property type updated successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update property type: ' . $e->getMessage());
        }
    }

    /**
     */
    public function destroy(PropertyType $propertyType): JsonResponse
    {
        try {
            // Check if property type has children
            if ($propertyType->children()->exists()) {
                return ApiResponse::error('Cannot delete property type that has child types. Please delete or move the children first.');
            }
            
            $propertyTypeId = $propertyType->id;
            $parentId = $propertyType->parent_id;
            $propertyType->delete();

            $this->clearAllCache();

            return ApiResponse::success(null, 'Property type deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete property type: ' . $e->getMessage());
        }
    }

    /**
     */
    public function children(PropertyType $propertyType): JsonResponse
    {
        try {
            $cacheKey = self::CACHE_PREFIX . 'children_' . $propertyType->id;
            
            $children = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($propertyType) {
                return $propertyType->children()->withCount('children')->orderBy('name')->get();
            });
            
            return ApiResponse::success(
                PropertyTypeResource::collection($children),
                'Child property types retrieved successfully'
            );
        } catch (\Exception $e) {
            $children = $propertyType->children()->withCount('children')->orderBy('name')->get();
            
            return ApiResponse::success(
                PropertyTypeResource::collection($children),
                'Child property types retrieved successfully (cache fallback)'
            );
        }
    }

    /**
     */
    public function roots(): JsonResponse
    {
        try {
            $cacheKey = self::CACHE_PREFIX . 'roots';
            
            $roots = Cache::remember($cacheKey, self::CACHE_TTL, function () {
                return PropertyType::withCount('children')
                    ->whereNull('parent_id')
                    ->orderBy('name')
                    ->get();
            });
            
            return ApiResponse::success(
                PropertyTypeResource::collection($roots),
                'Root property types retrieved successfully'
            );
        } catch (\Exception $e) {
            $roots = PropertyType::withCount('children')
                ->whereNull('parent_id')
                ->orderBy('name')
                ->get();
                
            return ApiResponse::success(
                PropertyTypeResource::collection($roots),
                'Root property types retrieved successfully (cache fallback)'
            );
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
            
            \Log::info('Property types cache cleared successfully');
        } catch (\Exception $e) {
            \Log::warning('Property types cache clear error: ' . $e->getMessage());
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
        $query = PropertyType::withCount('children');
        
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
        
        $propertyTypes = $query->orderBy('parent_id', 'desc')->get();
        
        return ApiResponse::success(
            PropertyTypeResource::collection($propertyTypes),
            'Property types retrieved successfully (cache fallback)'
        );
    }

    /**
     */
    public function clearCacheManual(): JsonResponse
    {
        try {
            $this->clearAllCache();
            return ApiResponse::success(null, 'Property types cache cleared successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to clear cache: ' . $e->getMessage());
        }
    }
}