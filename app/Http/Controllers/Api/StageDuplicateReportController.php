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
     *
     * Query: summary=1 (default) → counts only, fast.
     *        summary=0 → full pairs/groups (heavy; use sparingly).
     */
    public function __invoke(Request $request, StageDuplicateAnalysisService $analysis): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('super_admin')) {
            return ApiResponse::error('Forbidden', 403);
        }

        // Default to summary-only so the page opens quickly.
        $summaryOnly = ! $request->has('summary')
            || filter_var($request->query('summary'), FILTER_VALIDATE_BOOLEAN);

        // Summary is much lighter; full details still need headroom on large datasets.
        @set_time_limit($summaryOnly ? 120 : 300);

        try {
            $data = $analysis->analyze($summaryOnly);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(
            $data,
            $summaryOnly ? 'Stage duplicate summary' : 'Stage duplicate analysis'
        );
    }
}
