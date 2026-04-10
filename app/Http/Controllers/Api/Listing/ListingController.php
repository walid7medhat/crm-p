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
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;
use App\Models\SearchAlert;
use App\Jobs\CheckSearchAlerts;
use App\Models\PropertyOffer;
use App\Traits\HotDealNotifiable;
use App\Models\HotDealRequest;
use App\Services\ListingMapCoordinateResolver;
use Illuminate\Support\Facades\Log;

class ListingController extends Controller
{
    use HotDealNotifiable;
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

/**
 * Property map: LEFT JOIN listing area + parent/grandparent areas + project area; resolve lat/lng with geocode/default fallback.
 * Pins use the listing area’s coordinates when set; otherwise the parent area’s, then grandparent’s, then config mapping / project / cache.
 * Does not filter by map bounds — the client loads all pins for the current filters (paginated).
 *
 * Example item:
 * {
 *   "id": 1,
 *   "title": "Unit 12",
 *   "latitude": 24.49,
 *   "longitude": 54.61,
 *   "coordinate_source": "listing_parent_area",
 *   "map_area_id": 120,
 *   "area_join_name": "Yas Island",
 *   "area": { "id": 120, "name": "Yas Island", "latitude": 24.49, "longitude": 54.61 },
 *   ...
 * }
 */
public function map(Request $request, ListingMapCoordinateResolver $coordinateResolver): JsonResponse
{
    try {
        $validated = $request->validate([
            'min_lat' => 'nullable|numeric|between:-90,90',
            'max_lat' => 'nullable|numeric|between:-90,90',
            'min_lng' => 'nullable|numeric|between:-180,180',
            'max_lng' => 'nullable|numeric|between:-180,180',
            'area_id' => 'nullable|integer|exists:areas,id',
            'property_type_id' => 'nullable|integer|exists:property_types,id',
            'listing_status' => 'nullable|string|max:50',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'per_page' => 'nullable|integer|min:1|max:3000',
        ]);

        $maxGeocode = (int) config('services.nominatim.map_max_geocode_fallback_per_request', 40);

        $query = Listing::query()
            ->leftJoin('areas as listing_area', 'listings.area_id', '=', 'listing_area.id')
            ->leftJoin('areas as listing_parent_area', 'listing_area.parent_id', '=', 'listing_parent_area.id')
            ->leftJoin('areas as listing_grandparent_area', 'listing_parent_area.parent_id', '=', 'listing_grandparent_area.id')
            ->leftJoin('projects', 'listings.project_id', '=', 'projects.id')
            ->leftJoin('areas as project_area', 'projects.area_id', '=', 'project_area.id')
            ->select('listings.*')
            ->addSelect([
                'listing_area.id as listing_area_id',
                'listing_area.name as listing_area_name',
                'listing_area.latitude as listing_area_latitude',
                'listing_area.longitude as listing_area_longitude',
                'listing_parent_area.id as listing_parent_area_id',
                'listing_parent_area.name as listing_parent_area_name',
                'listing_parent_area.latitude as listing_parent_area_latitude',
                'listing_parent_area.longitude as listing_parent_area_longitude',
                'listing_grandparent_area.id as listing_grandparent_area_id',
                'listing_grandparent_area.name as listing_grandparent_area_name',
                'listing_grandparent_area.latitude as listing_grandparent_area_latitude',
                'listing_grandparent_area.longitude as listing_grandparent_area_longitude',
                'project_area.id as project_area_id',
                'project_area.name as project_area_name',
                'project_area.latitude as project_area_latitude',
                'project_area.longitude as project_area_longitude',
                'projects.title as project_join_title',
            ])
            ->where(function ($q) {
                $q->where('listings.is_active', true)
                    ->orWhereNull('listings.is_active');
            })
            ->where('listings.status', '!=', 'draft')
            ->where(function ($q) {
                $q->where('listings.is_archived', false)
                    ->orWhereNull('listings.is_archived');
            });

        if (isset($validated['property_type_id'])) {
            $query->where('listings.property_type_id', $validated['property_type_id']);
        }

        if (isset($validated['listing_status'])) {
            $query->where('listings.listing_status', $validated['listing_status']);
        }

        if (isset($validated['min_price'])) {
            $query->where('listings.price', '>=', $validated['min_price']);
        }

        if (isset($validated['max_price'])) {
            $query->where('listings.price', '<=', $validated['max_price']);
        }

        if (isset($validated['area_id'])) {
            $area = Area::find($validated['area_id']);
            if ($area) {
                $childAreaIds = $area->getChildIdsAttribute();
                $allAreaIds = array_values(array_unique(array_merge([(int) $area->id], $childAreaIds)));

                $query->where(function ($q) use ($allAreaIds) {
                    $q->whereIn('listings.area_id', $allAreaIds)
                        ->orWhereIn('projects.area_id', $allAreaIds);
                });
            }
        }

        $perPage = (int) ($validated['per_page'] ?? 2500);
        $paginator = $query->with([
            'propertyType:id,name',
            'project:id,title,area_id',
        ])
            ->orderBy('listings.id')
            ->paginate($perPage);

        $payload = $paginator->getCollection()->map(function (Listing $listing) use ($coordinateResolver, $maxGeocode) {
            $resolved = $coordinateResolver->resolve($listing, $maxGeocode);

            $lat = $resolved['latitude'];
            $lng = $resolved['longitude'];
            $resolvedAreaId = $resolved['map_area_id'];
            $resolvedName = $resolved['area_join_name'];

            $fallbackTitle = $listing->project_join_title ?? $listing->project?->title;
            if ($fallbackTitle === null || $fallbackTitle === '') {
                $fallbackTitle = ($resolvedName !== '') ? $resolvedName : 'Property';
            }

            return [
                'id' => $listing->id,
                'title' => $listing->title ?: $fallbackTitle,
                /** Raw unit title (empty when API falls back title to project/area). */
                'listing_title' => $listing->title,
                'latitude' => $lat,
                'longitude' => $lng,
                'coordinate_source' => $resolved['coordinate_source'],
                'map_area_id' => $resolvedAreaId,
                'area_join_name' => $resolvedName,
                'area' => [
                    'id' => $resolvedAreaId,
                    'name' => $resolvedName,
                    'latitude' => $lat,
                    'longitude' => $lng,
                ],
                'price' => $listing->price,
                'type' => $listing->propertyType?->name,
                'listing_status' => $listing->listing_status,
                'project_name' => $listing->project_join_title ?? $listing->project?->title,
                'area_name' => $resolvedName,
                'number_of_bedrooms' => $listing->number_of_bedrooms,
                'number_of_bathrooms' => $listing->number_of_bathrooms,
                'size_sqft' => $listing->size_sqft,
                'size_sqmt' => $listing->size_sqmt,
                'hero_image' => $listing->hero_image_path ? asset('storage/' . $listing->hero_image_path) : null,
            ];
        });

        return ApiResponse::success($payload, 'Property map data retrieved successfully', 200, [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'map_geocode_fallback_limit' => $maxGeocode,
        ]);
    } catch (\Throwable $e) {
        Log::error('ListingController::map failed', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        return ApiResponse::error('Failed to retrieve property map data: ' . $e->getMessage(), 422);
    }
}

    
    public function getListingsData(Request $request): array
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
        
        if($request->has('active') ){
              $query->where('is_active', true)
                ->where('status', '!=', 'converted')
                ->where('status', '!=', 'draft')
                ->where('is_archived', false);
        }
         if ($request->has('area_id')) {
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
  if ($request->has('area_ids')) {
        $areaIds = $request->area_ids;
        
        if (!is_array($areaIds)) {
            $areaIds = explode(',', $areaIds);
        }
        
        $areaIds = array_filter($areaIds); 
        
        if (!empty($areaIds)) {
            $allAreaIds = [];
            foreach ($areaIds as $areaId) {
                $area = Area::find($areaId);
                if ($area) {
                    $childAreaIds = $area->getChildIdsAttribute();
                    $allAreaIds = array_merge($allAreaIds, [$areaId], $childAreaIds);
                } else {
                    $allAreaIds[] = $areaId;
                }
            }
            $allAreaIds = array_unique($allAreaIds);
            
            $query->where(function ($q) use ($allAreaIds) {
                $q->whereIn('area_id', $allAreaIds)
                  ->orWhereHas('project', function ($projectQuery) use ($allAreaIds) {
                      $projectQuery->whereIn('area_id', $allAreaIds);
                  });
            });
        }
    } 
        if($request->has('is_archived')) {
            $query->where('is_archived', $request->boolean('is_archived'));
        }

        if($request->has('converted')) {
            $query->where('status', 'converted');
        }

        if($request->has('status')) {
            $query->where('status', $request->status);
        }
            if($request->has('completion_status')) {
                $completionStatus = $request->completion_status;
                $query->where('completion_status', $completionStatus);
            }

        if($request->has('is_active')) {
            if($request->is_active){
                $query->where('is_active', true)
                ->where('status', '!=', 'converted')
                ->where('status', '!=', 'draft')
                ->where('is_archived', false);
            }else{
            $query->where('is_active', $request->boolean('is_active'));
            }
        }
           if($request->has('agent_id')) {
                $query->where('agent_id', $request->agent_id);
            }
             if($request->has('owner_id')) {
                $query->where('owner_id', $request->owner_id);
            }
              if($request->has('project_id')) {
                $query->where('project_id', $request->project_id);
            }
            if ($request->has('search')) {
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

            if ($request->has('number_of_bedrooms')) {
                $bedrooms = $request->number_of_bedrooms;
                $bedrooms == 'Studio'  || 'studio' ? 0 : $bedrooms;
                if($bedrooms == 10){
                        $query->where('number_of_bedrooms','>=',$bedrooms );
                }else{
                    $query->where('number_of_bedrooms',$bedrooms );
                }
            }
            if ($request->has('number_of_bathrooms')) {
                $bathrooms = $request->number_of_bathrooms;
                if($bathrooms == 6){
                        $query->where('number_of_bathrooms','>=',$bathrooms );
                }else{
                    $query->where('number_of_bathrooms',$bathrooms );
                }
            }

            if ($request->has('min_price')) {
                $query->where('price', '>=', $request->min_price);
            }
            if ($request->has('max_price')) {
                $query->where('price', '<=', $request->max_price);
            }
             if ($request->has('min_size')) {
                $query->where('size_sqft', '>=', $request->min_size);
            }
            if ($request->has('max_size')) {
                $query->where('size_sqft', '<=', $request->max_size);
            }

            $sort = $request->get('sort', 'created_at_desc');
            switch ($sort) {
                case 'price_asc': $query->orderBy('price', 'asc'); break;
                case 'price_desc': $query->orderBy('price', 'desc'); break;
                case 'size_asc': $query->orderBy('size_sqft', 'asc'); break;
                case 'size_desc': $query->orderBy('size_sqft', 'desc'); break;
                case 'off_plan': $query->orderBy('completion_status', 'asc'); break;
                case 'hot_deal': 
                        $query->orderByRaw("
                            (is_hot_deal = 'Yes' 
                            AND hot_deal_approved_by IS NOT NULL 
                            AND hot_deal_approved_at IS NOT NULL) DESC
                        ")
                        ->orderBy('created_at', 'desc');
                        break;
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


public function getMatchingListings(Request $request)
{
    $baseQuery = Listing::with([
        'propertyType:id,name', 
        'area:id,name,parent_id',
        'agent:id,name',
        'galleryImages',
    ])->where('is_active', true)
      ->where('status', '!=', 'draft')
      ->where('status', '!=', 'converted')
      ->where('is_archived', false);

    $projectId = $request->project_id;
    $areaId    = $request->area_id;
    
    $typeId    = $request->property_type_id;
    $bedrooms  = $request->number_of_bedrooms;
    $minPrice  = $request->min_price;
    $maxPrice  = $request->max_price;

    // -----------------------------
    // 🗺️ LOCATION (Priority)
    // -----------------------------
    if ($areaId) {
        $area = Area::find($areaId);

        if ($area) {
            $childIds = $area->getChildIdsAttribute();
            $allAreaIds = array_merge([$areaId], $childIds);

            $areaQuery = clone $baseQuery;

            $areaQuery->where(function ($q) use ($allAreaIds) {
                $q->whereIn('area_id', $allAreaIds)
                  ->orWhereHas('project', function ($q2) use ($allAreaIds) {
                      $q2->whereIn('area_id', $allAreaIds);
                  });
            });

            // لو مفيش نتائج بالـ area → fallback للـ project
            if ($areaQuery->exists()) {
                $baseQuery = $areaQuery;
            } elseif ($projectId) {
                $baseQuery->where('project_id', $projectId);
            }
        }
    } elseif ($projectId) {
        $baseQuery->where('project_id', $projectId);
    }

    // -----------------------------
    // 💰 PRICE EXPANSION (Smart)
    // -----------------------------
    if ($minPrice && $maxPrice) {
        $priceRanges = [
            [$minPrice, $maxPrice],
            [$minPrice * 0.8, $maxPrice * 1.2],
            [$minPrice * 0.6, $maxPrice * 1.4],
        ];

        foreach ($priceRanges as [$min, $max]) {
            $priceQuery = clone $baseQuery;

            if ($typeId) {
                $priceQuery->where('property_type_id', $typeId);
            }

            if ($bedrooms !== null && $bedrooms !== '') {
                $bed = strtolower($bedrooms) === 'studio' ? 0 : $bedrooms;
                $priceQuery->where('number_of_bedrooms', $bed);
            }

            $priceQuery->whereBetween('price', [$min, $max]);

            if ($priceQuery->exists()) {
                $listings = $priceQuery->orderBy('created_at', 'desc')->limit(24)->get();

                return ApiResponse::success(
                    ListingGridResource::collection($listings),
                    'Listings (price expanded)',
                    200,
                    null
                );
            }
        }
    }

    // -----------------------------
    // 🎯 RELAXATION PIPELINE
    // -----------------------------
    $queries = [];

    // 1. Full filters
    $queries[] = function ($q) use ($typeId, $bedrooms, $minPrice, $maxPrice) {
        if ($typeId) $q->where('property_type_id', $typeId);

        if ($bedrooms !== null && $bedrooms !== '') {
            $bed = strtolower($bedrooms) === 'studio' ? 0 : $bedrooms;
            $q->where('number_of_bedrooms', $bed);
        }

        if ($minPrice) $q->where('price', '>=', $minPrice);
        if ($maxPrice) $q->where('price', '<=', $maxPrice);
    };

    // 2. بدون price
    $queries[] = function ($q) use ($typeId, $bedrooms) {
        if ($typeId) $q->where('property_type_id', $typeId);

        if ($bedrooms !== null && $bedrooms !== '') {
            $bed = strtolower($bedrooms) === 'studio' ? 0 : $bedrooms;
            $q->where('number_of_bedrooms', $bed);
        }
    };

    // 3. بدون bedrooms + price
    $queries[] = function ($q) use ($typeId) {
        if ($typeId) $q->where('property_type_id', $typeId);
    };

    // 4. location فقط
    $queries[] = function ($q) {
        // no filters
    };

    $finalQuery = null;

    foreach ($queries as $callback) {
        $testQuery = clone $baseQuery;
        $callback($testQuery);

        if ($testQuery->exists()) {
            $finalQuery = $testQuery;
            break;
        }
    }

    // fallback أخير (مستحيل تقريبًا يوصل هنا)
    if (!$finalQuery) {
        $finalQuery = $baseQuery;
    }

    // -----------------------------
    // 📦 FINAL RESULT
    // -----------------------------
    $listings = $finalQuery
        ->orderBy('created_at', 'desc')
        ->limit(24)
        ->get();
    return ApiResponse::success(
        ListingGridResource::collection($listings),
        'Smart matching listings retrieved',
        200,
        null
    );
}
    /**
     * Handle hot deal status change in store and update methods
     */
    private function handleHotDealStatus(Listing $listing, $newHotDealStatus, $oldHotDealStatus = 'No')
    {
        // If No change or Not requesting hot deal, return
        if ($newHotDealStatus == $oldHotDealStatus) {
            return;
        }

        $user = Auth::user();
                        \Log::info('Creating listing with data', [$newHotDealStatus,$oldHotDealStatus,$user->hasRole('sales') && $user->isListingTeam]);

        // If trying to mark as hot deal
        if ($newHotDealStatus == 'Yes' ) {
            // Check if user is sales agent under listing team
            if ($user->hasRole('sales') && $user->isListingTeam) {
                // Check if there's already a pending request
                if ($listing->hasPendingHotDealRequest()) {
                    throw new \Exception('There is already a pending hot deal request for this property.');
                }
                
                // Create a pending request
                HotDealRequest::create([
                    'listing_id' => $listing->id,
                    'requested_by' => $user->id,
                    'status' => 'pending'
                ]);
                
                // Notify managers and team leads
                $this->NotifyApprovers($listing, $user);
                
                // Don't set as hot deal yet - wait for approval
                $listing->update(['is_hot_deal' => 'No']);
                 return ApiResponse::success($listing,'Hot deal request has been sent for approval. You will be notified once it\'s reviewed.');
                     
                     
                throw new \Exception('Hot deal request has been sent for approval. You will be Notified once it\'s reviewed.', 422);
            }
            // If user is admin/super_admin/manager, they can set directly
            elseif ($user->hasRole(['super_admin', 'admin', 'manager'])) {
                $listing->update([
                    'is_hot_deal' => 'Yes',
                    'hot_deal_approved_by' => $user->id,
                    'hot_deal_approved_at' => Now()
                ]);
            }else if ($user->hasRole('sales') && !$user->isListingTeam ) {
                $listing->update([
                    'is_hot_deal' => 'Yes',
                    ]);
                
            }
            // Otherwise, Not allowed
            else {
                throw new \Exception('You are Not authorized to mark properties as hot deals.');
            }
        } 
        // If unmarking as hot deal
        else {
            $listing->update(['is_hot_deal' => 'No']);
            
            // Optionally cancel any pending requests
            $pendingRequests = $listing->hotDealRequests()->where('status', 'pending')->get();
            foreach ($pendingRequests as $request) {
                $request->update(['status' => 'rejected']);
            }
        }
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
                unset($data['project_floor_plan_ids']);
                unset($data['floor_plans_source']);
                unset($data['floor_plan_names']);
                unset($data['gallery']);
                unset($data['hero_image']);
                unset($data['additional_documents']);

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
               if ($request->has('floor_plans_source')) {
                    $data['floor_plans_source'] = json_encode($request->floor_plans_source);
                }
                
                // Create listing
                $listing = Listing::create(array_merge($data, [
                    'added_by' => auth()->id(),
                    'status' => $listingStatus,
                    'hero_image_path' => $heroImagePath,
                    'is_hot_deal' => 'No'
                ]));
                \Log::info('hot_deal',[ $request->has('is_hot_deal')  , $request->is_hot_deal=='Yes',$request->has('is_hot_deal')  && $request->is_hot_deal=='Yes']);
// Handle hot deal if requested
            if ($request->has('is_hot_deal')  && $request->is_hot_deal=='Yes') {
                try {
                    $this->handleHotDealStatus($listing, 'Yes', 'No');
                } catch (\Exception $e) {
                // dd($e);
                    // If approval is required, don't throw error, just set is_hot_deal to false
                    if (strpos($e->getMessage(), 'approval') !== 'No') {
                        // Already handled in handleHotDealStatus
                    } else {
                        throw $e;
                    }
                }
            }
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
                if ($request->has('project_floor_plan_ids') && is_array($request->project_floor_plan_ids)) {
                    foreach ($request->project_floor_plan_ids as $projectFloorPlanId) {
                        $projectFloorPlan = \App\Models\FloorPlanImage::find($projectFloorPlanId);
                        if ($projectFloorPlan) {
                            $listing->floorPlans()->create([
                                'name' => $projectFloorPlan->name,
                                'image_path' => $projectFloorPlan->image_path,
                                'order' => $projectFloorPlan->sort_order,
                                'is_from_project' => true,
                                'project_floor_plan_id' => $projectFloorPlanId
                            ]);
                        }
                    }
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
                  // Handle additional documents (PDF/images, No compression)
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
            if( $listingStatus == 'published'){
                dispatch(new CheckSearchAlerts());
            }
                DB::commit();

                $listing->load(['propertyType', 'area', 'agent', 'owner', 'developer', 'addedBy', 'floorPlans', 'galleryImages','additionalDocuments']);

                $this->clearCache();

                \Log::info('Property creation completed successfully');

                return ApiResponse::success(
                    new ListingResource($listing),
                    $listingStatus === 'draft' ? 'Listing saved as draft successfully' : 'Listing published successfully', 
                    201
                );
            } catch (\Exception $e) {
                dd($e->getMessage());
                DB::rollBack();
                
                return ApiResponse::error('Failed to create listing: ' . $e->getMessage());
            }
        }
   
    public function show($listing): JsonResponse
    {
        try {
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
            dd($e->getMessage());
            return $this->fallbackShow($listing, $e);
        }
    }

    private function getListingData($listingId): array
    {
        $user = Auth::user();
        $listing = Listing::with([
            'propertyType', 
            'agent', 
            'owner', 
            'developer', 
            'addedBy', 
            'floorPlans', 
            'galleryImages',
            'project',
             'additionalDocuments',
            'area' => function($query) {
                $query->with(['parent.parent.parent', 'child']);
            }
        ])->find($listingId);
        
        if (!$listing) {
            throw new \Exception('Listing Not found');
        }

        $listing->load(['area.parentRecursive'],'area');
        
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
    }

public function update(ListingRequest $request, $listingId): JsonResponse
{
    try {
        ini_set('memory_limit', '1024M');

        

        DB::beginTransaction();

        $user = Auth::user();
        $listing = Listing::find($listingId);
        
        if (!$listing) {
            return ApiResponse::error('Listing Not found', 404);
        }

        // Check if user has permission to update this listing
        if ($listing->added_by !== $user->id && $listing->agent_id !== $user->id && ! $user->hasRole('super_admin') && !$user->canEditListings($listing->agent_id)) {
            return ApiResponse::error('You are Not authorized to update this listing', 403);
        }
  
            $oldHotDealStatus = $listing->is_hot_deal;
            $newHotDealStatus = $request->has('is_hot_deal') ? $request->is_hot_deal : $oldHotDealStatus;
            
            // Handle hot deal status change first
            if ($newHotDealStatus !== $oldHotDealStatus) {
                $this->handleHotDealStatus($listing, $newHotDealStatus, $oldHotDealStatus);
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
         unset($data['is_hot_deal']);
        unset($data['floor_plans']);
        unset($data['floor_plan_names']);
        unset($data['gallery']);
        unset($data['hero_image']);
         unset($data['additional_documents']);
   if($listing->completion_status=='Completed'){
            $data['payment_plan']=null;
        }
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
        if ($request->hasFile('floor_plans') ) {
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
                // \Log::warning('Gallery image Not found for hero image', [
                //     'gallery_image_id' => $heroGalleryImageId
                // ]);
            }
        }
         if ($request->has('imported_floor_plans')) {
            foreach ($request->imported_floor_plans as $importedPlanData) {
               $projectFloorPlan = \App\Models\FloorPlanImage::find($importedPlanData['project_floor_plan_id']);
                        if ($projectFloorPlan) {
                            $listing->floorPlans()->create([
                                'name' => $importedPlanData['name']??$projectFloorPlan->name,
                                'image_path' => $projectFloorPlan->image_path,
                                'order' => $projectFloorPlan->sort_order,
                                'is_from_project' => true,
                                'project_floor_plan_id' => $importedPlanData['project_floor_plan_id']
                            ]);
                        }
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
        $listing->load(['propertyType', 'area', 'agent', 'owner', 'developer', 'addedBy', 'floorPlans', 'galleryImages','additionalDocuments']);

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
        \Log::error('Failed to update listing', [
            'listing_id' => $listingId,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return ApiResponse::error('Failed to update listing: ' . $e->getMessage());
    }
}
    public function destroy($listing): JsonResponse
    {
        try {
            $user = Auth::user();
            $listing = Listing::find($listing);
            
            if (!$listing) {
                return ApiResponse::error('Listing Not found', 404);
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
            foreach ($listing->floorPlans->whereNull('project_floor_plan_id') as $floorPlan) {
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
            foreach ($listing->additionalDocuments as $galleryImage) {
                if ($galleryImage->path) {
                    ImageHelper::deleteImage($galleryImage->path);
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
            $result = $this->getListingData($listing);
            return ApiResponse::success(
                new ListingResource($result['listing']),
                'Listing retrieved successfully (cache fallback)',
                200,
                $result['permissions']
            );
        } catch (\Exception $fallbackError) {
            \Log::error('Listing fallback error: ' . $fallbackError->getMessage());
            return ApiResponse::error('Failed to retrieve listing');
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
                return ApiResponse::error('Listing or floor plan Not found', 404);
            }
            
            if ($user->hasRole('sales') && $listing->agent_id !== $user->id) {
                return ApiResponse::error('Access denied', 403);
            }
            
            // Check if floor plan belongs to listing
            if ($floorPlan->floor_planable_id !== $listing->id || $floorPlan->floor_planable_type !== Listing::class) {
                return ApiResponse::error('Floor plan Not found for this listing', 404);
            }
            
            if ($floorPlan->image_path && $floorPlan->is_from_project==0) {
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
                return ApiResponse::error('Listing or gallery image Not found', 404);
            }
            
            if ($user->hasRole('sales') && $listing->agent_id !== $user->id) {
                return ApiResponse::error('Access denied', 403);
            }
            
            // Check if gallery image belongs to listing
            if ($galleryImage->imageable_id !== $listing->id || $galleryImage->imageable_type !== Listing::class) {
                return ApiResponse::error('Gallery image Not found for this listing', 404);
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

    /**
     * Update floor plan order
     */
    public function updateFloorPlaNorder(Request $request, $listing): JsonResponse
    {
        try {
            $user = Auth::user();
            $listing = Listing::find($listing);
            
            if (!$listing) {
                return ApiResponse::error('Listing Not found', 404);
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
                return ApiResponse::error('You canNot assign an agent to a property that does Not belong to your hierarchy', 403);
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
                        $fail('You canNot assign this agent because they are Not under your hierarchy.');
                    }
                }
            }],
            'Notes' => 'nullable|string|max:1000'
        ]);

        $oldAgent = $property->agent;
        $newAgent = User::findOrFail($request->agent_id);

        if ($oldAgent && $oldAgent->id == $newAgent->id) {
            return ApiResponse::error('Property is already assigned to this agent', 422);
        }

        $property->update([
            'agent_id' => $request->agent_id,
            'assignment_Notes' => $request->Notes,
            'assigned_by' => $currentUser->id,
            'assigned_at' => Now()
        ]);

        // إشعارات كما في الكود السابق
        if ($newAgent) {
            try { $newAgent->Notify(new PropertyAssignedNotification($property, $currentUser)); } 
            catch (\Exception $e) { \Log::error($e->getMessage()); }
        }
        if ($oldAgent && $oldAgent->id != $newAgent->id) {
            try { $oldAgent->Notify(new PropertyUnassignedNotification($property, $currentUser)); } 
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
            'converted_at' => Now(),
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
            if($request->has('listings')){
                $user=auth()->user();
                $allowedAgentIds = $user->getAllSubordinatesIds();
                $agents=$agents->whereIn('id',$allowedAgentIds);
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
            return ApiResponse::error('You are Not authorized to update this listing', 403);
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
 public function deleteAdditionalDocument($listing, $document): JsonResponse
    {
        try {
            $user = Auth::user();
            $listing = Listing::find($listing);
            $doc = ListingAdditionalDocument::find($document);
            if (!$listing || !$doc) {
                return ApiResponse::error('Listing or document Not found', 404);
            }
            if ($doc->listing_id != $listing->id) {
                return ApiResponse::error('Document Not found for this listing', 404);
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
    public function changeOwner(Request $request, $id)
        {
            $request->validate([
                'owner_id' => 'required|exists:owners,id',
            ]);
        
            $listing = Listing::findOrFail($id);
        
            $listing->previous_owner_id = $listing->owner_id;
        
            $listing->owner_id = $request->owner_id;
        
            $listing->save();
        
            return response()->json([
                'message' => 'Owner changed successfully'
            ]);
        }
 public function store_search_alert(Request $request)
    {
        SearchAlert::create([
            'user_id' => auth()->id(),
            'filters' => $request->all(),
        ]);

        return response()->json([
            'message' => 'Search alert saved'
        ]);
    }
    
    
     public function generateOffer(Request $request, $id)
    {
        try {
            $property = Listing::findOrFail($id);
            $user = Auth::user();

            // Validate request
            $request->validate([
                'offer_data' => 'sometimes|array',
                'client_name' => 'sometimes|string',
                'client_email' => 'sometimes|email',
                'client_phone' => 'sometimes|string'
            ]);

            // Create offer record
            $offer = PropertyOffer::create([
                'property_id' => $property->id,
                'created_by' => $user->id,
                'offer_number' => PropertyOffer::generateOfferNumber(),
                'offer_data' => $request->all(),
                'status' => 'generated'
            ]);

            // Load creator relationship
            $offer->load('creator:id,name,email,avatar');

            \Log::info('Offer generated', [
                'offer_id' => $offer->id,
                'property_id' => $property->id,
                'created_by' => $user->id,
                'offer_number' => $offer->offer_number
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Offer generated successfully',
                'data' => [
                    'offer' => $offer,
                    'created_by' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'avatar' => $user->avatar
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to generate offer: ' . $e->getMessage());
            
            return response()->json([
                'status' => false,
                'message' => 'Failed to generate offer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all offers for a property
     */
    public function getOffers($id)
    {
        try {
            $offers = PropertyOffer::with('creator:id,name,email,avatar')
                ->where('property_id', $id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'status' => true,
                'data' => $offers
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch offers'
            ], 500);
        }
    }

    /**
     * Get a single offer
     */
    public function getOffer($propertyId, $offerId)
    {
        try {
            $offer = PropertyOffer::with('creator:id,name,email,avatar')
                ->where('property_id', $propertyId)
                ->findOrFail($offerId);

            return response()->json([
                'status' => true,
                'data' => $offer
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Offer Not found'
            ], 404);
        }
    }

}