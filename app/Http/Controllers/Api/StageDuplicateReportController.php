<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\StageDuplicateAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StageDuplicateReportController extends Controller
{
    /**
     * Cross-stage lead duplicate analysis (Contacted / Qualified / Assigned).
     * Super Admin only — middleware + controller gate; no PII for anyone else.
     */
    public function __invoke(Request $request, StageDuplicateAnalysisService $analysis): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('super_admin')) {
            return ApiResponse::error('Forbidden', 403);
        }

        try {
            $data = $analysis->analyze();
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($data, 'Stage duplicate analysis');
    }
}
