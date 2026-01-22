<?php

namespace App\Http\Controllers\Api\Listing;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Http\Requests\Listing\FeatureRequest;
use App\Http\Resources\Listing\FeatureResource;
use App\Models\Feature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class FeatureController extends Controller
{
    // Cache constants
    const CACHE_TTL = 86400;
    const CACHE_PREFIX = 'features_';

    public function __construct()
    {
        $this->middleware('permission:features-list', ['only' => [ 'show']]);
        $this->middleware('permission:features-create', ['only' => ['store']]);
        $this->middleware('permission:features-edit', ['only' => ['update']]);
        $this->middleware('permission:features-delete', ['only' => ['destroy']]);
    }

    /**
     * Get all Features - مع الكاش
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $cacheKey = self::CACHE_PREFIX . 'index';
            
            $features = Cache::remember($cacheKey, self::CACHE_TTL, function () {
                return Feature::orderBy('name')->get();
            });
            
            return ApiResponse::success(
                FeatureResource::collection($features),
                'Features retrieved successfully'
            );
        } catch (\Exception $e) {
            // Fallback بدون cache
            return ApiResponse::success(
                FeatureResource::collection(Feature::orderBy('name')->get()),
                'Features retrieved successfully (cache fallback)'
            );
        }
    }

    /**
     * Create a new Feature -
     */
    public function store(FeatureRequest $request): JsonResponse
    {
        try {
              $data = $request->validated();
             // Handle avatar upload
            if ($request->hasFile('avatar')) {
                $avatar = $request->file('avatar');
                $avatarPath = $avatar->store("images/features", 'public');
                $data['img'] = $avatarPath;
            }
            
            // Remove avatar from data array as we're using avatar_path
            unset($data['avatar']);
            $feature = Feature::create($data + [
                'added_by' => auth()->user()->id
            ]);

            // مسح الكاش المتعلق بالـ Features
            $this->clearCache();
             
            return ApiResponse::success(
                new FeatureResource($feature),
                'Feature created successfully', 
                201
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create Feature: ' . $e->getMessage());
        }
    }

    /**
     * Get a single Feature - 
     */
    public function show(Feature $feature): JsonResponse
    {
        try {
            $cacheKey = self::CACHE_PREFIX . 'show_' . $feature->id;
            
            $cachedFeature = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($feature) {
                return $feature;
            });
            
            return ApiResponse::success(
                new FeatureResource($cachedFeature),
                'Feature retrieved successfully'
            );
        } catch (\Exception $e) {
            // Fallback بدون cache
            return ApiResponse::success(
                new FeatureResource($feature),
                'Feature retrieved successfully (cache fallback)'
            );
        }
    }

    /**
     * Update a Feature - 
     */
    public function update(FeatureRequest $request, Feature $feature): JsonResponse
    {
        try {
              $data = $request->validated();
            
            // Handle avatar upload
            if ($request->hasFile('avatar')) {
                // Delete old avatar
                if ($feature->img && Storage::disk('public')->exists($feature->img)) {
                    Storage::disk('public')->delete($feature->img);
                }
                
                // Store new avatar
                $avatar = $request->file('avatar');
                $avatarPath = $avatar->store("images/features", 'public');
                $data['img'] = $avatarPath;
            }
            
            // Remove avatar from data array
            unset($data['avatar']);
            
            $feature->update($data);

            // مسح الكاش المتعلق بهذا الـ Feature والـ Features عامة
            $this->clearCache();
            $this->clearSpecificCache($feature->id);

            return ApiResponse::success(
                new FeatureResource($feature),
                'Feature updated successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update Feature: ' . $e->getMessage());
        }
    }

    /**
     * Delete a Feature - 
     */
    public function destroy(Feature $feature): JsonResponse
    {
        try {
             if ($feature->img && Storage::disk('public')->exists($feature->img)) {
                Storage::disk('public')->delete($feature->img);
            }
            
            $featureId = $feature->id;
            $feature->delete();

            $this->clearCache();
            $this->clearSpecificCache($featureId);

            return ApiResponse::success(null, 'Feature deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete Feature: ' . $e->getMessage());
        }
    }

   
    private function clearCache(): void
    {
        try {
            Cache::forget(self::CACHE_PREFIX . 'index');
            $this->clearCacheByPattern(self::CACHE_PREFIX . 'show_*');
        } catch (\Exception $e) {
            \Log::warning('Features cache clear error: ' . $e->getMessage());
        }
    }

    
    private function clearSpecificCache(int $featureId): void
    {
        try {
            Cache::forget(self::CACHE_PREFIX . 'show_' . $featureId);
        } catch (\Exception $e) {
            \Log::warning('Feature cache clear error: ' . $e->getMessage());
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
            return ApiResponse::success(null, 'Features cache cleared successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to clear cache: ' . $e->getMessage());
        }
    }
}