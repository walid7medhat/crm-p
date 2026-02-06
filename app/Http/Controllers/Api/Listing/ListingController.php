<?php

namespace App\Http\Controllers\Api\Listing;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Helpers\ImageHelper;
use App\Http\Requests\Listing\ListingRequest;
use App\Http\Resources\Listing\ListingResource;
use App\Http\Resources\Listing\ListingGridResource;
use App\Models\Listing;
use App\Models\ListingAdditionalDocument;
use App\Models\FloorPlan;
use App\Models\Area;
use App\Models\User;
use App\Models\GalleryImage;
use App\Notifications\PropertyAssignedNotification;
use App\Notifications\PropertyUnassignedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
class ListingController extends Controller
{
    // Cache constants
    const CACHE_TTL = 1800;
    const CACHE_PREFIX = 'listings_';
    const CACHE_TAG = 'listings';
    const PAGINATION_CACHE_TTL = 900;

    public function __construct()
    {
        $this->middleware('permission:listings-list', ['only' => [ 'show']]);
        $this->middleware('permission:listings-create', ['only' => ['store']]);
        $this->middleware('permission:listings-edit', ['only' => ['update']]);
        $this->middleware('permission:listings-delete', ['only' => ['destroy']]);
    }

    
  public function index(Request $request): JsonResponse
{
    try {
        // 1) cache key
        $filtersHash = md5(serialize($request->all()));

        if ($request->boolean('my_listings')) {
            $cacheKey = self::CACHE_PREFIX . 'my_' . Auth::id() . '_' . $filtersHash;
        } else {
            $cacheKey = self::CACHE_PREFIX . 'index_' . Auth::id() . '_'. $filtersHash;
        }

        if (method_exists(Cache::getStore(), 'tags')) {
            $result = Cache::tags([self::CACHE_TAG])->remember(
                $cacheKey,
                self::CACHE_TTL,
                fn () => $this->getListingsData($request)
            );
        } else {
            $result = Cache::remember(
                $cacheKey,
                self::CACHE_TTL,
                fn () => $this->getListingsData($request)
            );
        }

        return ApiResponse::success(
            ListingGridResource::collection($result['listings']),
            'Listings retrieved successfully',
            200,
            $result['pagination']
        );

    } catch (\Exception $e) {
        return $this->fallbackIndex($request, $e);
    }
}

public function permissions(User $user): JsonResponse
{
    try {
        $permissions = $user->getAllPermissions(); 

        return ApiResponse::success(
            $permissions,
            'User permissions retrieved successfully'
        );
    } catch (\Exception $e) {
        return ApiResponse::error('Failed to retrieve user permissions: ' . $e->getMessage());
    }
}

    
    private function getListingsData(Request $request): array
    {
        $user = Auth::user();
        $query = Listing::with([
            'propertyType:id,name', 
            'area:id,name,parent_id',
            'agent:id,name',
            'galleryImages',
        ]);

          if (!($user->hasRole('super_admin') || $user->hasRole('admin')) && $request->boolean('my_listings')) {
                    $currentUser = $user;
                    $allIds = User::where(function($q) use ($currentUser) {
                        $q->where('id', $currentUser->id)
                        ->orWhere('parent_id', $currentUser->id)
                        ->orWhereHas('parent', function($parentQuery) use ($currentUser) {
                            $parentQuery->where('parent_id', $currentUser->id);
                        });
                    })->pluck('id');
                    
                    $query->where(function($q) use ($allIds) {
                        $q->whereIn('agent_id', $allIds);
                    });
                   
                }

        if(!$request->boolean('my_listings') && !($user->hasRole('super_admin') || $user->hasRole('admin'))){
            $query->where('is_active', true)
                ->where('status', '!=', 'converted')
                ->where('status', '!=', 'draft')
                ->where('is_archived', false);
        }
         if ($request->filled('area_id')) {
                    $areaId = $request->area_id;
                    $area = Area::find($areaId);
                
                    if ($area) {
                        $childAreaIds = $area->getChildIdsAttribute();
                        $allAreaIds = array_merge([$areaId], $childAreaIds);
                
                        $query->where(function ($q) use ($allAreaIds) {
                            $q->whereIn('area_id', $allAreaIds)
                              ->orWhereHas('project', function ($projectQuery) use ($allAreaIds) {
                                  $projectQuery->whereIn('area_id', $allAreaIds);
                              });
                        });
                    }
                }

        if($request->filled('is_archived')) {
            $query->where('is_archived', $request->boolean('is_archived'));
        }

        if($request->filled('converted')) {
            $query->where('status', 'converted');
        }

        if($request->filled('status')) {
            $query->where('status', $request->status);
        }
            if($request->filled('completion_status')) {
                $completionStatus = $request->completion_status;
                $query->where('completion_status', $completionStatus);
            }

        if($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }
           if($request->filled('agent_id')) {
                $query->where('agent_id', $request->agent_id);
            }
             if($request->filled('owner_id')) {
                $query->where('owner_id', $request->owner_id);
            }
              if($request->filled('project_id')) {
                $query->where('project_id', $request->project_id);
            }
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                    ->orWhere('reference_number','like',"%{$search}%")
                    ->orWhere('unit_number', 'like', "%{$search}%")
                    ->orWhereHas('owner', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
                });
            }
          if ($request->has('reference_number') && $request->reference_number != '') {
                $query->where('reference_number', 'like', '%' . $request->reference_number . '%');
            }
             


            $basicFilters = [
                'property_type_id',
                'developer_id', 
                'listing_status',
                'ownership_type',
                'furnished_status',
                'responsiblePerson'
            ];

            foreach ($basicFilters as $filter) {
                if ($request->filled($filter)) {
                    $query->where($filter, $request->$filter);
                }
            }

            if ($request->filled('number_of_bedrooms')) {
                $bedrooms = $request->number_of_bedrooms;
                $bedrooms == 'Studio' ? 0 : $bedrooms;
                $query->where('number_of_bedrooms',$bedrooms );
            }

            if ($request->filled('min_price')) {
                $query->where('price', '>=', $request->min_price);
            }
            if ($request->filled('max_price')) {
                $query->where('price', '<=', $request->max_price);
            }

            $sort = $request->get('sort', 'created_at_desc');
            switch ($sort) {
                case 'price_asc': $query->orderBy('price', 'asc'); break;
                case 'price_desc': $query->orderBy('price', 'desc'); break;
                case 'size_asc': $query->orderBy('size_sqft', 'asc'); break;
                case 'size_desc': $query->orderBy('size_sqft', 'desc'); break;
                case 'off_plan': $query->orderBy('completion_status', 'asc'); break;
                 case 'hot_deal': $query->orderBy('is_hot_deal', 'asc'); break;
                 case 'created_at_asc':
                    $query->orderBy('created_at', 'asc');
                    break;
                case 'created_at_desc':
                default: $query->orderBy('created_at', 'desc'); break;
            }
            $perPage = $request->get('per_page', 12);
            $listings = $query->paginate($perPage);

            return [
                'listings' => $listings,
                
                'pagination' => [
                    'current_page' => $listings->currentPage(),
                    'last_page' => $listings->lastPage(),
                    'per_page' => $listings->perPage(),
                    'total' => $listings->total(),
                ]
            ];
    }

    
     public function store(ListingRequest $request): JsonResponse
        {

            \Log::info('Starting property creation', ['action' => $request->input('action')]);
            
            try {
                ini_set('memory_limit', '1024M'); 

                DB::beginTransaction();

                $data = $request->validated();
                \Log::info('Validated data', array_keys($data));

                $listingStatus = 'draft';
                if ($request->has('action')) {
                    if ($request->action === 'publish') {
                        $listingStatus = 'published';
                    } elseif ($request->action === 'preview') {
                        $listingStatus = 'draft';
                    }
                }

                \Log::info('Listing status determined', ['status' => $listingStatus]);

                // Remove files from data array before creating listing
                unset($data['floor_plans']);
                unset($data['floor_plan_names']);
                unset($data['gallery']);
                unset($data['hero_image']);

                \Log::info('Files removed from data array');

                // Handle hero image upload with compression
                $heroImagePath = null;
                if ($request->hasFile('hero_image')) {
                    \Log::info('Processing hero image');
                    $heroImageFile = $request->file('hero_image');
                   $compressionResult = ImageHelper::compressAndConvertToWebP(
                            $heroImageFile,
                            "listings/hero",
                            [
                                'quality' => 90,
                                'max_width' => 1920,
                                'watermark' => [
                                    'enabled'  => true,
                                    'path'     => 'storage/Setting/1745128256Oia Watermark.png',
                                    'position' => 'center',
                                    'opacity'  => 90
                                ]
                            ]
                        );

                    $heroImagePath = $compressionResult['path'];
                    \Log::info('Hero image processed', ['path' => $heroImagePath]);
                }

                // Handle document uploads
                $documentFields = [
                    'spa_document' => 'spa_document_path',
                    'desk_document' => 'desk_document_path', 
                    'other_document' => 'other_document_path'
                ];
                
                foreach ($documentFields as $field => $pathField) {
                    if ($request->hasFile($field)) {
                        \Log::info('Processing document', ['field' => $field]);
                        $file = $request->file($field);
                        $path = $file->store("listings/documents", 'public');
                        $data[$pathField] = $path;
                    }
                    unset($data[$field]);
                }

                \Log::info('Creating listing with data', $data);

                // Create listing
                $listing = Listing::create(array_merge($data, [
                    'added_by' => auth()->id(),
                    'status' => $listingStatus,
                    'hero_image_path' => $heroImagePath
                ]));

                \Log::info('Listing created', ['id' => $listing->id]);

                // Handle floor plans upload with compression
                if ($request->hasFile('floor_plans')) {
                    \Log::info('Processing floor plans', ['count' => count($request->file('floor_plans'))]);
                    $floorPlanNames = $request->floor_plan_names ?? [];
                    
                    foreach ($request->file('floor_plans') as $index => $floorPlanFile) {
                        // $compressionResult = ImageHelper::compressAndConvertToWebP(
                        //     $floorPlanFile, 
                        //     "listings/floor_plans",
                        //     ['quality' => 85, 'max_width' => 1600]
                        // );
                        $compressionResult = ImageHelper::compressAndConvertToWebP(
                                $floorPlanFile,
                                "listings/floor_plans",
                                [
                                    'quality' => 85,
                                    'max_width' => 1600,
                                    'watermark' => [
                                        'enabled'  => true,
                                        'path'     => 'storage/Setting/1745128256Oia Watermark.png',
                                        'position' => 'center',
                                        'opacity'  => 90
                                    ]
                                ]
                            );

                        
                        $floorPlanName = $floorPlanNames[$index] ?? $floorPlanFile->getClientOriginalName();
                        
                        $listing->floorPlans()->create([
                            'name' => $floorPlanName,
                            'image_path' => $compressionResult['path'],
                            'order' => $index
                        ]);
                    }
                    \Log::info('Floor plans processed');
                }

                // Handle gallery images upload with compression
                if ($request->hasFile('gallery')) {
                    \Log::info('Processing gallery images', ['count' => count($request->file('gallery'))]);
                    foreach ($request->file('gallery') as $index => $galleryFile) {
                      
                         $compressionResult = ImageHelper::compressAndConvertToWebP(
                                $galleryFile,
                                "listings/gallery",
                                [
                                    'quality' => 85,
                                    'max_width' => 1920,
                                    'watermark' => [
                                        'enabled'  => true,
                                        'path'     => 'storage/Setting/1745128256Oia Watermark.png',
                                        'position' => 'center',
                                        'opacity'  => 90
                                    ]
                                ]
                            );
                        
                        $listing->galleryImages()->create([
                            'name' => $galleryFile->getClientOriginalName(),
                            'image_path' => $compressionResult['path'],
                            'order' => $index
                        ]);
                    }
                    \Log::info('Gallery images processed');
                }

                // Handle additional documents (PDF/images, no compression)
            if ($request->hasFile('additional_documents') && Schema::hasTable('listing_additional_documents')) {
                foreach ($request->file('additional_documents') as $index => $file) {
                    if (!$file->isValid()) continue;
                    $path = $file->store('listings/additional_documents', 'public');
                    $listing->additionalDocuments()->create([
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'order' => $index
                    ]);
                }
            }
                
                DB::commit();

                $relationships = ['propertyType', 'area', 'agent', 'owner', 'developer', 'addedBy', 'floorPlans', 'galleryImages'];
                if (Schema::hasTable('listing_additional_documents')) {
                    $relationships[] = 'additionalDocuments';
                }
                $listing->load($relationships);

                // مسح الكاش المتعلق بالـ listings
                $this->clearCache();

                \Log::info('Property creation completed successfully');

                return ApiResponse::success(
                    new ListingResource($listing),
                    $listingStatus === 'draft' ? 'Listing saved as draft successfully' : 'Listing published successfully', 
                    201
                );
            } catch (\Exception $e) {
                DB::rollBack();
                
                return ApiResponse::error('Failed to create listing: ' . $e->getMessage());
            }
        }
   
    public function show($listing): JsonResponse
    {
        try {
            \Log::info('Listing show request', ['listing_id' => $listing, 'user_id' => Auth::id()]);
            
            $cacheKey = self::CACHE_PREFIX . 'show_' . $listing;
            
            if (method_exists(Cache::getStore(), 'tags')) {
                $result = Cache::tags([self::CACHE_TAG])->remember($cacheKey, self::CACHE_TTL, function () use ($listing) {
                    return $this->getListingData($listing);
                });
            } else {
                $result = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($listing) {
                    return $this->getListingData($listing);
                });
            }
            
            return ApiResponse::success(
                new ListingResource($result['listing']),
                'Listing retrieved successfully',
                200,
                $result['permissions']
            );
        } catch (\Exception $e) {
            \Log::error('Listing show error: ' . $e->getMessage(), [
                'listing_id' => $listing,
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id()
            ]);
            return $this->fallbackShow($listing, $e);
        }
    }

    private function getListingData($listingId): array
    {
        $user = Auth::user();
        
        try {
            // Check if additional_documents table exists
            $hasAdditionalDocsTable = \Schema::hasTable('listing_additional_documents');
            
            $relationships = [
                'propertyType', 
                'agent', 
                'owner', 
                'developer', 
                'addedBy', 
                'floorPlans', 
                'galleryImages',
                'project',
                'area' => function($query) {
                    $query->with(['parent.parent.parent', 'child']);
                }
            ];
            
            // Only include additionalDocuments if table exists
            if ($hasAdditionalDocsTable) {
                $relationships[] = 'additionalDocuments';
            }
            
            $listing = Listing::with($relationships)->find($listingId);
            
            if (!$listing) {
                throw new \Exception('Listing not found');
            }

            // Try to load area.parentRecursive, but don't fail if it doesn't exist
            try {
                $listing->load(['area.parentRecursive']);
            } catch (\Exception $e) {
                \Log::warning('Could not load area.parentRecursive: ' . $e->getMessage());
                // Continue without this relationship
            }
            
            $canEdit = $user ? $user->can('update', $listing) : false;
            $canDelete = $user ? $user->can('delete', $listing) : false;
            
            $listing->user_permissions = [
                'can_edit' => $canEdit,
                'can_delete' => $canDelete,
            ];
            
            return [
                'listing' => $listing,
                'permissions' => [
                    'can_edit' => $canEdit,
                    'can_delete' => $canDelete,
                ]
            ];
        } catch (\Exception $e) {
            \Log::error('getListingData error: ' . $e->getMessage(), [
                'listing_id' => $listingId,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

public function update(ListingRequest $request, $listingId): JsonResponse
{
    try {
        ini_set('memory_limit', '1024M');

        

        DB::beginTransaction();

        $user = Auth::user();
        $listing = Listing::find($listingId);
        
        if (!$listing) {
            return ApiResponse::error('Listing not found', 404);
        }

        // Check if user has permission to update this listing
        if ($listing->added_by !== $user->id && $listing->agent_id !== $user->id && ! $user->hasRole('super_admin')) {
            return ApiResponse::error('You are not authorized to update this listing', 403);
        }

        $data = $request->validated();
        unset($data['agent_id']); 


        // Handle listing status based on action
        if ($request->has('action')) {
            if ($request->action === 'publish') {
                $data['status'] = 'published';
            } elseif ($request->action === 'draft') {
                $data['status'] = 'draft';
            } elseif ($request->action === 'preview') {
                $data['status'] = 'draft';
            }
        }

        // Remove files from data array before updating listing
        unset($data['floor_plans']);
        unset($data['floor_plan_names']);
        unset($data['gallery']);
        unset($data['hero_image']);
        unset($data['additional_documents']);

        $heroImageProcessed = false;
        
        if ($request->hasFile('hero_image')) {
            // \Log::info('Processing new hero image upload');
            
            // Delete old hero image if exists
            if ($listing->hero_image_path) {
                ImageHelper::deleteImage($listing->hero_image_path);
                // \Log::info('Deleted old hero image', ['path' => $listing->hero_image_path]);
            }
            
            $heroImageFile = $request->file('hero_image');
            // $compressionResult = ImageHelper::compressAndConvertToWebP(
            //     $heroImageFile, 
            //     "listings/hero",
            //     ['quality' => 90, 'max_width' => 1920]
            // );
             $compressionResult = ImageHelper::compressAndConvertToWebP(
                                $heroImageFile,
                                "listings/hero",
                                [
                                    'quality' => 85,
                                    'max_width' => 1920,
                                    'watermark' => [
                                        'enabled'  => true,
                                        'path'     => 'storage/Setting/1745128256Oia Watermark.png',
                                        'position' => 'center',
                                        'opacity'  => 90
                                    ]
                                ]
                            );
            $data['hero_image_path'] = $compressionResult['path'];
            $heroImageProcessed = true;
            // \Log::info('New hero image uploaded', ['path' => $compressionResult['path']]);
        }

        // Handle document uploads
        $documentFields = [
            'spa_document' => 'spa_document_path',
            'desk_document' => 'desk_document_path',
            'other_document' => 'other_document_path'
        ];
        
        foreach ($documentFields as $field => $pathField) {
            if ($request->hasFile($field)) {

                // Delete old document if exists
                if ($listing->$pathField) {
                    Storage::disk('public')->delete($listing->$pathField);
                }
                
                $file = $request->file($field);
                $path = $file->store("listings/documents", 'public');
                $data[$pathField] = $path;
            }
            unset($data[$field]);
        }

        // For draft updates, set default values for null required fields
        if ($request->input('action') === 'draft') {

            $defaultValues = [
                'unit_number' => $data['unit_number'] ?? 'DRAFT-' . uniqid(),
                'ownership_type' => $data['ownership_type'] ?? 'freehold',
                'completion_status' => $data['completion_status'] ?? 'Completed',
            ];

            $data = array_merge($defaultValues, $data);
        }

        // Update listing
        $listing->update($data);

        // dd($data,$listing);

        // Handle floor plans upload with compression
        if ($request->hasFile('floor_plans')) {
            $floorPlanNames = $request->floor_plan_names ?? [];
            
            // Get current max order to continue from there
            $maxOrder = $listing->floorPlans()->max('order') ?? -1;
            
            foreach ($request->file('floor_plans') as $index => $floorPlanFile) {
               
                 $compressionResult = ImageHelper::compressAndConvertToWebP(
                                $floorPlanFile,
                                "listings/floor_plans",
                                [
                                    'quality' => 85,
                                    'max_width' => 1600,
                                    'watermark' => [
                                        'enabled'  => true,
                                        'path'     => 'storage/Setting/1745128256Oia Watermark.png',
                                        'position' => 'center',
                                        'opacity'  => 90
                                    ]
                                ]
                            );
                
                $floorPlanName = $floorPlanNames[$index] ?? $floorPlanFile->getClientOriginalName();
                
                $listing->floorPlans()->create([
                    'name' => $floorPlanName,
                    'image_path' => $compressionResult['path'],
                    'order' => $maxOrder + $index + 1
                ]);
            }
        }

        if ($request->hasFile('gallery')) {

            // Get current max order to continue from there
            $maxOrder = $listing->galleryImages()->max('order') ?? -1;
            
            $setFirstAsHero = $request->has('hero_image_from_gallery') && $request->input('hero_image_from_gallery') === 'first_new_image';
            
            foreach ($request->file('gallery') as $index => $galleryFile) {
               
                 $compressionResult = ImageHelper::compressAndConvertToWebP(
                                $galleryFile,
                                "listings/gallery",
                                [
                                    'quality' => 85,
                                    'max_width' => 1920,
                                    'watermark' => [
                                        'enabled'  => true,
                                        'path'     => 'storage/Setting/1745128256Oia Watermark.png',
                                        'position' => 'center',
                                        'opacity'  => 90
                                    ]
                                ]
                            );
                $galleryImage = $listing->galleryImages()->create([
                    'name' => $galleryFile->getClientOriginalName(),
                    'image_path' => $compressionResult['path'],
                    'order' => $maxOrder + $index + 1
                ]);
                
                if ($setFirstAsHero && $index === 0 && !$heroImageProcessed) {
                    // \Log::info('Setting first new gallery image as hero image', [
                    //     'gallery_image_id' => $galleryImage->id,
                    //     'path' => $compressionResult['path']
                    // ]);
                    
                    $listing->update([
                        'hero_image_path' => $compressionResult['path']
                    ]);
                    $heroImageProcessed = true;
                }
            }
            // \Log::info('Gallery images added successfully');
        }

        if (!$heroImageProcessed && $request->has('hero_image_from_gallery') && is_numeric($request->input('hero_image_from_gallery'))) {
            $heroGalleryImageId = $request->input('hero_image_from_gallery');
            $heroGalleryImage = $listing->galleryImages()->find($heroGalleryImageId);
            
            if ($heroGalleryImage) {
                // \Log::info('Setting existing gallery image as hero image', [
                //     'gallery_image_id' => $heroGalleryImageId,
                //     'path' => $heroGalleryImage->image_path
                // ]);
                
                $listing->update([
                    'hero_image_path' => $heroGalleryImage->image_path
                ]);
                $heroImageProcessed = true;
                
                // \Log::info('Hero image updated from gallery successfully');
            } else {
                // \Log::warning('Gallery image not found for hero image', [
                //     'gallery_image_id' => $heroGalleryImageId
                // ]);
            }
        }

        // Append additional documents (new uploads only; existing kept)
        if ($request->hasFile('additional_documents') && Schema::hasTable('listing_additional_documents')) {
            $maxOrder = $listing->additionalDocuments()->max('order') ?? -1;
            foreach ($request->file('additional_documents') as $index => $file) {
                if (!$file->isValid()) continue;
                $path = $file->store('listings/additional_documents', 'public');
                $listing->additionalDocuments()->create([
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'order' => $maxOrder + $index + 1
                ]);
            }
        }

        DB::commit();

        // Reload relationships
        $relationships = ['propertyType', 'area', 'agent', 'owner', 'developer', 'addedBy', 'floorPlans', 'galleryImages'];
        if (Schema::hasTable('listing_additional_documents')) {
            $relationships[] = 'additionalDocuments';
        }
        $listing->load($relationships);

        $this->clearCache();
        $this->clearSpecificCache($listing->id);

        // \Log::info('Property update completed successfully');

        // Determine success message based on action
        $successMessage = 'Listing updated successfully';
        if ($request->has('action')) {
            if ($request->action === 'publish') {
                $successMessage = 'Listing published successfully';
            } elseif ($request->action === 'draft') {
                $successMessage = 'Listing saved as draft successfully';
            } elseif ($request->action === 'preview') {
                $successMessage = 'Listing updated for preview successfully';
            }
        }

        return ApiResponse::success(
            new ListingResource($listing),
            $successMessage
        );
    } catch (\Exception $e) {
        DB::rollBack();
        // \Log::error('Failed to update listing', [
        //     'listing_id' => $listingId,
        //     'message' => $e->getMessage(),
        //     'file' => $e->getFile(),
        //     'line' => $e->getLine(),
        //     'trace' => $e->getTraceAsString()
        // ]);
        
        return ApiResponse::error('Failed to update listing: ' . $e->getMessage());
    }
}
    public function destroy($listing): JsonResponse
    {
        try {
            $user = Auth::user();
            $listing = Listing::find($listing);
            
            if (!$listing) {
                return ApiResponse::error('Listing not found', 404);
            }
            
            // Delete documents
            $documentPaths = [
                $listing->spa_document_path,
                $listing->desk_document_path,
                $listing->other_document_path
            ];
            
            foreach ($documentPaths as $path) {
                if ($path) {
                    ImageHelper::deleteImage($path);
                }
            }
            
            // Delete floor plans
            foreach ($listing->floorPlans as $floorPlan) {
                if ($floorPlan->image_path) {
                    ImageHelper::deleteImage($floorPlan->image_path);
                }
                $floorPlan->delete();
            }

            // Delete gallery images
            foreach ($listing->galleryImages as $galleryImage) {
                if ($galleryImage->image_path) {
                    ImageHelper::deleteImage($galleryImage->image_path);
                }
                $galleryImage->delete();
            }
            
            $listingId = $listing->id;
            $listing->delete();

            $this->clearCache();
            $this->clearSpecificCache($listingId);

            return ApiResponse::success(null, 'Listing deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete listing: ' . $e->getMessage());
        }
    }

    
    public function getStatistics(Request $request): JsonResponse
    {
        try {
            $cacheKey = self::CACHE_PREFIX . 'stats_' . md5(serialize($request->all()));
            
            if (method_exists(Cache::getStore(), 'tags')) {
                $stats = Cache::tags([self::CACHE_TAG])->remember($cacheKey, 900, function () use ($request) {
                    return $this->getStatisticsData($request);
                });
            } else {
                $stats = Cache::remember($cacheKey, 900, function () use ($request) {
                    return $this->getStatisticsData($request);
                });
            }
            
            return ApiResponse::success(
                $stats,
                'Listings statistics retrieved successfully'
            );
        } catch (\Exception $e) {
            // Fallback بدون cache
            return ApiResponse::error('Failed to retrieve listings statistics: ' . $e->getMessage());
        }
    }

   
    private function getStatisticsData(Request $request): array
    {
        $user = Auth::user();
        $myListings = $request->has('my_listings') && $request->boolean('my_listings');
        
        if ($user->hasRole('sales') || $myListings) {
            $baseQuery = Listing::where('added_by', $user->id);
            $totalListings = $baseQuery->count();
            $draftListings = $baseQuery->clone()->where('listing_status', 'draft')->count();
            $submittedListings = $baseQuery->clone()->where('listing_status', 'submitted')->count();
            $approvedListings = $baseQuery->clone()->where('listing_status', 'approved')->count();
        } else {
            $totalListings = Listing::count();
            $draftListings = Listing::where('listing_status', 'draft')->count();
            $submittedListings = Listing::where('listing_status', 'submitted')->count();
            $approvedListings = Listing::where('listing_status', 'approved')->count();
        }

        return [
            'total_listings' => $totalListings,
            'draft_listings' => $draftListings,
            'submitted_listings' => $submittedListings,
            'approved_listings' => $approvedListings,
            'draft_percentage' => $totalListings > 0 ? round(($draftListings / $totalListings) * 100, 2) : 0,
            'submitted_percentage' => $totalListings > 0 ? round(($submittedListings / $totalListings) * 100, 2) : 0,
            'approved_percentage' => $totalListings > 0 ? round(($approvedListings / $totalListings) * 100, 2) : 0,
            'is_my_listings' => $myListings,
        ];
    }

    
    private function clearCache(): void
    {
        try {
            if (method_exists(Cache::getStore(), 'tags')) {
                Cache::tags([self::CACHE_TAG])->flush();
                \Log::info('Listings cache cleared using tags');
            } else {
                $this->clearCacheWithoutTags();
            }
        } catch (\Exception $e) {
            \Log::warning('Listings cache clear error: ' . $e->getMessage());
            
            Cache::flush();
            \Log::info('Full cache flush as fallback for listings');
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
                \Log::info('All cache flushed for database driver in listings');
            }
            
            \Log::info('Listings cache cleared without tags for driver: ' . $cacheDriver);
        } catch (\Exception $e) {
            \Log::warning('Listings cache clear without tags error: ' . $e->getMessage());
            throw $e;
        }
    }

   
    private function clearRedisCache(): void
    {
        $redis = Cache::getRedis();
        $iterator = null;
        $patterns = [
            self::CACHE_PREFIX . 'index_*',
            self::CACHE_PREFIX . 'show_*',
            self::CACHE_PREFIX . 'stats_*'
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
        
        \Log::info("Deleted {$totalDeleted} Redis cache keys for listings");
    }

    
    private function clearFileCache(): void
    {
        $storage = Storage::disk('framework_cache');
        $files = $storage->files();
        $deletedCount = 0;
        
        $patterns = [
            self::CACHE_PREFIX . 'index_',
            self::CACHE_PREFIX . 'show_',
            self::CACHE_PREFIX . 'stats_'
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
        
        \Log::info("Deleted {$deletedCount} file cache keys for listings");
    }

   
    private function clearSpecificCache(int $listingId): void
    {
        try {
            Cache::forget(self::CACHE_PREFIX . 'show_' . $listingId);
            \Log::info('Cleared specific cache for listing: ' . $listingId);
        } catch (\Exception $e) {
            \Log::warning('Listing cache clear error: ' . $e->getMessage());
        }
    }

    private function fallbackIndex(Request $request, \Exception $e = null): JsonResponse
    {
        try {
            $result = $this->getListingsData($request);
            return ApiResponse::success(
                ListingGridResource::collection($result['listings']),
                'Listings retrieved successfully (cache fallback)',
                200,
                $result['pagination']
            );
        } catch (\Exception $fallbackError) {
            \Log::error('Listings fallback error: ' . $fallbackError->getMessage());
            return ApiResponse::error('Failed to retrieve listings');
        }
    }

   
    private function fallbackShow($listing, \Exception $e = null): JsonResponse
    {
        try {
            \Log::info('Using fallback show for listing', ['listing_id' => $listing]);
            $result = $this->getListingData($listing);
            return ApiResponse::success(
                new ListingResource($result['listing']),
                'Listing retrieved successfully (cache fallback)',
                200,
                $result['permissions']
            );
        } catch (\Exception $fallbackError) {
            \Log::error('Listing fallback error: ' . $fallbackError->getMessage(), [
                'listing_id' => $listing,
                'original_error' => $e?->getMessage(),
                'fallback_trace' => $fallbackError->getTraceAsString()
            ]);
            return ApiResponse::error('Failed to retrieve listing: ' . $fallbackError->getMessage(), 400);
        }
    }

   
    public function clearCacheManual(): JsonResponse
    {
        try {
            $this->clearCache();
            return ApiResponse::success(null, 'Listings cache cleared successfully');
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
            
            return ApiResponse::success($status, 'Listings cache status retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to check cache status: ' . $e->getMessage());
        }
    }

    
    public function deleteFloorPlan($listing, $floorPlan): JsonResponse
    {
        try {
            $user = Auth::user();
            $listing = Listing::find($listing);
            $floorPlan = FloorPlan::find($floorPlan);
            
            if (!$listing || !$floorPlan) {
                return ApiResponse::error('Listing or floor plan not found', 404);
            }
            
            if ($user->hasRole('sales') && $listing->agent_id !== $user->id) {
                return ApiResponse::error('Access denied', 403);
            }
            
            // Check if floor plan belongs to listing
            if ($floorPlan->floor_planable_id !== $listing->id || $floorPlan->floor_planable_type !== Listing::class) {
                return ApiResponse::error('Floor plan not found for this listing', 404);
            }
            
            if ($floorPlan->image_path) {
                ImageHelper::deleteImage($floorPlan->image_path);
            }
            
            $floorPlan->delete();

            return ApiResponse::success(null, 'Floor plan deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete floor plan: ' . $e->getMessage());
        }
    }

    
    public function deleteGalleryImage($listing, $galleryImage): JsonResponse
    {
        try {
            $user = Auth::user();
            $listing = Listing::find($listing);
            $galleryImage = GalleryImage::find($galleryImage);
            
            if (!$listing || !$galleryImage) {
                return ApiResponse::error('Listing or gallery image not found', 404);
            }
            
            if ($user->hasRole('sales') && $listing->agent_id !== $user->id) {
                return ApiResponse::error('Access denied', 403);
            }
            
            // Check if gallery image belongs to listing
            if ($galleryImage->imageable_id !== $listing->id || $galleryImage->imageable_type !== Listing::class) {
                return ApiResponse::error('Gallery image not found for this listing', 404);
            }
            
            if ($galleryImage->image_path) {
                ImageHelper::deleteImage($galleryImage->image_path);
            }
            
            $galleryImage->delete();

            return ApiResponse::success(null, 'Gallery image deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete gallery image: ' . $e->getMessage());
        }
    }

    public function deleteAdditionalDocument($listing, $document): JsonResponse
    {
        try {
            $user = Auth::user();
            $listing = Listing::find($listing);
            $doc = ListingAdditionalDocument::find($document);
            if (!$listing || !$doc) {
                return ApiResponse::error('Listing or document not found', 404);
            }
            if ($doc->listing_id != $listing->id) {
                return ApiResponse::error('Document not found for this listing', 404);
            }
            if ($user->hasRole('sales') && $listing->agent_id !== $user->id) {
                return ApiResponse::error('Access denied', 403);
            }
            if ($doc->path && Storage::disk('public')->exists($doc->path)) {
                Storage::disk('public')->delete($doc->path);
            }
            $doc->delete();
            $this->clearCache();
            return ApiResponse::success(null, 'Additional document deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete document: ' . $e->getMessage());
        }
    }

    /**
     * Update floor plan order
     */
    public function updateFloorPlanOrder(Request $request, $listing): JsonResponse
    {
        try {
            $user = Auth::user();
            $listing = Listing::find($listing);
            
            if (!$listing) {
                return ApiResponse::error('Listing not found', 404);
            }
            
            if ($user->hasRole('sales') && $listing->agent_id !== $user->id) {
                return ApiResponse::error('Access denied', 403);
            }
            
            $request->validate([
                'floor_plans' => 'required|array',
                'floor_plans.*.id' => 'required|exists:floor_plans,id',
                'floor_plans.*.order' => 'required|integer'
            ]);
            
            foreach ($request->floor_plans as $item) {
                $floorPlan = FloorPlan::find($item['id']);
                
                // Verify floor plan belongs to listing
                if ($floorPlan && $floorPlan->floor_planable_id === $listing->id && $floorPlan->floor_planable_type === Listing::class) {
                    $floorPlan->update(['order' => $item['order']]);
                }
            }
            
            return ApiResponse::success(null, 'Floor plan order updated successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update floor plan order: ' . $e->getMessage());
        }
    }
public function toggleArchive($id)
{
    try {
        $property = Listing::findOrFail($id);
        
        // التحقق من الصلاحيات
        if (Auth::user()->hasRole('sales') && $property->agent_id !== Auth::id()) {
            return ApiResponse::error('Access denied', 403);
        }

        $property->update([
            'is_archived' => !$property->is_archived
        ]);

        $this->clearCache();

        return ApiResponse::success(
            new ListingResource($property->fresh()),
            'Property archive status updated successfully'
        );

    } catch (\Exception $e) {
        return ApiResponse::error('Failed to update archive status: ' . $e->getMessage());
    }
}

public function toggleStatus($id)
{
    try {
        $property = Listing::findOrFail($id);
        
        if (Auth::user()->hasRole('super_admin') || (Auth::user()->hasRole('sales') && $property->agent_id !== Auth::id())) {
            return ApiResponse::error('Access denied', 403);
        }

        $property->update([
            'is_active' => !$property->is_active
        ]);

        $this->clearCache();

        return ApiResponse::success(
            new ListingResource($property->fresh()),
            'Property status updated successfully'
        );

    } catch (\Exception $e) {
        return ApiResponse::error('Failed to update property status: ' . $e->getMessage());
    }
}

public function assignAgent(Request $request, $id)
{
    try {
        $currentUser = Auth::user();

        // السماح بالدور المناسب
        if (! $currentUser->hasRole(['admin','super_admin','team_lead','manager'])) {
            return ApiResponse::error('Access denied', 403);
        }

        // جلب property
        $property = Listing::with(['agent'])->findOrFail($id);

        // لو المدير أو تيم ليد، تأكد property تبعته أو تبع agent من تحته
        if ($currentUser->hasRole(['team_lead','manager'])) {

            // جلب جميع الـ agent المسموح بهم (التحتيه)
            $allowedAgentIds = User::where(function($q) use ($currentUser) {
                $q->where('id', $currentUser->id)
                  ->orWhere('parent_id', $currentUser->id)
                  ->orWhereHas('parent', function($parentQuery) use ($currentUser) {
                      $parentQuery->where('parent_id', $currentUser->id);
                  });
            })->pluck('id')->toArray();

            // تأكد إن property agent_id تبع التحتيه أو هو نفسه
            if ($property->agent_id && !in_array($property->agent_id, $allowedAgentIds)) {
                return ApiResponse::error('You cannot assign an agent to a property that does not belong to your hierarchy', 403);
            }
        }

        // Validation للـ agent الجديد
        $request->validate([
            'agent_id' => ['required','exists:users,id', function($attribute, $value, $fail) use ($currentUser) {
                // نفس قاعدة التحتيه
                if ($currentUser->hasRole(['team_lead','manager'])) {
                    $allowedAgentIds = User::where(function($q) use ($currentUser) {
                        $q->where('id', $currentUser->id)
                          ->orWhere('parent_id', $currentUser->id)
                          ->orWhereHas('parent', function($parentQuery) use ($currentUser) {
                              $parentQuery->where('parent_id', $currentUser->id);
                          });
                    })->pluck('id')->toArray();

                    if (!in_array($value, $allowedAgentIds)) {
                        $fail('You cannot assign this agent because they are not under your hierarchy.');
                    }
                }
            }],
            'notes' => 'nullable|string|max:1000'
        ]);

        $oldAgent = $property->agent;
        $newAgent = User::findOrFail($request->agent_id);

        if ($oldAgent && $oldAgent->id == $newAgent->id) {
            return ApiResponse::error('Property is already assigned to this agent', 422);
        }

        $property->update([
            'agent_id' => $request->agent_id,
            'assignment_notes' => $request->notes,
            'assigned_by' => $currentUser->id,
            'assigned_at' => now()
        ]);

        // إشعارات كما في الكود السابق
        if ($newAgent) {
            try { $newAgent->notify(new PropertyAssignedNotification($property, $currentUser)); } 
            catch (\Exception $e) { \Log::error($e->getMessage()); }
        }
        if ($oldAgent && $oldAgent->id != $newAgent->id) {
            try { $oldAgent->notify(new PropertyUnassignedNotification($property, $currentUser)); } 
            catch (\Exception $e) { \Log::error($e->getMessage()); }
        }

        $this->clearCache();

        return ApiResponse::success(
            new ListingResource($property->fresh()),
            'Property assigned to agent successfully'
        );

    } catch (\Exception $e) {
        return ApiResponse::error('Failed to assign agent: ' . $e->getMessage());
    }
}

public function markAsConverted(Request $request, $id)
{
    try {
        $property = Listing::findOrFail($id);
        
        if (Auth::user()->hasRole('sales') && $property->agent_id !== Auth::id()) {
            return ApiResponse::error('Access denied', 403);
        }

        $request->validate([
            'sold_by' => 'required|in:me,oia,other_company'
        ]);

        $property->update([
            'status' => 'converted',
            'sold_by' => $request->sold_by,
            'converted_at' => now(),
            'converted_by' => Auth::id()
        ]);

        $this->clearCache();

        return ApiResponse::success(
            new ListingResource($property->fresh()),
            'Property marked as sold out successfully'
        );

    } catch (\Exception $e) {
        return ApiResponse::error('Failed to mark as converted: ' . $e->getMessage());
    }
}

public function revertFromConverted($id)
{
    try {
        $property = Listing::findOrFail($id);
        
        if (Auth::user()->hasRole('sales') && $property->agent_id !== Auth::id()) {
            return ApiResponse::error('Access denied', 403);
        }

        $property->update([
            'status' => 'active',
            'sold_by' => null,
            'converted_at' => null,
            'converted_by' => null
        ]);

        $this->clearCache();

        return ApiResponse::success(
            new ListingResource($property->fresh()),
            'Property reverted from sold out successfully'
        );

    } catch (\Exception $e) {
        return ApiResponse::error('Failed to revert from converted: ' . $e->getMessage());
    }
}

/**
 * Update only the owner_id of a listing (e.g. when adding new owner on mark as sold).
 */
public function updateOwner(Request $request, $id): JsonResponse
{
    try {
        $request->validate(['owner_id' => 'required|exists:owners,id']);
        $listing = Listing::findOrFail($id);

        if (Auth::user()->hasRole('sales') && $listing->agent_id !== Auth::id()) {
            return ApiResponse::error('Access denied', 403);
        }

        $listing->update(['owner_id' => $request->owner_id]);
        $this->clearCache();

        return ApiResponse::success(
            new ListingResource($listing->fresh(['owner'])),
            'Listing owner updated successfully'
        );
    } catch (\Exception $e) {
        return ApiResponse::error('Failed to update listing owner: ' . $e->getMessage());
    }
}

public function getAgents(Request $request)
{
    try {
        $agents = User::query()->whereHas('roles', function($query) {
                $query->whereNotIn('name', ['admin', 'super_admin']);
            })
            ->where('status','active')->select('id', 'name', 'email', 'avatar', 'phone');
  // Filter by role
            if ($request->has('role')) {
                $agents=$agents->whereHas('roles', function($q) use ($request) {
                    $q->where('name', $request->role);
                });
            }
            $agents=$agents ->get();
        return response()->json([
            'status' => true,
            'data' => $agents,
            'message' => 'Agents retrieved successfully'
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to retrieve agents: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * Set hero image from gallery
 */

public function setHeroImage(Request $request, $listingId): JsonResponse
{
    try {
        $listing = Listing::findOrFail($listingId);
        $galleryImageId = $request->input('gallery_image_id');

        $user = Auth::user();
        if ($listing->added_by !== $user->id && $listing->agent_id !== $user->id) {
            return ApiResponse::error('You are not authorized to update this listing', 403);
        }

        $galleryImage = $listing->galleryImages()->where('id', $galleryImageId)->firstOrFail();

        $listing->update([
            'hero_image_path' => $galleryImage->image_path
        ]);

        \Log::info('Hero image updated from gallery', [
            'listing_id' => $listingId,
            'gallery_image_id' => $galleryImageId,
            'hero_image_path' => $galleryImage->image_path
        ]);

        $heroImageUrl = asset('storage/' . $galleryImage->image_path);

        return ApiResponse::success([
            'hero_image_url' => $heroImageUrl,
            'message' => 'Hero image updated successfully'
        ], 'Hero image updated successfully');

    } catch (\Exception $e) {
        \Log::error('Failed to update hero image', [
            'listing_id' => $listingId,
            'error' => $e->getMessage()
        ]);
        
        return ApiResponse::error('Failed to update hero image: ' . $e->getMessage());
    }
}

public function validateUnitNumber(Request $request)
{
    $validator = Validator::make($request->all(), [
        'unit_number' => [
            'required',
            'string',
            'max:50',
            Rule::unique('listings', 'unit_number')
                ->where(function ($query) use ($request) {
                    $query->where('listing_status', $request->listing_status)
                          ->where('project_id', $request->project_id);
                })
        ],
        'listing_status' => 'required|in:Sale,Rent',
        'project_id' => 'required|exists:projects,id'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'exists' => true,
            'errors' => $validator->errors()
        ], 422);
    }

    return response()->json([
        'exists' => false,
        'message' => 'Unit number is available'
    ]);
}
}