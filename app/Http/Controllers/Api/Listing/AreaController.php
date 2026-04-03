<?php

namespace App\Http\Controllers\Api\Listing;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Http\Requests\Listing\AreaRequest;
use App\Http\Resources\Listing\AreaResource;
use App\Models\Area;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AreaController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:areas-list', ['only' => ['show', 'byType', 'children']]);
        $this->middleware('permission:areas-create', ['only' => ['store']]);
        $this->middleware('permission:areas-edit', ['only' => ['update']]);
        $this->middleware('permission:areas-delete', ['only' => ['destroy']]);
    }

    
    public function index(Request $request): JsonResponse
    {
        $cacheKey = 'areas_'.md5(serialize($request->all()));

        $resolver = function () use ($request) {
            $query = Area::withCount('child');

            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            if ($request->has('parent_id')) {
                $query->where('parent_id', $request->parent_id);
            }

            if ($request->has('with_parent')) {
                $query->with('parent');
            }

            if ($request->has('with_child')) {
                $query->with('child');
            }

            if ($request->has('has_listings')) {
                $query->where(function ($subQ) {
                    $subQ->whereHas('properties_complete')
                        ->orWhereHas('child.properties_complete')
                        ->orWhereHas('child.child.properties_complete')
                        ->orWhereHas('child.child.child.properties_complete');
                });
            }

            return $query->get();
        };

        try {
            $areas = Cache::supportsTags()
                ? Cache::tags(['areas'])->remember($cacheKey, 3600, $resolver)
                : Cache::remember($cacheKey, 3600, $resolver);

            return ApiResponse::success(
                AreaResource::collection($areas),
                'Areas retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Error fetching areas: '.$e->getMessage());

            return ApiResponse::success(
                AreaResource::collection($resolver()),
                'Areas retrieved successfully (cache fallback)'
            );
        }
    }

    
    public function store(AreaRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            
            // Create the area
            $area = Area::create([
                'name' => $request->name,
                'type' => $request->type,
                'parent_id' => $request->parent_id,
                'added_by' => auth()->user()->id,
                'latitude' => $request->input('latitude'),
                'longitude' => $request->input('longitude'),
            ]);

        // dd($request->boolean('create_project'),$request->input());
            $project = null;
            if ($request->boolean('create_project')) {
                $project = $this->createProjectFromArea($area);
                // dd($project);
                 Cache::flush();
            }

            $this->clearAreasCache();

            DB::commit();

            $responseData = [
                'area' => new AreaResource($area->loadCount('child'))
            ];

            if ($project) {
                $responseData['project'] = [
                    'id' => $project->id,
                    'name' => $project->title,
                    'message' => 'Project created successfully. You can edit it later from Projects section.'
                ];
                $responseData['message'] = 'Area and project created successfully';
            } else {
                $responseData['message'] = 'Area created successfully';
            }

            return ApiResponse::success($responseData, $responseData['message'], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create area: ' . $e->getMessage());
            return ApiResponse::error('Failed to create area: ' . $e->getMessage());
        }
    }

    
    public function show(Area $area): JsonResponse
    {
        try {
            $cacheKey = 'area_' . $area->id;
            
            $cachedArea = Cache::tags(['areas'])->remember($cacheKey, 3600, function () use ($area) {
                return $area->loadCount('child')->load('parent');
            });
            
            return ApiResponse::success(
                new AreaResource($cachedArea),
                'Area retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Error fetching area: ' . $e->getMessage());
            return ApiResponse::success(
                new AreaResource($area->loadCount('child')->load('parent')),
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
            
            DB::beginTransaction();
        
            $data = [
                'name' => $request->name,
                'type' => $request->type,
                'parent_id' => $request->parent_id,
            ];
            if ($request->exists('latitude')) {
                $data['latitude'] = $request->input('latitude');
            }
            if ($request->exists('longitude')) {
                $data['longitude'] = $request->input('longitude');
            }
            $area->update($data);
            $project=Project::where('area_id',$area->id)->first();
            if($project){
            $project->update(['title'=>$request->name]);
            }
            // Note: We don't create projects on update, only on create
            
            $this->clearAreaCache($area->id);
            $this->clearAreasCache();

            DB::commit();

            return ApiResponse::success(
                new AreaResource($area->loadCount('child')),
                'Area updated successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update area: ' . $e->getMessage());
            return ApiResponse::error('Failed to update area: ' . $e->getMessage());
        }
    }

    
    public function destroy(Area $area): JsonResponse
    {
        try {
            if ($area->child()->exists()) {
                return ApiResponse::error('Cannot delete area that has child areas. Please delete or move the child first.');
            }
            
            DB::beginTransaction();
            
            $areaId = $area->id;
            
            // Check if this area is used in any project
            $projectCount = Project::where('area_id', $areaId)->count();
            if ($projectCount > 0) {
                return ApiResponse::error('Cannot delete area that is associated with projects.');
            }
            
            $area->delete();

            $this->clearAreaCache($areaId);
            $this->clearAreasCache();

            DB::commit();

            return ApiResponse::success(null, 'Area deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete area: ' . $e->getMessage());
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
            Log::error("Error fetching {$type} areas: " . $e->getMessage());
            
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
            Log::error('Error fetching children: ' . $e->getMessage());
            
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
            Cache::tags(['areas'])->flush();
            
            // Also clear specific keys
            Cache::forget('areas_list_all');
            
            if (config('cache.default') === 'file') {
                Cache::forget('areas_list');
            }
        } catch (\Exception $e) {
            Log::warning('Cache clear error: ' . $e->getMessage());
        }
    }

  
    private function clearAreaCache(int $areaId): void
    {
        try {
            Cache::forget('area_' . $areaId);
            Cache::forget('area_children_' . $areaId);
        } catch (\Exception $e) {
            Log::warning('Area cache clear error: ' . $e->getMessage());
        }
    }

   
    public function clearCache(): JsonResponse
    {
        try {
            $this->clearAreasCache();
            return ApiResponse::success(null, 'Areas cache cleared successfully');
        } catch (\Exception $e) {
            Log::error('Failed to clear cache: ' . $e->getMessage());
            return ApiResponse::error('Failed to clear cache: ' . $e->getMessage());
        }
    }

    /**
     * Create a project from an area - مبسط جداً
     */
    private function createProjectFromArea(Area $area): ?Project
    {
        try {
            $project = Project::create([
                'title' => $area->name,
                'area_id' => $area->id,
                'added_by' => auth()->user()->id,
             
            ]);

            Log::info('Project created from area', [
                'area_id' => $area->id,
                'project_id' => $project->id,
                'user_id' => auth()->user()->id
            ]);

            return $project;
            
        } catch (\Exception $e) {
            Log::error('Failed to create project from area: ' . $e->getMessage(), [
                'area_id' => $area->id
            ]);
            throw $e; // Re-throw to trigger rollback
        }
    }

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
            
            $area = Area::where('id', $project->area_id)->first();
            
            if (!$area) {
                return response()->json([
                    'success' => false,
                    'message' => 'Area not found'
                ], 404);
            }
            
            // $allAreas = Area::whereIn('id', $area->child_ids ?? [])->get();
            // $matchedArea = null;
            
            // foreach ($allAreas as $areaItem) {
            //     if ($this->stringsMatch($project->title, $areaItem->name)) {
            //         $matchedArea = $areaItem;
            //         break;
            //     }
            // }
            
            // if (!$matchedArea) {
            //     $areas = Area::whereIn('id', [$project->area_id])->get();
            //     return response()->json([
            //         'success' => true,
            //         'data' => AreaResource::collection($areas),
            //         'message' => 'Project areas retrieved successfully'
            //     ]);
            // }
            
            // $childIds = is_array($matchedArea->child_ids) 
            //     ? $matchedArea->child_ids 
            //     : json_decode($matchedArea->child_ids, true);
            
            // $childIds = array_values(
            //     array_diff($childIds, [$matchedArea->id])
            // );
            
            // if (!empty($childIds)) {
            //     $areas = Area::whereIn('id', $childIds)->get();
            // } else {
            //     $areas = Area::where('id', $matchedArea->id)->get();
            // }
         $childIds=$area->child_ids;
          $areas = Area::whereIn('id', $childIds)->where('id','!=',$area->id)->withCount('child')->get();
          if($areas->count()==0){
              $childIds=[$area->id];
              $areas=Area::whereIn('id', $childIds)->withCount('child')->get();
          }
            return response()->json([
                'success' => true,
                'data' => AreaResource::collection($areas),
                'message' => 'Project areas retrieved successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to retrieve project areas: ' . $e->getMessage());
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