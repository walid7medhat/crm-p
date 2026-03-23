<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\AbuDhabiBenchmarkService;
use Illuminate\Http\JsonResponse;

class AbuDhabiBenchmarkController extends Controller
{
    public function __construct(private readonly AbuDhabiBenchmarkService $benchmarkService)
    {
    }

    public function index(): JsonResponse
    {
        return ApiResponse::success($this->benchmarkService->dataset(), 'Abu Dhabi benchmarks retrieved successfully');
    }
}
