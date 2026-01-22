<?php

namespace App\Http\Controllers\Api\Listing;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Http\Requests\Listing\DeveloperRequest;
use App\Http\Resources\Listing\DeveloperResource;
use App\Models\Developer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

class DeveloperController extends Controller
{
    // Cache constants
    const CACHE_TTL = 3600; // ساعة واحدة
    const CACHE_PREFIX = 'developers_';
    const CACHE_TAG = 'developers';

    public function __construct()
    {
        $this->middleware('permission:developers-list', ['only' => ['show']]);
        $this->middleware('permission:developers-create', ['only' => ['store']]);
        $this->middleware('permission:developers-edit', ['only' => ['update']]);
        $this->middleware('permission:developers-delete', ['only' => ['destroy']]);
    }

    /**
     * Get all developers - PUBLIC (no authentication required) - مع الكاش
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $cacheKey = self::CACHE_PREFIX . 'index_' . md5(serialize($request->all()));
            
            // استخدام cache tags إذا كان مدعوماً
            if (method_exists(Cache::getStore(), 'tags')) {
                $developers = Cache::tags([self::CACHE_TAG])->remember($cacheKey, self::CACHE_TTL, function () use ($request) {
                    return $this->getDevelopersData($request);
                });
            } else {
                $developers = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($request) {
                    return $this->getDevelopersData($request);
                });
            }
            
            return ApiResponse::success(
                DeveloperResource::collection($developers),
                'Developers retrieved successfully'
            );
        } catch (\Exception $e) {
            // Fallback بدون cache
            return $this->fallbackIndex($request, $e);
        }
    }

    /**
     * Get developers data - منفصلة للكاش
     */
    private function getDevelopersData(Request $request)
    {
        // Start the query - no user restrictions
        $query = Developer::with('addedBy');
        
        // Search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Optional: Filter by added_by if needed
        if ($request->has('added_by')) {
            $query->where('added_by', $request->added_by);
        }

        return $query->orderBy('created_at')->get();
    }

    /**
     * Create a new developer - PROTECTED - مع clear للكاش
     */
    public function store(DeveloperRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            
            // Handle avatar upload
            if ($request->hasFile('avatar')) {
                $avatar = $request->file('avatar');
                $avatarPath = $avatar->store("developers/avatars", 'public');
                $data['avatar_path'] = $avatarPath;
            }
            
            // Remove avatar from data array as we're using avatar_path
            unset($data['avatar']);
            
            // Create developer
            $developer = Developer::create(array_merge($data, [
                'added_by' => auth()->id()
            ]));
            
            $developer->load('addedBy');

            // مسح الكاش المتعلق بالـ developers
            $this->clearCache();

            return ApiResponse::success(
                new DeveloperResource($developer),
                'Developer created successfully', 
                201
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create developer: ' . $e->getMessage());
        }
    }

    /**
     * Get a single developer - PUBLIC (no authentication required) - مع الكاش
     */
    public function show(Developer $developer): JsonResponse
    {
        try {
            $cacheKey = self::CACHE_PREFIX . 'show_' . $developer->id;
            
            // استخدام cache tags إذا كان مدعوماً
            if (method_exists(Cache::getStore(), 'tags')) {
                $cachedDeveloper = Cache::tags([self::CACHE_TAG])->remember($cacheKey, self::CACHE_TTL, function () use ($developer) {
                    return $developer->load('addedBy');
                });
            } else {
                $cachedDeveloper = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($developer) {
                    return $developer->load('addedBy');
                });
            }
            
            return ApiResponse::success(
                new DeveloperResource($cachedDeveloper),
                'Developer retrieved successfully'
            );
        } catch (\Exception $e) {
            // Fallback بدون cache
            return ApiResponse::success(
                new DeveloperResource($developer->load('addedBy')),
                'Developer retrieved successfully (cache fallback)'
            );
        }
    }

    /**
     * Update a developer - PROTECTED - مع clear للكاش
     */
    public function update(DeveloperRequest $request, Developer $developer): JsonResponse
    {
        try {
            $data = $request->validated();
            
            // Handle avatar upload
            if ($request->hasFile('avatar')) {
                // Delete old avatar
                if ($developer->avatar_path && Storage::disk('public')->exists($developer->avatar_path)) {
                    Storage::disk('public')->delete($developer->avatar_path);
                }
                
                // Store new avatar
                $avatar = $request->file('avatar');
                $avatarPath = $avatar->store("developers/avatars", 'public');
                $data['avatar_path'] = $avatarPath;
            }
            
            // Remove avatar from data array
            unset($data['avatar']);
            
            $developer->update($data);
            $developer->load('addedBy');

            // مسح الكاش المتعلق بهذا الـ developer والـ developers عامة
            $this->clearCache();
            $this->clearSpecificCache($developer->id);

            return ApiResponse::success(
                new DeveloperResource($developer),
                'Developer updated successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update developer: ' . $e->getMessage());
        }
    }

    /**
     * Delete a developer - PROTECTED - مع clear للكاش
     */
    public function destroy(Developer $developer): JsonResponse
    {
        try {
            // Delete avatar if exists
            if ($developer->avatar_path && Storage::disk('public')->exists($developer->avatar_path)) {
                Storage::disk('public')->delete($developer->avatar_path);
            }
            
            $developerId = $developer->id;
            $developer->delete();

            // مسح الكاش المتعلق بهذا الـ developer والـ developers عامة
            $this->clearCache();
            $this->clearSpecificCache($developerId);

            return ApiResponse::success(null, 'Developer deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete developer: ' . $e->getMessage());
        }
    }

    /**
     * Get statistics for developers - PUBLIC - مع الكاش
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $cacheKey = self::CACHE_PREFIX . 'stats';
            
            // استخدام cache tags إذا كان مدعوماً
            if (method_exists(Cache::getStore(), 'tags')) {
                $stats = Cache::tags([self::CACHE_TAG])->remember($cacheKey, 900, function () { // 15 دقيقة للإحصائيات
                    return $this->getStatisticsData();
                });
            } else {
                $stats = Cache::remember($cacheKey, 900, function () {
                    return $this->getStatisticsData();
                });
            }
            
            return ApiResponse::success(
                $stats,
                'Developers statistics retrieved successfully'
            );
        } catch (\Exception $e) {
            // Fallback بدون cache
            return ApiResponse::error('Failed to retrieve developers statistics: ' . $e->getMessage());
        }
    }

    /**
     * Get statistics data - منفصلة للكاش
     */
    private function getStatisticsData(): array
    {
        $totalDevelopers = Developer::count();
        $developersWithAvatar = Developer::whereNotNull('avatar_path')->count();
        $developersWithPhone = Developer::whereNotNull('phone')->count();

        return [
            'total_developers' => $totalDevelopers,
            'developers_with_avatar' => $developersWithAvatar,
            'developers_with_phone' => $developersWithPhone,
            'developers_without_avatar' => $totalDevelopers - $developersWithAvatar,
            'avatar_percentage' => $totalDevelopers > 0 ? round(($developersWithAvatar / $totalDevelopers) * 100, 2) : 0,
            'phone_percentage' => $totalDevelopers > 0 ? round(($developersWithPhone / $totalDevelopers) * 100, 2) : 0,
        ];
    }

    /**
     * دالة مساعدة لمسح كل الكاش
     */
    private function clearCache(): void
    {
        try {
            // استخدام cache tags إذا كان مدعوماً (أفضل حل)
            if (method_exists(Cache::getStore(), 'tags')) {
                Cache::tags([self::CACHE_TAG])->flush();
                \Log::info('Developers cache cleared using tags');
            } else {
                // Fallback لمسح الكاش بدون tags
                $this->clearCacheWithoutTags();
            }
        } catch (\Exception $e) {
            \Log::warning('Developers cache clear error: ' . $e->getMessage());
            
            // Fallback نهائي - مسح الكاش كله
            Cache::flush();
            \Log::info('Full cache flush as fallback for developers');
        }
    }

    /**
     * مسح الكاش بدون استخدام tags
     */
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
                // لـ database وغيرها - نستخدم flush
                Cache::flush();
                \Log::info('All cache flushed for database driver in developers');
            }
            
            \Log::info('Developers cache cleared without tags for driver: ' . $cacheDriver);
        } catch (\Exception $e) {
            \Log::warning('Developers cache clear without tags error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * مسح Redis cache
     */
    private function clearRedisCache(): void
    {
        $redis = Cache::getRedis();
        $iterator = null;
        $patterns = [
            self::CACHE_PREFIX . 'index_*',
            self::CACHE_PREFIX . 'show_*',
            self::CACHE_PREFIX . 'stats*'
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
        
        \Log::info("Deleted {$totalDeleted} Redis cache keys for developers");
    }

    /**
     * مسح File cache
     */
    private function clearFileCache(): void
    {
        $storage = Storage::disk('framework_cache');
        $files = $storage->files();
        $deletedCount = 0;
        
        $patterns = [
            self::CACHE_PREFIX . 'index_',
            self::CACHE_PREFIX . 'show_',
            self::CACHE_PREFIX . 'stats'
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
        
        \Log::info("Deleted {$deletedCount} file cache keys for developers");
    }

    /**
     * دالة مساعدة لمسح كاش سجل معين
     */
    private function clearSpecificCache(int $developerId): void
    {
        try {
            Cache::forget(self::CACHE_PREFIX . 'show_' . $developerId);
            \Log::info('Cleared specific cache for developer: ' . $developerId);
        } catch (\Exception $e) {
            \Log::warning('Developer cache clear error: ' . $e->getMessage());
        }
    }

    /**
     * Fallback للـ index بدون cache
     */
    private function fallbackIndex(Request $request, \Exception $e = null): JsonResponse
    {
        try {
            $developers = $this->getDevelopersData($request);
            return ApiResponse::success(
                DeveloperResource::collection($developers),
                'Developers retrieved successfully (cache fallback)'
            );
        } catch (\Exception $fallbackError) {
            \Log::error('Developers fallback error: ' . $fallbackError->getMessage());
            return ApiResponse::error('Failed to retrieve developers');
        }
    }

    /**
     * دالة لمسح الكاش يدوياً
     */
    public function clearCacheManual(): JsonResponse
    {
        try {
            $this->clearCache();
            return ApiResponse::success(null, 'Developers cache cleared successfully');
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
            
            return ApiResponse::success($status, 'Developers cache status retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to check cache status: ' . $e->getMessage());
        }
    }
}