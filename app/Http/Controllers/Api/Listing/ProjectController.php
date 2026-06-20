<?php

namespace App\Http\Controllers\Api\Listing;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Helpers\ImageHelper;
use App\Http\Requests\Listing\ProjectRequest;
use App\Http\Resources\Listing\ProjectResource;
use App\Models\Project;
use App\Models\ProjectImage;
use App\Models\Developer;
use App\Models\Feature;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\FloorPlanImage;
class ProjectController extends Controller
{
    // Cache constants
    const CACHE_TTL = 1800; 
    const CACHE_PREFIX = 'projects_';
    const CACHE_TAG = 'projects';
    const PAGINATION_CACHE_TTL = 900; // 15 Min

    public function __construct()
    {
        $this->middleware('permission:projects-list', ['only' => [ 'show']]);
        $this->middleware('permission:projects-create', ['only' => ['store']]);
        $this->middleware('permission:projects-edit', ['only' => ['update']]);
        $this->middleware('permission:projects-delete', ['only' => ['destroy']]);
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $filtersHash = md5(serialize($request->all()));
            $cacheKey = self::CACHE_PREFIX . 'index_' . Auth::id() . '_' . $filtersHash;

            if (method_exists(Cache::getStore(), 'tags')) {
                $result = Cache::tags([self::CACHE_TAG])->remember(
                    $cacheKey,
                    self::CACHE_TTL,
                    fn () => $this->getProjectsData($request)
                );
            } else {
                $result = Cache::remember(
                    $cacheKey,
                    self::CACHE_TTL,
                    fn () => $this->getProjectsData($request)
                );
            }

            return ApiResponse::success(
                ProjectResource::collection($result['projects']),
                'Projects retrieved successfully',
                200,
                $result['pagination']
            );

        } catch (\Exception $e) {
            Log::error('Projects index error: ' . $e->getMessage());
            return $this->fallbackIndex($request, $e);
        }
    }

 
   private function getProjectsData(Request $request): array
{
    $query = Project::with(['developer', 'area', 'images', 'mainImage']);
    
    // Filters
    if ($request->has('search')) {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhereHas('developer', function ($q) use ($search) {
                  $q->where('name', 'like', "%{$search}%");
              })->orWhereHas('area', function($q) use ($search) {
                  $q->where('name', 'like', "%{$search}%");
              });
        });
    }
    
    if ($request->has('status')) {
        $query->where('status', $request->status);
    }
    
    if ($request->has('developer_id')) {
        $query->where('developer_id', $request->developer_id);
    }
    
    if ($request->has('min_price')) {
        $query->where('from_price', '>=', $request->min_price);
    }
    
    if ($request->has('max_price')) {
        $query->where('to_price', '<=', $request->max_price);
    }

    // Sorting
    $sort = $request->get('sort', 'created_at_desc');
    switch ($sort) {
        case 'title_asc': $query->orderBy('title', 'asc'); break;
        case 'title_desc': $query->orderBy('title', 'desc'); break;
        case 'price_asc': $query->orderBy('from_price', 'asc'); break;
        case 'price_desc': $query->orderBy('from_price', 'desc'); break;
        default: $query->orderBy('created_at', 'desc'); break;
    }

    // Get ALL projects without pagination
    // if ($request->has('get_all') && $request->get('get_all') === 'true') {
        $projects = $query->get();
        
        return [
            'projects' => $projects,
            'pagination' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $projects->count(),
                'total' => $projects->count(),
            ]
        ];
    // }

    // Default: Use pagination
    $perPage = $request->get('per_page', 15);
    $projects = $query->paginate($perPage);
    
    return [
        'projects' => $projects,
        'pagination' => $projects->toArray()
    ];
}
    /**
     * Create a new Project
     */
    public function store(ProjectRequest $request): JsonResponse
    {
        Log::info('Starting project creation');
        
        try {
            DB::beginTransaction();

            $data = $request->validated();
            $data['added_by'] = Auth::id();
            
            Log::info('Validated data', array_keys($data));

            unset($data['main_image']);
            unset($data['floor_plan_images']);
            Log::info('Creating project with data', $data);

            // Create project
            $project = Project::create($data);

            Log::info('Project created', ['id' => $project->id]);

            // Attach features (Many-to-Many relationship)
            if ($request->has('features') && is_array($request->features)) {
                $project->features()->attach($request->features);
                Log::info('Features attached', ['count' => count($request->features)]);
            }

            // Handle main image upload with compression
            if ($request->hasFile('main_image')) {
                Log::info('Processing main image upload');
                
                $mainImageFile = $request->file('main_image');
                $compressionResult = ImageHelper::compressAndConvertToWebP(
                    $mainImageFile, 
                    "projects/{$project->id}",
                    ['quality' => 90, 'max_width' => 1920]
                );
                
                // Set all images as non-main first
                ProjectImage::where('project_id', $project->id)->update(['is_main' => false]);
                
                // Create main image
                $project->images()->create([
                    'image_path' => $compressionResult['path'],
                    'is_main' => true,
                    'sort_order' => 0
                ]);
                
                Log::info('Main image uploaded', ['path' => $compressionResult['path']]);
            }


            // Handle additional images upload
                if ($request->hasFile('images')) {
                    Log::info('Processing additional images', ['count' => count($request->file('images'))]);
                    
                    $maxOrder = $project->images()->max('sort_order') ?? 0;
                    
                    foreach ($request->file('images') as $index => $imageFile) {
                        $compressionResult = ImageHelper::compressAndConvertToWebP(
                            $imageFile, 
                            "projects/{$project->id}/gallery",
                            ['quality' => 85, 'max_width' => 1920]
                        );
                        
                        $project->images()->create([
                            'image_path' => $compressionResult['path'],
                            'is_main' => false,
                            'sort_order' => $maxOrder + $index + 1
                        ]);
                    }
                }
             // Handle floor plan images upload
          if ($request->has('floor_plan_images') && count($request->floor_plan_images)>0) {
                Log::info('Processing floor plan images upload', [
                    'count' => count($request->file('floor_plan_images'))
                ]);
                
                  foreach ($request->floor_plan_images as $index => $data) {
                    $imageFile = $data['file'];
                    $name = $data['name'] ?? "Floor Plan " . ($index + 1);
                    $compressionResult = ImageHelper::compressAndConvertToWebP(
                        $imageFile, 
                        "projects/{$project->id}/floor-plans",
                        ['quality' => 85, 'max_width' => 1920]
                    );
                    
                    $project->floorPlanImages()->create([
                        'image_path' => $compressionResult['path'],
                        'sort_order' => $index,
                        'name'=>$name
                    ]);
                }
                
                Log::info('Floor plan images uploaded', [
                    'count' => count($request->file('floor_plan_images'))
                ]);
            }


            DB::commit();

            // Load relationships
            $project->load(['developer', 'images', 'mainImage', 'features','floorPlanImages']);

            // Clear cache
            $this->clearCache();

            Log::info('Project creation completed successfully');

            return ApiResponse::success(
                new ProjectResource($project),
                'Project created successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Project creation error: ' . $e->getMessage());
            return ApiResponse::error('Failed to create project: ' . $e->getMessage());
        }
    }

    /**
     * Get a single Project
     */
   public function show($id): JsonResponse
    {
        try {
            Log::info('📝 SHOW METHOD CALLED with ID:', ['id' => $id, 'type' => gettype($id)]);
            
            $cacheKey = self::CACHE_PREFIX . 'show_' . $id;
            
            $project = null;
            
            if (method_exists(Cache::getStore(), 'tags')) {
                $project = Cache::tags([self::CACHE_TAG])->remember($cacheKey, self::CACHE_TTL, function () use ($id) {
                    return $this->getProjectData($id);
                });
            } else {
                $project = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($id) {
                    return $this->getProjectData($id);
                });
            }
            
            return ApiResponse::success(
                new ProjectResource($project),
                'Project retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('❌ Project show error: ' . $e->getMessage(), [
                'id' => $id,
                'error_trace' => $e->getTraceAsString()
            ]);
            return $this->fallbackShow($id, $e);
        }
    }

    private function getProjectData($id)
    {
        Log::info('🔍 Looking for project with ID:', ['id' => $id]);
        
        $project = Project::with([
            'developer', 
            'images', 
            'mainImage', 
            'features',
            'addedBy','floorPlanImages'
        ])->find($id);
        
        if (!$project) {
            Log::error('❌ Project not found with ID:', ['id' => $id]);
            throw new \Exception('Project not found');
        }

        Log::info('✅ Project found:', [
            'id' => $project->id,
            'title' => $project->title
        ]);
        
        return $project;
    }

    // PUT: api/listings/projects/{id}
    public function update(ProjectRequest $request, $id): JsonResponse
    {          

        try {
            Log::info('🔄 UPDATE METHOD CALLED', [
                'project_id' => $id,
                'user_id' => Auth::id(),
                'type' => gettype($id)
            ]);

            DB::beginTransaction();

            $user = Auth::user();
            $project = Project::find($id);
            
            if (!$project) {
                Log::error('❌ Project not found for update:', ['id' => $id]);
                return ApiResponse::error('Project not found', 404);
            }

            // Check if user has permission to update this project
            // if ($project->added_by !== $user->id && ! $user->hasRole(['admin', 'super_admin'])) {
            //     Log::warning('⚠️ Unauthorized update attempt:', [
            //         'user_id' => $user->id,
            //         'project_added_by' => $project->added_by
            //     ]);
            //     return ApiResponse::error('You are not authorized to update this project', 403);
            // }

            $data = $request->validated();
            
            Log::info('📋 Validated data for update:', array_keys($data));

            // Remove files from data array before updating project
            unset($data['main_image']);
            unset($data['images']);
             unset($data['floor_plan_images']);

            // Handle main image upload
            if ($request->hasFile('main_image')) {
                Log::info('🖼️ Processing new main image upload');
                
                // Delete old main image if exists
                $oldMainImage = $project->mainImage;
                if ($oldMainImage) {
                    ImageHelper::deleteImage($oldMainImage->image_path);
                    $oldMainImage->delete();
                    Log::info('🗑️ Deleted old main image', ['path' => $oldMainImage->image_path]);
                }
                
                $mainImageFile = $request->file('main_image');
                $compressionResult = ImageHelper::compressAndConvertToWebP(
                    $mainImageFile, 
                    "projects/{$project->id}",
                    ['quality' => 90, 'max_width' => 1920]
                );
                
                // Set all images as non-main first
                ProjectImage::where('project_id', $project->id)->update(['is_main' => false]);
                
                // Create new main image
                $project->images()->create([
                    'image_path' => $compressionResult['path'],
                    'is_main' => true,
                    'sort_order' => 0
                ]);
                
                $data['main_image_path'] = $compressionResult['path'];
                Log::info('✅ New main image uploaded', ['path' => $compressionResult['path']]);
            }

            // Handle keep_current_image flag
            if ($request->has('keep_current_image') && $request->boolean('keep_current_image')) {
                Log::info('💾 Keeping current main image');
                // Do nothing - keep current image
            }

            // Sync features
            if ($request->has('features')) {
                $features = is_array($request->features) ? $request->features : [];
                $project->features()->sync($features);
                Log::info('🔗 Features synced', ['count' => count($features)]);
            }

            Log::info('📝 Updating project with data:', $data);
            
            // Update project
            $project->update($data);

            Log::info('✅ Project updated successfully', [
                'project_id' => $project->id,
                'title' => $project->title
            ]);

            // Handle additional images upload
            if ($request->has('delete_images') && is_array($request->delete_images)) {
                foreach ($request->delete_images as $imageId) {
                    $image = ProjectImage::find($imageId);
                    if ($image && $image->project_id === $project->id && !$image->is_main) {
                        ImageHelper::deleteImage($image->image_path);
                        $image->delete();
                    }
                }
            }
            
            // Handle new gallery images upload
            if ($request->hasFile('images')) {
                $maxOrder = $project->images()->max('sort_order') ?? 0;
                
                foreach ($request->file('images') as $index => $imageFile) {
                    $compressionResult = ImageHelper::compressAndConvertToWebP(
                        $imageFile, 
                        "projects/{$project->id}/gallery",
                        ['quality' => 85, 'max_width' => 1920]
                    );
                    
                    $project->images()->create([
                        'image_path' => $compressionResult['path'],
                        'is_main' => false,
                        'sort_order' => $maxOrder + $index + 1
                    ]);
                }
            }
              // Handle floor plan images
        if ($request->has('floor_plan_images') && count($request->floor_plan_images)>0) {
            Log::info('🔄 Processing floor plan images update', [
                'count' => count($request->file('floor_plan_images'))
            ]);
            
            // Get current max order
            $maxOrder = $project->floorPlanImages()->max('sort_order') ?? 0;
            
             foreach ($request->floor_plan_images as $index => $data) {
                 if(isset($data['file'])){
                    $imageFile = $data['file'];
                    $name = $data['name'] ?? "Floor Plan " . ($maxOrder + $index + 1);
                    $compressionResult = ImageHelper::compressAndConvertToWebP(
                        $imageFile, 
                        "projects/{$project->id}/floor-plans",
                        ['quality' => 85, 'max_width' => 1920]
                    );
                    
                    $project->floorPlanImages()->create([
                        'image_path' => $compressionResult['path'],
                        'sort_order' => $maxOrder + $index + 1,
                        'name'=>$name
                    ]);
                 }
            }
            
            Log::info('✅ New floor plan images uploaded', [
                'count' => count($request->file('floor_plan_images'))
            ]);
        }
            if ($request->has('floor_plan_names')) {
                foreach ($request->floor_plan_names as $imageId => $name) {
                    $floorPlanImage = FloorPlanImage::find($imageId);
                    if ($floorPlanImage ) {
                        $floorPlanImage->update([
                            'name' => $name ?: null
                        ]);
                    }
                }
            }
        // Handle delete floor plan images
        if ($request->has('delete_floor_plan_images') && is_array($request->delete_floor_plan_images)) {
            Log::info('🗑️ Deleting floor plan images', [
                'ids' => $request->delete_floor_plan_images
            ]);
            
            foreach ($request->delete_floor_plan_images as $imageId) {
                $floorPlanImage = FloorPlanImage::find($imageId);
                if ($floorPlanImage && $floorPlanImage->project_id === $project->id) {
                    if ($floorPlanImage->image_path) {
                        ImageHelper::deleteImage($floorPlanImage->image_path);
                    }
                    $floorPlanImage->delete();
                }
            }
        }

            DB::commit();

            // Reload relationships
            $project->load(['developer', 'images', 'mainImage', 'features','floorPlanImages']);

            // Clear cache
            $this->clearCache();
            $this->clearSpecificCache($project->id);

            Log::info('🎉 Project update completed successfully');

            return ApiResponse::success(
                new ProjectResource($project),
                'Project updated successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Project update error: ' . $e->getMessage(), [
                'project_id' => $id,
                'error_trace' => $e->getTraceAsString()
            ]);
            return ApiResponse::error('Failed to update project: ' . $e->getMessage());
        }
    }

    /**
     * Delete a Project
     */
    public function destroy($projectId): JsonResponse
    {
        try {
            $user = Auth::user();
            $project = Project::find($projectId);
            
            if (!$project) {
                return ApiResponse::error('Project not found', 404);
            }
            
            // Check if user has permission to delete this project
            if ($project->added_by !== $user->id && ! $user->hasRole(['admin', 'super_admin'])) {
                return ApiResponse::error('You are not authorized to delete this project', 403);
            }

            // Delete project images
            foreach ($project->images as $image) {
                if ($image->image_path) {
                    ImageHelper::deleteImage($image->image_path);
                }
                $image->delete();
            }
            // Delete floor plan images
        foreach ($project->floorPlanImages as $floorPlanImage) {
            if ($floorPlanImage->image_path) {
                ImageHelper::deleteImage($floorPlanImage->image_path);
            }
            $floorPlanImage->delete();
        }


            // Detach all features
            $project->features()->detach();

            $projectId = $project->id;
            $project->delete();

            $this->clearCache();
            $this->clearSpecificCache($projectId);

            return ApiResponse::success(null, 'Project deleted successfully');
        } catch (\Exception $e) {
            Log::error('Project delete error: ' . $e->getMessage());
            return ApiResponse::error('Failed to delete project: ' . $e->getMessage());
        }
    }

   
   
    private function clearCache(): void
    {
        try {
            if (method_exists(Cache::getStore(), 'tags')) {
                Cache::tags([self::CACHE_TAG])->flush();
                Log::info('Projects cache cleared using tags');
            } else {
                $this->clearCacheWithoutTags();
            }
        } catch (\Exception $e) {
            Log::warning('Projects cache clear error: ' . $e->getMessage());
            
            Cache::flush();
            Log::info('Full cache flush as fallback for projects');
        }
    }

    private function clearCacheWithoutTags(): void
    {
        try {
            $cacheDriver = config('cache.default');
            
            if ($cacheDriver === 'redis') {
                $this->clearRedisCache();
            } 
            elseif ($cacheDriver === 'file') {
                $this->clearFileCache();
            }
            else {
                Cache::flush();
                Log::info('All cache flushed for database driver in projects');
            }
            
            Log::info('Projects cache cleared without tags for driver: ' . $cacheDriver);
        } catch (\Exception $e) {
            Log::warning('Projects cache clear without tags error: ' . $e->getMessage());
        }
    }

  
    private function clearRedisCache(): void
    {
        $redis = Cache::getRedis();
        $iterator = null;
        $patterns = [
            self::CACHE_PREFIX . 'index_*',
            self::CACHE_PREFIX . 'show_*'
        ];
        
        $totalDeleted = 0;
        
        foreach ($patterns as $pattern) {
            $iterator = null;
            do {
                $keys = $redis->scan($iterator, $pattern, 100);
                if (!empty($keys)) {
                    $redis->del($keys);
                    $totalDeleted += count($keys);
                }
            } while ($iterator > 0);
        }
        
        Log::info("Deleted {$totalDeleted} Redis cache keys for projects");
    }

   
    private function clearFileCache(): void
    {
        $storage = Storage::disk('framework_cache');
        $files = $storage->files();
        $deletedCount = 0;
        
        $patterns = [
            self::CACHE_PREFIX . 'index_',
            self::CACHE_PREFIX . 'show_',
            self::CACHE_PREFIX . 'developers_',
            self::CACHE_PREFIX . 'features_'
        ];
        
        foreach ($files as $file) {
            foreach ($patterns as $pattern) {
                if (str_contains($file, $pattern)) {
                    $storage->delete($file);
                    $deletedCount++;
                    break;
                }
            }
        }
        
        Log::info("Deleted {$deletedCount} file cache keys for projects");
    }

    
    private function clearSpecificCache(int $projectId): void
    {
        try {
            Cache::forget(self::CACHE_PREFIX . 'show_' . $projectId);
            Log::info('Cleared specific cache for project: ' . $projectId);
        } catch (\Exception $e) {
            Log::warning('Project cache clear error: ' . $e->getMessage());
        }
    }

    
    private function fallbackIndex(Request $request, \Exception $e = null): JsonResponse
    {
        try {
            $result = $this->getProjectsData($request);
            return ApiResponse::success(
                ProjectResource::collection($result['projects']),
                'Projects retrieved successfully (cache fallback)',
                200,
                $result['pagination']
            );
        } catch (\Exception $fallbackError) {
            Log::error('Projects fallback error: ' . $fallbackError->getMessage());
            return ApiResponse::error('Failed to retrieve projects');
        }
    }

    
    private function fallbackShow($project, \Exception $e = null): JsonResponse
    {
        try {
            $project = $this->getProjectData($project);
            return ApiResponse::success(
                new ProjectResource($project),
                'Project retrieved successfully (cache fallback)'
            );
        } catch (\Exception $fallbackError) {
            Log::error('Project fallback error: ' . $fallbackError->getMessage());
            return ApiResponse::error('Failed to retrieve project');
        }
    }

    
    public function clearCacheManual(): JsonResponse
    {
        try {
            $this->clearCache();
            return ApiResponse::success(null, 'Projects cache cleared successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to clear cache: ' . $e->getMessage());
        }
    }

    
    public function checkCacheStatus(): JsonResponse
    {
        try {
            $cacheDriver = config('cache.default');
            $status = [
                'driver' => $cacheDriver,
                'supports_tags' => method_exists(Cache::getStore(), 'tags'),
                'cache_prefix' => self::CACHE_PREFIX,
                'cache_tag' => self::CACHE_TAG,
            ];
            
            return ApiResponse::success($status, 'Projects cache status retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to check cache status: ' . $e->getMessage());
        }
    }

public function getFloorPlans( $id)
{
    try {
        $project=Project::find($id);
        $floorPlans = $project->floorPlanImages()
            ->select('id', 'name', 'image_path', 'sort_order','area_id')
            ->orderBy('sort_order')
            ->get()
            ->map(function ($floorPlan) use ($id) {
                return [
                    'id' => $floorPlan->id,
                    'name' => $floorPlan->name,
                    'image_url' => $floorPlan->image_path ? asset('storage/' . $floorPlan->image_path) : null,
                    'order' => $floorPlan->sort_order,
                    'sort_order' => $floorPlan->sort_order,
                    'created_at' => $floorPlan->created_at,
                    'project_id' => $id,
                    'area_id'=>$floorPlan->area_id
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $floorPlans
        ]);
    } catch (\Exception $e) {
        dd($e);
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch floor plans'
        ], 500);
    }
}
   public function updateFloorPlanName(Request $request, $id)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:100'
            ]);

            $floorPlanImage = FloorPlanImage::findOrFail($id);
            $floorPlanImage->update([
                'name' => $request->name
            ]);

            Log::info('Floor plan name updated', [
                'id' => $id,
                'name' => $request->name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Floor plan name updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Update floor plan name error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update floor plan name'
            ], 500);
        }
    }

 public function getFloorPlansByArea($projectId, $areaId)
    {
        try {
            Log::info('Fetching floor plans for area', [
                'project_id' => $projectId,
                'area_id' => $areaId
            ]);

            $project = Project::findOrFail($projectId);
            
            // بنجيب الصور اللي project_id = projectId AND area_id = areaId
            $floorPlans = FloorPlanImage::where('project_id', $projectId)
                ->where('area_id', $areaId)
                ->orderBy('sort_order')
                ->get()
                ->map(function ($floorPlan) {
                    return [
                        'id' => $floorPlan->id,
                        'name' => $floorPlan->name ?? '',
                        'image_url' => $floorPlan->image_url,
                        'area_id' => $floorPlan->area_id,
                        'area_name' => $floorPlan->area?->name,
                        'sort_order' => $floorPlan->sort_order,
                        'created_at' => $floorPlan->created_at ? $floorPlan->created_at->format('Y-m-d H:i:s') : null,
                        'file_size' => $floorPlan->file_size
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $floorPlans,
                'area_id' => (int)$areaId,
                'total' => $floorPlans->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Get floor plans error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch floor plans',
                'error' => $e->getMessage()
            ], 500);
        }
    }
     
     
     
 public function updateFloorPlans(Request $request, $id)
{
    try {
        DB::beginTransaction();

        $project = Project::findOrFail($id);
        $areaId = $request->input('area_id');

        if (!$areaId) {
            return response()->json([
                'success' => false,
                'message' => 'Area ID is required'
            ], 400);
        }

        Log::info('=== START UPDATE FLOOR PLANS ===');
        Log::info('Project ID: ' . $id);
        Log::info('Area ID: ' . $areaId);
        
        // 🔴 الحل: نستخدم file() مباشرة ونتأكد منها يدوياً
        $uploadedFiles = $request->file('floor_plan_images');
        
        if (!empty($uploadedFiles) && is_array($uploadedFiles)) {
            Log::info('Files found!', ['count' => count($uploadedFiles)]);
            
            // نجيب أقصى ترتيب للصور في المنطقة دي
            $maxOrder = FloorPlanImage::where('project_id', $project->id)
                ->where('area_id', $areaId)
                ->max('sort_order') ?? 0;
            
            $processedCount = 0;
            
            foreach ($uploadedFiles as $index => $fileData) {
                Log::info('Processing index ' . $index);
                
                // الفايل جوا array مع key 'file'
                if (isset($fileData['file']) && $fileData['file'] instanceof \Illuminate\Http\UploadedFile) {
                    $file = $fileData['file'];
                    
                    // نجيب الاسم من الـ request (لو موجود)
                    $name = $request->input("floor_plan_images.{$index}.name") ?? 
                           "Floor Plan " . ($maxOrder + $processedCount + 1);
                    
                    Log::info('File details:', [
                        'original_name' => $file->getClientOriginalName(),
                        'name' => $name,
                        'size' => $file->getSize(),
                        'mime' => $file->getMimeType(),
                        'is_valid' => $file->isValid() ? 'Yes' : 'No'
                    ]);
                    
                    if ($file->isValid()) {
                        // نضغط الصورة
                        $compressionResult = ImageHelper::compressAndConvertToWebP(
                            $file, 
                            "projects/{$project->id}/floor-plans/area-{$areaId}",
                            ['quality' => 85, 'max_width' => 1920]
                        );
                        
                        // نضيف الصورة
                        $newImage = FloorPlanImage::create([
                            'project_id' => $project->id,
                            'area_id' => $areaId,
                            'image_path' => $compressionResult['path'],
                            'sort_order' => $maxOrder + $processedCount + 1,
                            'name' => $name,
                            'file_size' => $file->getSize()
                        ]);
                        
                        Log::info('✅ Added image: ' . $newImage->id . ' - ' . $name);
                        $processedCount++;
                    }
                }
            }
            
            Log::info("Processed {$processedCount} files");
        } else {
            Log::info('No files found');
        }

        // 1. حذف الصور المحددة
        if ($request->has('delete_floor_plan_images')) {
            $imageIds = $request->input('delete_floor_plan_images');
            if (is_array($imageIds)) {
                foreach ($imageIds as $imageId) {
                    $floorPlanImage = FloorPlanImage::find($imageId);
                    if ($floorPlanImage && $floorPlanImage->project_id == $project->id) {
                        
                        if ($floorPlanImage->image_path) {
                            ImageHelper::deleteImage($floorPlanImage->image_path);
                        }
                        $floorPlanImage->delete();
                        Log::info('Deleted image: ' . $imageId);
                    }
                }
            }
        }

        // 3. تحديث أسماء الصور الموجودة
        if ($request->has('floor_plan_names')) {
            $names = $request->input('floor_plan_names');
            if (is_array($names)) {
                foreach ($names as $imageId => $name) {
                    FloorPlanImage::where('id', $imageId)
                        ->where('project_id', $project->id)
                        ->update(['name' => $name]);
                }
            }
        }

        DB::commit();
        Log::info('=== END UPDATE FLOOR PLANS - SUCCESS ===');

        // نجيب الصور المحدثة
        $updatedFloorPlans = FloorPlanImage::where('project_id', $project->id)
            ->where('area_id', $areaId)
            ->orderBy('sort_order')
            ->get()
            ->map(function ($floorPlan) {
                return [
                    'id' => $floorPlan->id,
                    'name' => $floorPlan->name,
                    'image_url' => $floorPlan->image_url,
                    'area_id' => $floorPlan->area_id,
                    'sort_order' => $floorPlan->sort_order,
                    'created_at' => $floorPlan->created_at ? $floorPlan->created_at->format('Y-m-d H:i:s') : null,
                    'file_size' => $floorPlan->file_size
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Floor plans updated successfully',
            'data' => $updatedFloorPlans
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('❌ Error: ' . $e->getMessage());
        Log::error('Stack trace: ' . $e->getTraceAsString());
        
        return response()->json([
            'success' => false,
            'message' => 'Failed: ' . $e->getMessage()
        ], 500);
    }
}
}