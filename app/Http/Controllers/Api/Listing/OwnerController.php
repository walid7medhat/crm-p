<?php

namespace App\Http\Controllers\Api\Listing;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Http\Requests\Listing\OwnerRequest;
use App\Http\Resources\Listing\OwnerResource;
use App\Models\Owner;
use App\Models\Area;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

class OwnerController extends Controller
{
    // Cache constants
    const CACHE_TTL = 3600; // ساعة واحدة
    const CACHE_PREFIX = 'owners_';
    const CACHE_TAG = 'owners';

    public function __construct()
    {
        $this->middleware('permission:owners-list', ['only' => [ 'show']]);
        $this->middleware('permission:owners-create', ['only' => ['store']]);
        $this->middleware('permission:owners-edit', ['only' => ['update']]);
        $this->middleware('permission:owners-delete', ['only' => ['destroy']]);
    }

    /**
     * Get all owners - مع الكاش
     */
public function index(Request $request): JsonResponse
{
    try {
        $user = Auth::user();
        $userId = $user->id;
        
        // مفتاح الكاش بيكون مميز لكل مستخدم
        $cacheKey = self::CACHE_PREFIX . 'user_' . $userId . '_index_' . md5(serialize($request->all()));
        
        // استخدام cache tags إذا كان مدعوماً
        if (method_exists(Cache::getStore(), 'tags')) {
            $owners = Cache::tags([self::CACHE_TAG, self::CACHE_TAG . '_user_' . $userId])->remember($cacheKey, self::CACHE_TTL, function () use ($request, $user) {
                return $this->getOwnersData($request, $user);
            });
        } else {
            $owners = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($request, $user) {
                return $this->getOwnersData($request, $user);
            });
        }
        
        return ApiResponse::success(
            OwnerResource::collection($owners),
            'Owners retrieved successfully'
        );
    } catch (\Exception $e) {
        // Fallback بدون cache
        return $this->fallbackIndex($request, $e);
    }
}
    /**
     * Get owners data - منفصلة للكاش
     */
    private function getOwnersData(Request $request)
    {
        $user = Auth::user();
        
        $query = Owner::with('location');
        if(!($user->hasRole('admin') || $user->hasRole('super_admin'))){
                $query->where('added_by', $user->id);
        }
                     
        // Search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%")
                  ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
            });
        }
        
        // Filter by residency status
        if ($request->has('residency_status')) {
            $query->where('residency_status', $request->residency_status);
        }
        
        // Filter by nationality
        if ($request->has('nationality')) {
            $query->where('nationality', $request->nationality);
        }
        
        // Filter by location
        if ($request->has('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        return $query->orderBy('created_at','desc')->get();
    }

    /**
     * Create a new owner - مع clear للكاش
     */
     
     /**
 * Get properties for a specific owner
 */
public function getOwnerProperties(Owner $owner): JsonResponse
{
    try {
        $user = Auth::user();
        
        // Check permissions
        if ($owner->added_by !== $user->id && !$user->hasRole('super_admin')) {
            return ApiResponse::error('Access denied', 403);
        }
        
        // Check if owner has properties relationship
        if (!method_exists($owner, 'properties')) {
            return ApiResponse::error('Properties relationship not defined', 500);
        }
        
        // Get active properties (not sold/archived)
        $properties = $owner->properties()
            ->where('is_active', true)
            ->where('status', '!=', 'converted')
            ->where(function($query) {
                $query->where('status', 'active')
                      ->orWhere('status', 'pending')
                      ->orWhere('status', 'draft');
            })
            ->with(['area', 'propertyType', 'images', 'listingStatus'])
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Or if properties are in Listings model
        // $properties = \App\Models\Listing::where('owner_id', $owner->id)
        //     ->where('is_active', true)
        //     ->where('status', '!=', 'converted')
        //     ->with(['property', 'property.area', 'property.propertyType'])
        //     ->get();
        
        return ApiResponse::success(
            $properties,
            'Owner properties retrieved successfully'
        );
        
    } catch (\Exception $e) {
        \Log::error('Error getting owner properties: ' . $e->getMessage());
        return ApiResponse::error('Failed to retrieve owner properties: ' . $e->getMessage());
    }
}


    public function store(OwnerRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            // Handle file uploads
            $fileFields = ['id_front', 'id_back', 'visa_copy', 'passport_copy'];
            $filePaths = [];
            $data = array_diff_key($data, array_flip($fileFields));

            // Handle additional documents (multiple)
            $additionalDocumentsMeta = [];
            if ($request->hasFile('additional_documents')) {
                foreach ($request->file('additional_documents') as $file) {
                    if (!$file) {
                        continue;
                    }
                    $path = $file->store("owners/documents", 'public');
                    $additionalDocumentsMeta[] = [
                        'name' => $file->getClientOriginalName(),
                        'path' => $path,
                    ];
                }
            }
            // Remove raw additional_documents from data; we'll store metadata instead
            unset($data['additional_documents']);

            foreach ($fileFields as $field) {
                if ($request->hasFile($field)) {
                    $file = $request->file($field);
                    $path = $file->store("owners/documents", 'public');
                    $filePaths[$field . '_path'] = $path;
                }
            }
            
            // Handle avatar upload
            if ($request->hasFile('avatar')) {
                $avatar = $request->file('avatar');
                $avatarPath = $avatar->store("owners/avatars", 'public');
                $data['avatar_path'] = $avatarPath;
            }
            
            // Remove avatar from data array as we're using avatar_path
            unset($data['avatar']);
            
            if (!empty($additionalDocumentsMeta)) {
                $data['additional_documents'] = $additionalDocumentsMeta;
            }

            // Create owner with file paths
            $owner = Owner::create(array_merge($data, $filePaths, [
                'added_by' => auth()->user()->id
            ]));
            
            $owner->load('location');

            // مسح الكاش المتعلق بالـ owners
            $this->clearCache();

            return ApiResponse::success(
                new OwnerResource($owner),
                'Owner created successfully', 
                201
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create owner: ' . $e->getMessage());
        }
    }

    /**
     * Get a single owner - مع الكاش
     */
    public function show(Owner $owner): JsonResponse
{
    try {
        $user = Auth::user();
        
        // If user has sales role, check if they added this owner
        if ($owner->added_by !== $user->id && !$user->hasRole(['super_admin', 'admin'])) {
            return ApiResponse::error('Access denied', 403);
        }
        
        $cacheKey = self::CACHE_PREFIX . 'user_' . $user->id . '_show_' . $owner->id;
        
        if (method_exists(Cache::getStore(), 'tags')) {
            $cachedOwner = Cache::tags([self::CACHE_TAG, self::CACHE_TAG . '_user_' . $user->id])->remember($cacheKey, self::CACHE_TTL, function () use ($owner) {
                return $owner->load('location', 'addedBy');
            });
        } else {
            $cachedOwner = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($owner) {
                return $owner->load('location', 'addedBy');
            });
        }
        
        return ApiResponse::success(
            new OwnerResource($cachedOwner),
            'Owner retrieved successfully'
        );
    } catch (\Exception $e) {
        // Fallback بدون cache
        return ApiResponse::success(
            new OwnerResource($owner->load('location', 'addedBy')),
            'Owner retrieved successfully (cache fallback)'
        );
    }
}
    /**
     * Update an owner - مع clear للكاش
     */
    public function update(OwnerRequest $request, Owner $owner): JsonResponse
    {
        try {
            $user = Auth::user();
            
           if ($owner->added_by !== $user->id && !$user->hasRole('super_admin')) {

                return ApiResponse::error('Access denied', 403);
            }
            
            $data = $request->validated();
            
            // Remove file fields from data
            unset($data['id_front'], $data['id_back'], $data['visa_copy'], $data['passport_copy'], $data['additional_documents']);
            
            // Handle file uploads
            $fileFields = [
                'id_front' => 'id_front_path',
                'id_back' => 'id_back_path', 
                'visa_copy' => 'visa_copy_path',
                'passport_copy' => 'passport_copy_path'
            ];
            
            foreach ($fileFields as $field => $pathField) {
                if ($request->hasFile($field)) {
                    // Delete old file
                    if ($owner->$pathField && Storage::disk('public')->exists($owner->$pathField)) {
                        Storage::disk('public')->delete($owner->$pathField);
                    }
                    
                    // Store new file
                    $file = $request->file($field);
                    $path = $file->store("owners/documents", 'public');
                    $data[$pathField] = $path;
                }
            }
            
            // Handle additional documents (append to existing)
            $existingAdditionalDocs = $owner->additional_documents ?? [];
            $additionalDocumentsMeta = [];
            if ($request->hasFile('additional_documents')) {
                foreach ($request->file('additional_documents') as $file) {
                    if (!$file) {
                        continue;
                    }
                    $path = $file->store("owners/documents", 'public');
                    $additionalDocumentsMeta[] = [
                        'name' => $file->getClientOriginalName(),
                        'path' => $path,
                    ];
                }
            }
            if (!empty($additionalDocumentsMeta)) {
                $data['additional_documents'] = array_merge($existingAdditionalDocs, $additionalDocumentsMeta);
            }

            // Handle avatar upload
            if ($request->hasFile('avatar')) {
                // Delete old avatar
                if ($owner->avatar_path && Storage::disk('public')->exists($owner->avatar_path)) {
                    Storage::disk('public')->delete($owner->avatar_path);
                }
                
                // Store new avatar
                $avatar = $request->file('avatar');
                $avatarPath = $avatar->store("owners/avatars", 'public');
                $data['avatar_path'] = $avatarPath;
            }
            
            // Remove avatar from data array
            unset($data['avatar']);
            
            $owner->update($data);
            $owner->load('location');

            // مسح الكاش المتعلق بهذا الـ owner والـ owners عامة
            $this->clearCache();
            $this->clearSpecificCache($owner->id);

            return ApiResponse::success(
                new OwnerResource($owner),
                'Owner updated successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update owner: ' . $e->getMessage());
        }
    }

    /**
     * Delete an owner - مع clear للكاش
     */
    public function destroy(Owner $owner): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // If user has sales role, check if they added this owner
           if ($owner->added_by !== $user->id && !$user->hasRole('super_admin')) {

                return ApiResponse::error('Access denied', 403);
            }
            
            $ownerId = $owner->id;
            $owner->delete();

            // مسح الكاش المتعلق بهذا الـ owner والـ owners عامة
            $this->clearCache();
            $this->clearSpecificCache($ownerId);

            return ApiResponse::success(null, 'Owner deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete owner: ' . $e->getMessage());
        }
    }

    /**
     * Get available locations based on residency status - مع الكاش
     */
    public function getLocationsByResidency(Request $request): JsonResponse
    {
        try {
            $residencyStatus = $request->get('residency_status', 'resident');
            
            if (!in_array($residencyStatus, ['resident', 'non_resident'])) {
                return ApiResponse::error('Invalid residency status. Must be: resident or non_resident');
            }

            $cacheKey = self::CACHE_PREFIX . 'locations_' . $residencyStatus;
            
            // استخدام cache tags إذا كان مدعوماً
            if (method_exists(Cache::getStore(), 'tags')) {
                $locations = Cache::tags([self::CACHE_TAG])->remember($cacheKey, self::CACHE_TTL, function () use ($residencyStatus) {
                    return $this->getLocationsData($residencyStatus);
                });
            } else {
                $locations = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($residencyStatus) {
                    return $this->getLocationsData($residencyStatus);
                });
            }

            return ApiResponse::success(
                $locations,
                "Available locations for {$residencyStatus} owners retrieved successfully"
            );
        } catch (\Exception $e) {
            // Fallback بدون cache
            return ApiResponse::error('Failed to retrieve locations: ' . $e->getMessage());
        }
    }

    /**
     * Get locations data - منفصلة للكاش
     */
    private function getLocationsData(string $residencyStatus)
    {
        if ($residencyStatus === 'resident') {
            return Area::where('type', 'city')
               ->where('id','!=',1024)
                ->orderBy('name')
                ->get(['id', 'name', 'type']);
        } else {
            return Area::where('type', 'country')
                ->orderBy('name')
                ->get(['id', 'name', 'type']);
        }
    }

    /**
     * Get statistics for owners - مع الكاش
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $cacheKey = self::CACHE_PREFIX . 'stats_' . Auth::id();
            
            // استخدام cache tags إذا كان مدعوماً
            if (method_exists(Cache::getStore(), 'tags')) {
                $stats = Cache::tags([self::CACHE_TAG])->remember($cacheKey, 900, function () {
                    return $this->getStatisticsData();
                });
            } else {
                $stats = Cache::remember($cacheKey, 900, function () {
                    return $this->getStatisticsData();
                });
            }
            
            return ApiResponse::success(
                $stats,
                'Owners statistics retrieved successfully'
            );
        } catch (\Exception $e) {
            // Fallback بدون cache
            return ApiResponse::error('Failed to retrieve owners statistics: ' . $e->getMessage());
        }
    }

    /**
     * Get statistics data - منفصلة للكاش
     */
    private function getStatisticsData(): array
    {
        $user = Auth::user();
        
        if ($user->hasRole('sales')) {
            // sales sees only their statistics
            $totalOwners = Owner::where('added_by', $user->id)->count();
            $residentOwners = Owner::where('added_by', $user->id)
                ->where('residency_status', 'resident')->count();
            $nonResidentOwners = Owner::where('added_by', $user->id)
                ->where('residency_status', 'non_resident')->count();
        } else {
            // Other roles see all statistics
            $totalOwners = Owner::count();
            $residentOwners = Owner::where('residency_status', 'resident')->count();
            $nonResidentOwners = Owner::where('residency_status', 'non_resident')->count();
        }

        return [
            'total_owners' => $totalOwners,
            'resident_owners' => $residentOwners,
            'non_resident_owners' => $nonResidentOwners,
            'resident_percentage' => $totalOwners > 0 ? round(($residentOwners / $totalOwners) * 100, 2) : 0,
            'non_resident_percentage' => $totalOwners > 0 ? round(($nonResidentOwners / $totalOwners) * 100, 2) : 0,
        ];
    }

    /**
     * دالة مساعدة لمسح كل الكاش - الإصلاح النهائي
     */
    private function clearCache(): void
    {
        try {
            // استخدام cache tags إذا كان مدعوماً (أفضل حل)
            if (method_exists(Cache::getStore(), 'tags')) {
                Cache::tags([self::CACHE_TAG])->flush();
                \Log::info('Owners cache cleared using tags');
            } else {
                // Fallback لمسح الكاش بدون tags
                $this->clearCacheWithoutTags();
            }
        } catch (\Exception $e) {
            \Log::warning('Owners cache clear error: ' . $e->getMessage());
            
            // Fallback نهائي - مسح الكاش كله
            Cache::flush();
            \Log::info('Full cache flush as fallback');
        }
    }

    /**
     * مسح الكاش بدون استخدام tags
     */
    private function clearCacheWithoutTags(): void
    {
        try {
            // 1. مسح إحصائيات المستخدم الحالي
            Cache::forget(self::CACHE_PREFIX . 'stats_' . Auth::id());
            
            // 2. مسح الـ locations cache
            Cache::forget(self::CACHE_PREFIX . 'locations_resident');
            Cache::forget(self::CACHE_PREFIX . 'locations_non_resident');
            
            // 3. مسح جميع مفاتيح الـ index
            $this->clearAllIndexCache();
            
            \Log::info('Owners cache cleared without tags');
        } catch (\Exception $e) {
            \Log::warning('Cache clear without tags error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * مسح جميع مفاتيح index cache - الإصلاح
     */
    private function clearAllIndexCache(): void
    {
        try {
            $cacheDriver = config('cache.default');
            
            if ($cacheDriver === 'redis') {
                $this->clearRedisIndexCache();
            } 
            elseif ($cacheDriver === 'file') {
                $this->clearFileIndexCache();
            }
            else {
                // لـ database وغيرها - نستخدم flush مع log
                Cache::flush();
                \Log::info('All cache flushed for database driver');
            }
            
            \Log::info('All owners index cache cleared for driver: ' . $cacheDriver);
        } catch (\Exception $e) {
            \Log::warning('Index cache clear error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * مسح index cache لـ Redis
     */
    private function clearRedisIndexCache(): void
    {
        $redis = Cache::getRedis();
        $iterator = null;
        $pattern = self::CACHE_PREFIX . 'index_*';
        $deletedCount = 0;
        
        do {
            $keys = $redis->scan($iterator, $pattern, 100);
            if (!empty($keys)) {
                $redis->del($keys);
                $deletedCount += count($keys);
            }
        } while ($iterator > 0);
        
        \Log::info("Deleted {$deletedCount} Redis index keys with pattern: {$pattern}");
    }

    /**
     * مسح index cache لـ File
     */
    private function clearFileIndexCache(): void
    {
        $storage = Storage::disk('framework_cache');
        $files = $storage->files();
        $deletedCount = 0;
        
        foreach ($files as $file) {
            if (str_contains($file, self::CACHE_PREFIX . 'index_')) {
                $storage->delete($file);
                $deletedCount++;
            }
        }
        
        \Log::info("Deleted {$deletedCount} file cache index keys");
    }

    /**
     * دالة مساعدة لمسح كاش سجل معين
     */
    private function clearSpecificCache(int $ownerId): void
    {
        try {
            Cache::forget(self::CACHE_PREFIX . 'show_' . $ownerId);
            \Log::info('Cleared specific cache for owner: ' . $ownerId);
        } catch (\Exception $e) {
            \Log::warning('Owner cache clear error: ' . $e->getMessage());
        }
    }

    /**
     * Fallback للـ index بدون cache
     */
    private function fallbackIndex(Request $request, \Exception $e = null): JsonResponse
    {
        try {
            $owners = $this->getOwnersData($request);
            return ApiResponse::success(
                OwnerResource::collection($owners),
                'Owners retrieved successfully (cache fallback)'
            );
        } catch (\Exception $fallbackError) {
            \Log::error('Owners fallback error: ' . $fallbackError->getMessage());
            return ApiResponse::error('Failed to retrieve owners');
        }
    }

    /**
     * دالة لمسح الكاش يدوياً
     */
    public function clearCacheManual(): JsonResponse
    {
        try {
            $this->clearCache();
            return ApiResponse::success(null, 'Owners cache cleared successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to clear cache: ' . $e->getMessage());
        }
    }

    /**
     * دالة لفحص حالة الكاش (للت debugging)
     */
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
            
            return ApiResponse::success($status, 'Cache status retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to check cache status: ' . $e->getMessage());
        }
    }
}