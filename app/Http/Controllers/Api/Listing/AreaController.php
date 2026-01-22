<?php

namespace App\Http\Controllers\Api\Listing;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Http\Requests\Listing\AreaRequest;
use App\Http\Resources\Listing\AreaResource;
use App\Models\Area;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\Project;
class AreaController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:areas-list', ['only' => ['show']]);
        $this->middleware('permission:areas-create', ['only' => ['store']]);
        $this->middleware('permission:areas-edit', ['only' => ['update']]);
        $this->middleware('permission:areas-delete', ['only' => ['destroy']]);
    }

    
    public function index(Request $request): JsonResponse
    {
        try {
            $cacheKey = 'areas_' . md5(serialize($request->all()));
            
            $areas = Cache::remember($cacheKey, 3600, function () use ($request) {
                $query = Area::withCount('child');
                
                // Filter by type if provided
                if ($request->has('type')) {
                    $query->where('type', $request->type);
                }
                
                // Filter by parent_id if provided (null for root areas)
                if ($request->has('parent_id')) {
                    $query->where('parent_id', $request->parent_id);
                }
                
                // Load relationships based on query parameters
                if ($request->has('with_parent')) {
                    $query->with('parent');
                }
                
                if ($request->has('with_child')) {
                    $query->with('child');
                }
                
                return $query->get();
            });
            
            return ApiResponse::success(
                AreaResource::collection($areas),
                'Areas retrieved successfully'
            );
        } catch (\Exception $e) {
            $query = Area::withCount('child');
            
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }
            
            if ($request->has('parent_id')) {
                $query->where('parent_id', $request->parent_id);
            }
            
            $areas = $query->get();
            
            return ApiResponse::success(
                AreaResource::collection($areas),
                'Areas retrieved successfully (cache fallback)'
            );
        }
    }

    
    public function store(AreaRequest $request): JsonResponse
    {
        try {
            $area = Area::create($request->validated() + ['added_by' => auth()->user()->id]);

            $this->clearAreasCache();

            return ApiResponse::success(
                new AreaResource($area->loadCount('child')),
                'Area created successfully', 
                201
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create area: ' . $e->getMessage());
        }
    }

    
    public function show(Area $area): JsonResponse
    {
        try {
            $cacheKey = 'area_' . $area->id;
            
            $cachedArea = Cache::remember($cacheKey, 3600, function () use ($area) {
                return $area->loadCount('child');
            });
            
            return ApiResponse::success(
                new AreaResource($cachedArea),
                'Area retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::success(
                new AreaResource($area->loadCount('child')),
                'Area retrieved successfully (cache fallback)'
            );
        }
    }

   
    public function update(AreaRequest $request, Area $area): JsonResponse
    {
        try {
            // Prevent circular reference
            if ($request->parent_id == $area->id) {
                return ApiResponse::error('Area cannot be its own parent');
            }
            
            $area->update($request->validated());

            $this->clearAreaCache($area->id);
            $this->clearAreasCache();

            return ApiResponse::success(
                new AreaResource($area->loadCount('child')),
                'Area updated successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update area: ' . $e->getMessage());
        }
    }

    
    public function destroy(Area $area): JsonResponse
    {
        try {
            if ($area->child()->exists()) {
                return ApiResponse::error('Cannot delete area that has child areas. Please delete or move the child first.');
            }
            
            $areaId = $area->id;
            $area->delete();

            $this->clearAreaCache($areaId);
            $this->clearAreasCache();

            return ApiResponse::success(null, 'Area deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete area: ' . $e->getMessage());
        }
    }

    
    public function byType(string $type): JsonResponse
    {
        try {
            $cacheKey = 'areas_type_' . $type;
            
            $areas = Cache::remember($cacheKey, 3600, function () use ($type) {
                return Area::withCount('child')
                    ->where('type', $type)
                    ->orderBy('name')
                    ->get();
            });
            
            return ApiResponse::success(
                AreaResource::collection($areas),
                "{$type} areas retrieved successfully"
            );
        } catch (\Exception $e) {
            // Fallback بدون cache
            $areas = Area::withCount('child')
                ->where('type', $type)
                ->orderBy('name')
                ->get();
                
            return ApiResponse::success(
                AreaResource::collection($areas),
                "{$type} areas retrieved successfully (cache fallback)"
            );
        }
    }

    public function children(Area $area): JsonResponse
    {
        try {
            $cacheKey = 'area_children_' . $area->id;
            
            $children = Cache::remember($cacheKey, 3600, function () use ($area) {
                return $area->child()->withCount('child')->orderBy('name')->get();
            });
            
            return ApiResponse::success(
                AreaResource::collection($children),
                'Child areas retrieved successfully'
            );
        } catch (\Exception $e) {
            // Fallback بدون cache
            $children = $area->child()->withCount('child')->orderBy('name')->get();
            
            return ApiResponse::success(
                AreaResource::collection($children),
                'Child areas retrieved successfully (cache fallback)'
            );
        }
    }

   
    private function clearAreasCache(): void
    {
        try {
            Cache::forget('areas_list_all');
            
            $prefix = config('cache.prefix');
            $keysToForget = [
                'areas_*',
                'areas_type_*',
                'area_*',
                'area_children_*'
            ];
            
            if (config('cache.default') === 'file') {
                Cache::forget('areas_list');
            }
        } catch (\Exception $e) {
            \Log::warning('Cache clear error: ' . $e->getMessage());
        }
    }

  
    private function clearAreaCache(int $areaId): void
    {
        try {
            Cache::forget('area_' . $areaId);
            Cache::forget('area_children_' . $areaId);
        } catch (\Exception $e) {
            \Log::warning('Area cache clear error: ' . $e->getMessage());
        }
    }

   
    public function clearCache(): JsonResponse
    {
        try {
            $this->clearAreasCache();
            return ApiResponse::success(null, 'Areas cache cleared successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to clear cache: ' . $e->getMessage());
        }
    }
    //  public function getProjectAreas( $projectId)
    // {
    //     try {
    //         $project=Project::find($projectId);
    //       $areass = Area::where('id', $project->area_id)->first();
    //       $areas = Area::whereIn('id', $areass->child_ids)->get();
            
    //         return response()->json([
    //             'success' => true,
    //             'data' => AreaResource::collection($areas),
    //             'message' => 'Project areas retrieved successfully'
    //         ]);
            
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to retrieve project areas',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }
    public function getProjectAreas($projectId)
{
    try {
        $project = Project::find($projectId);
        
        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => 'Project not found'
            ], 404);
        }
        $area=Area::where('id',$project->area_id)->first();
        $allAreas = Area::whereIn('id',$area->child_ids)->get();
        $matchedArea = null;
        
        foreach ($allAreas as $area) {
            if ($this->stringsMatch($project->title, $area->name)) {
                $matchedArea = $area;
                break;
            }
        }
        
        if (!$matchedArea) {
          $areas = Area::whereIn('id', [$project->area_id])->get();
          return response()->json([
            'success' => true,
            'data' => AreaResource::collection($areas),
            'message' => 'Project areas retrieved successfully'
        ]);
        }
        
        $childIds = is_array($matchedArea->child_ids) 
            ? $matchedArea->child_ids 
            : json_decode($matchedArea->child_ids, true);
        
    $childIds = array_values(
    array_diff($childIds, [$matchedArea->id])
);
        
        if (!empty($childIds)) {
            $areas = Area::whereIn('id', $childIds)->get();
        } else {
            $areas = Area::where('id', $matchedArea->id)->get();
        }

        
        return response()->json([
            'success' => true,
            'data' => AreaResource::collection($areas),
            'message' => 'Project areas retrieved successfully'
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to retrieve project areas',
            'error' => $e->getMessage()
        ], 500);
    }
}

private function stringsMatch($str1, $str2)
{
    $cleanStr1 = $this->normalizeString($str1);
    $cleanStr2 = $this->normalizeString($str2);
    
    return $cleanStr1 === $cleanStr2 || 
           strpos($cleanStr1, $cleanStr2) !== false || 
           strpos($cleanStr2, $cleanStr1) !== false;
}

private function normalizeString($string)
{
    $string = strtolower($string);
    $string = preg_replace("/[^a-z0-9]/", '', $string);
    return $string;
}
}