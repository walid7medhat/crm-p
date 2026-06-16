<?php
// app/Http/Controllers/Api/DealCostSettingController.php

namespace App\Http\Controllers\Api\Listing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Listing\DealCostSettingRequest;
use App\Http\Resources\Listing\DealCostSettingResource;
use App\Models\DealCostSetting;
use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DealCostSettingController extends Controller
{
    const CACHE_TTL = 3600; // ساعة واحدة
    const CACHE_PREFIX = 'deal_cost_settings_';
    const CACHE_TAG = 'deal_cost_settings';

    public function __construct()
    {
        // صلاحيات الوصول
        $this->middleware('permission:settings-view', ['only' => ['index', 'show']]);
        $this->middleware('permission:settings-update', ['only' => ['update', 'updateMultiple']]);
    }

    /**
     * Get all deal cost settings - PROTECTED
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $cacheKey = self::CACHE_PREFIX . 'index_' . md5(serialize($request->all()));
            
            if (method_exists(Cache::getStore(), 'tags')) {
                $settings = Cache::tags([self::CACHE_TAG])->remember($cacheKey, self::CACHE_TTL, function () {
                    return DealCostSetting::with('updatedBy')
                        ->where('is_active', true)
                        ->orderBy('key')
                        ->get();
                });
            } else {
                $settings = Cache::remember($cacheKey, self::CACHE_TTL, function () {
                    return DealCostSetting::with('updatedBy')
                        ->where('is_active', true)
                        ->orderBy('key')
                        ->get();
                });
            }

            // تحويل البيانات إلى صيغة key => value لسهولة الاستخدام
            $formattedSettings = [];
            foreach ($settings as $setting) {
                $formattedSettings[$setting->key] = $setting->value;
            }

            return ApiResponse::success([
                'settings' => $formattedSettings,
                'details' => DealCostSettingResource::collection($settings)
            ], 'Deal cost settings retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error fetching deal cost settings: ' . $e->getMessage());
            return ApiResponse::error('Failed to retrieve settings: ' . $e->getMessage());
        }
    }

    /**
     * Get a single setting by key
     */
    public function show(string $key): JsonResponse
    {
        try {
            $cacheKey = self::CACHE_PREFIX . 'show_' . $key;
            
            if (method_exists(Cache::getStore(), 'tags')) {
                $setting = Cache::tags([self::CACHE_TAG])->remember($cacheKey, self::CACHE_TTL, function () use ($key) {
                    return DealCostSetting::with('updatedBy')
                        ->where('key', $key)
                        ->where('is_active', true)
                        ->firstOrFail();
                });
            } else {
                $setting = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($key) {
                    return DealCostSetting::with('updatedBy')
                        ->where('key', $key)
                        ->where('is_active', true)
                        ->firstOrFail();
                });
            }

            return ApiResponse::success(
                new DealCostSettingResource($setting),
                'Setting retrieved successfully'
            );

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Setting not found', 404);
        } catch (\Exception $e) {
            Log::error('Error fetching deal cost setting: ' . $e->getMessage());
            return ApiResponse::error('Failed to retrieve setting: ' . $e->getMessage());
        }
    }

    /**
     * Update multiple settings at once - PROTECTED
     */
    public function updateMultiple(DealCostSettingRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $userId = auth()->id();
            $updatedSettings = [];

            foreach ($validated as $key => $value) {
                // التحقق من وجود المفتاح
                $setting = DealCostSetting::where('key', $key)->first();
                
                if ($setting) {
                    // تحديث الإعداد الموجود
                    $setting->update([
                        'value' => $value,
                        'updated_by' => $userId,
                        'is_active' => true
                    ]);
                } else {
                    // إنشاء إعداد جديد
                    $setting = DealCostSetting::create([
                        'key' => $key,
                        'value' => $value,
                        'updated_by' => $userId,
                        'is_active' => true,
                        'description' => ucfirst(str_replace('_', ' ', $key))
                    ]);
                }

                $updatedSettings[$key] = $setting;
            }

            // مسح الكاش
            $this->clearCache();

            return ApiResponse::success(
                DealCostSettingResource::collection(collect($updatedSettings)),
                'Deal cost settings updated successfully'
            );

        } catch (\Exception $e) {
            Log::error('Error updating deal cost settings: ' . $e->getMessage());
            return ApiResponse::error('Failed to update settings: ' . $e->getMessage());
        }
    }

    /**
     * Update a single setting - PROTECTED
     */
    public function update(DealCostSettingRequest $request, string $key): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            // البحث عن المفتاح في البيانات المرسلة
            if (!isset($validated[$key])) {
                return ApiResponse::error("Key '{$key}' not found in request data");
            }

            $value = $validated[$key];
            $userId = auth()->id();

            $setting = DealCostSetting::where('key', $key)->first();
            
            if ($setting) {
                $setting->update([
                    'value' => $value,
                    'updated_by' => $userId,
                    'is_active' => true
                ]);
            } else {
                $setting = DealCostSetting::create([
                    'key' => $key,
                    'value' => $value,
                    'updated_by' => $userId,
                    'is_active' => true,
                    'description' => ucfirst(str_replace('_', ' ', $key))
                ]);
            }

            $setting->load('updatedBy');

            // مسح الكاش
            $this->clearCache();

            return ApiResponse::success(
                new DealCostSettingResource($setting),
                'Setting updated successfully'
            );

        } catch (\Exception $e) {
            Log::error('Error updating deal cost setting: ' . $e->getMessage());
            return ApiResponse::error('Failed to update setting: ' . $e->getMessage());
        }
    }

    /**
     * Clear cache
     */
    private function clearCache(): void
    {
        try {
            if (method_exists(Cache::getStore(), 'tags')) {
                Cache::tags([self::CACHE_TAG])->flush();
            } else {
                // مسح المفاتيح المحددة
                $keys = [
                    self::CACHE_PREFIX . 'index_*',
                    self::CACHE_PREFIX . 'show_*',
                ];
                
                foreach ($keys as $pattern) {
                    Cache::forget($pattern);
                }
            }
            Log::info('Deal cost settings cache cleared');
        } catch (\Exception $e) {
            Log::warning('Error clearing deal cost settings cache: ' . $e->getMessage());
        }
    }
}