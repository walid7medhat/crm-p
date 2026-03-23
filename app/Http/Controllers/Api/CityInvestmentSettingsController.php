<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\CityInvestmentSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CityInvestmentSettingsController extends Controller
{
    public function __construct(private readonly CityInvestmentSettingsService $settingsService)
    {
    }

    public function index(): JsonResponse
    {
        return ApiResponse::success($this->settingsService->all(), 'City settings retrieved successfully');
    }

    public function show(string $city): JsonResponse
    {
        $setting = $this->settingsService->getByCity($city);
        if (!$setting) {
            return ApiResponse::error('City settings not found', 404);
        }

        return ApiResponse::success($setting, 'City settings retrieved successfully');
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasRole(['admin', 'super_admin'])) {
            return ApiResponse::error('Unauthorized - only admins can update city settings', 403);
        }

        $request->validate([
            'city' => 'required|string|max:80',
            'purchase_price_min' => 'nullable|numeric|min:0',
            'purchase_price_max' => 'nullable|numeric|min:0',
            'down_payment_percent' => 'required|numeric|min:0|max:100',
            'loan_interest_percent' => 'required|numeric|min:0|max:100',
            'hold_years' => 'required|integer|min:1|max:60',
            'vacancy_rate_percent' => 'required|numeric|min:0|max:100',
            'is_default' => 'nullable|boolean',
        ]);

        if (
            $request->filled('purchase_price_min') &&
            $request->filled('purchase_price_max') &&
            $request->purchase_price_min > $request->purchase_price_max
        ) {
            return ApiResponse::error('purchase_price_min cannot be greater than purchase_price_max', 422);
        }

        $updated = $this->settingsService->updateCity($request->city, $request->all());
        return ApiResponse::success($updated, 'City settings updated successfully');
    }
}
