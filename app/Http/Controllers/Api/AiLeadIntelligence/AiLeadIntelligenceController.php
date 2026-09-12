<?php

namespace App\Http\Controllers\Api\AiLeadIntelligence;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\AiLeadIntelligence\AiLeadIntelligenceContract;
use App\Services\AiLeadIntelligence\AiLeadIntelligenceRunService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiLeadIntelligenceController extends Controller
{
    public function __construct(
        protected AiLeadIntelligenceRunService $runs
    ) {
        $this->middleware(function ($request, $next) {
            $user = auth()->user();

            if (!$user) {
                return ApiResponse::error('Unauthorized', 401);
            }

            if (!$this->canAccessAli($user)) {
                return ApiResponse::error('You do not have permission to view AI Lead Intelligence.', 403);
            }

            return $next($request);
        });
    }

    private function canAccessAli($user): bool
    {
        return method_exists($user, 'hasRole') && $user->hasRole('super_admin');
    }

    public function overview(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->runs->overviewPayload(),
            'AI Lead Intelligence overview'
        );
    }

    public function priorityLeads(Request $request): JsonResponse
    {
        $payload = $this->runs->overviewPayload();

        return ApiResponse::success(
            $payload['priority_leads'] ?? AiLeadIntelligenceContract::listSection(),
            'Priority leads'
        );
    }

    public function atRisk(Request $request): JsonResponse
    {
        $payload = $this->runs->overviewPayload();

        return ApiResponse::success(
            $payload['at_risk'] ?? AiLeadIntelligenceContract::listSection(),
            'At-risk leads'
        );
    }

    public function propertyOpportunities(Request $request): JsonResponse
    {
        $payload = $this->runs->overviewPayload();

        return ApiResponse::success(
            $payload['property_opportunities'] ?? AiLeadIntelligenceContract::propertyOpportunitiesSection(),
            'Property opportunities'
        );
    }

    public function neglected(Request $request): JsonResponse
    {
        $payload = $this->runs->overviewPayload();

        return ApiResponse::success(
            $payload['neglected'] ?? AiLeadIntelligenceContract::neglectedSection(),
            'Neglected leads'
        );
    }

    public function actionsToday(Request $request): JsonResponse
    {
        $payload = $this->runs->overviewPayload();

        return ApiResponse::success(
            $payload['actions_today'] ?? AiLeadIntelligenceContract::listSection(),
            'Today actions'
        );
    }

    public function refresh(Request $request): JsonResponse
    {
        try {
            $result = $this->runs->queueRefresh(auth()->id());
            $run = $result['run'];

            return ApiResponse::success([
                'phase' => AiLeadIntelligenceContract::PHASE,
                'analysis_status' => $run->status === 'queued'
                    ? AiLeadIntelligenceContract::STATUS_QUEUED
                    : AiLeadIntelligenceContract::STATUS_RUNNING,
                'run_status' => $run->status,
                'run_id' => $run->id,
                'queued' => $result['queued'],
                'reused_existing_run' => $result['reused'],
                'total_eligible' => (int) $run->total_eligible,
                'message' => $result['reused']
                    ? 'A refresh is already in progress.'
                    : 'CRM intelligence aggregation has been queued.',
                'last_updated_at' => null,
            ], $result['reused'] ? 'Refresh already running' : 'Refresh queued');
        } catch (\Throwable $e) {
            return ApiResponse::error('Unable to queue intelligence refresh.', 500);
        }
    }
}
