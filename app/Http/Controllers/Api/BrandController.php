<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Support\Brand\BrandContext;
use Illuminate\Http\JsonResponse;

class BrandController extends Controller
{
    public function show(BrandContext $brand): JsonResponse
    {
        return ApiResponse::success($brand->toFrontendArray(), 'Brand resolved');
    }
}
