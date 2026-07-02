<?php

namespace App\Http\Controllers\Api\AiSalesIntelligence;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\AiSalesIntelligence\AiAgentMetricResource;
use App\Models\AiSalesIntelligence\AiAgentAlert;
use App\Models\AiSalesIntelligence\AiAgentMetric;
use App\Models\AiSalesIntelligence\AiAgentObservation;
use App\Models\AiSalesIntelligence\AiAgentRanking;
use App\Models\AiSalesIntelligence\AiSalesIntelligenceSetting;
use App\Models\AiSalesIntelligence\AiScoringRule;
use App\Models\User;
use App\Services\AiSalesIntelligence\AiAgentUserResolver;
use App\Services\AiSalesIntelligence\AiOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AiSalesIntelligenceController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $user = auth()->user();
            if (!$user || !$user->hasRole('super_admin')) {
                return ApiResponse::error('Forbidden', 403);
            }

            return $next($request);
        });
    }

    public function dashboard(Request $request): JsonResponse
    {
        $branchId = $request->integer('branch_id') ?: null;
        $cacheKey = AiOrchestrator::CACHE_DASHBOARD.':'.($branchId ?? 'all');

        $data = Cache::remember($cacheKey, 120, function () use ($branchId) {
            $query = AiAgentMetric::query()->with('user');
            if ($branchId && \Schema::hasColumn('users', 'company_branch_id')) {
                $query->whereHas('user', fn ($q) => $q->where('company_branch_id', $branchId));
            }

            $metrics = $query->orderByDesc('overall_ai_score')->get();
            $rankings = AiAgentRanking::query()
                ->where('snapshot_date', now()->toDateString())
                ->orderBy('overall_rank')
                ->get();

            $alerts = AiAgentAlert::query()
                ->where('is_read', false)
                ->orderByDesc('created_at')
                ->limit(50)
                ->with('user:id,name')
                ->get();

            return [
                'summary' => [
                    'agents_tracked' => $metrics->count(),
                    'avg_ai_score' => round((float) $metrics->avg('overall_ai_score'), 2),
                    'excellent' => $metrics->where('status', 'excellent')->count(),
                    'good' => $metrics->where('status', 'good')->count(),
                    'needs_attention' => $metrics->where('status', 'needs_attention')->count(),
                    'critical' => $metrics->where('status', 'critical')->count(),
                    'high_risk' => $metrics->where('risk_level', 'high')->count(),
                    'last_computed_at' => $metrics->max('computed_at'),
                ],
                'agents' => AiAgentMetricResource::collection($metrics),
                'rankings' => $rankings,
                'alerts' => $alerts,
            ];
        });

        return ApiResponse::success($data, 'AI Sales Intelligence dashboard');
    }

    public function agents(Request $request): JsonResponse
    {
        $query = AiAgentMetric::query()->with('user')->orderByDesc('overall_ai_score');

        if ($request->filled('agent_id')) {
            $query->where('user_id', $request->integer('agent_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return ApiResponse::success(
            AiAgentMetricResource::collection($query->get()),
            'Agents loaded'
        );
    }

    public function show(User $user): JsonResponse
    {
        $metric = AiAgentMetric::query()->with('user')->where('user_id', $user->id)->first();

        if (!$metric) {
            return ApiResponse::error('No AI metrics for this agent yet. Run recalculation.', 404);
        }

        $observations = AiAgentObservation::query()
            ->where('user_id', $user->id)
            ->orderByDesc('generated_at')
            ->limit(20)
            ->get();

        $alerts = AiAgentAlert::query()
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->orderByDesc('created_at')
            ->get();

        $ranking = AiAgentRanking::query()
            ->where('user_id', $user->id)
            ->where('snapshot_date', now()->toDateString())
            ->first();

        return ApiResponse::success([
            'agent' => new AiAgentMetricResource($metric),
            'observations' => $observations,
            'alerts' => $alerts,
            'ranking' => $ranking,
        ], 'Agent intelligence profile');
    }

    public function neglect(User $user): JsonResponse
    {
        $metric = AiAgentMetric::query()->where('user_id', $user->id)->first();
        if (!$metric) {
            return ApiResponse::error('No metrics available', 404);
        }

        return ApiResponse::success(
            $metric->neglect_metrics ?? ['neglected_leads' => [], 'drilldown' => []],
            'Neglect detection'
        );
    }

    public function drilldown(User $user): JsonResponse
    {
        $metric = AiAgentMetric::query()->where('user_id', $user->id)->first();
        if (!$metric) {
            return ApiResponse::error('No metrics available', 404);
        }

        return ApiResponse::success(
            $metric->neglect_metrics['drilldown'] ?? [],
            'Lead drilldown'
        );
    }

    public function alerts(Request $request): JsonResponse
    {
        $query = AiAgentAlert::query()->with('user:id,name')->orderByDesc('created_at');

        if ($request->boolean('unread_only')) {
            $query->where('is_read', false);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        return ApiResponse::success($query->limit(100)->get(), 'Alerts loaded');
    }

    public function recalculate(Request $request, AiOrchestrator $orchestrator): JsonResponse
    {
        $userId = $request->integer('user_id') ?: null;
        if ($userId) {
            $orchestrator->recalculateUser($userId);
            $count = 1;
        } else {
            $count = $orchestrator->recalculateAll();
        }

        return ApiResponse::success(['recalculated' => $count], 'Recalculation queued/completed');
    }

    public function settings(): JsonResponse
    {
        $row = AiSalesIntelligenceSetting::current();

        return ApiResponse::success($row->only([
            'metrics_lookback_days',
            'neglect_inactive_days',
            'stuck_follow_up_days',
            'response_sla_minutes',
            'automation_flags',
        ]), 'Settings loaded');
    }

    public function agentOptions(AiAgentUserResolver $resolver): JsonResponse
    {
        $ids = $resolver->scoredUserIds();
        $users = User::query()->whereIn('id', $ids)->select('id', 'name', 'email')->orderBy('name')->get();

        return ApiResponse::success($users, 'Agent options');
    }

    public function scoringRules(): JsonResponse
    {
        $rules = AiScoringRule::query()->orderBy('sort_order')->get();
        $config = AiScoringRule::resolved();

        return ApiResponse::success([
            'rules' => $rules,
            'config' => $config,
            'defaults' => AiScoringRule::defaultConfig(),
        ], 'Scoring rules loaded');
    }

    public function updateScoringRules(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rules' => 'required|array|min:1',
            'rules.*.id' => 'required|integer|exists:ai_scoring_rules,id',
            'rules.*.weight' => 'required|numeric|min:0|max:100',
            'rules.*.thresholds' => 'nullable|array',
        ]);

        foreach ($data['rules'] as $row) {
            $update = ['weight' => $row['weight']];
            if (array_key_exists('thresholds', $row)) {
                $update['thresholds'] = $row['thresholds'];
            }
            AiScoringRule::query()->where('id', $row['id'])->update($update);
        }

        AiScoringRule::clearCache();
        Cache::forget(AiOrchestrator::CACHE_DASHBOARD);

        return ApiResponse::success([
            'rules' => AiScoringRule::query()->orderBy('sort_order')->get(),
            'config' => AiScoringRule::resolved(),
        ], 'Scoring rules saved');
    }

    public function resetScoringRules(): JsonResponse
    {
        $defaults = [
            'overall_behavior' => 0.35, 'overall_pipeline' => 0.15, 'overall_followup' => 0.15,
            'overall_qualification' => 0.10, 'overall_communication' => 0.10, 'overall_conversion' => 0.10, 'overall_neglect' => 0.05,
            'behavior_response' => 0.20, 'behavior_followup' => 0.20, 'behavior_pipeline' => 0.15,
            'behavior_communication' => 0.15, 'behavior_qualification' => 0.15, 'behavior_neglect' => 0.15,
            'status_excellent' => 85, 'status_good' => 70, 'status_needs_attention' => 50,
            'risk_high' => 70, 'risk_medium' => 40,
        ];

        foreach ($defaults as $key => $weight) {
            AiScoringRule::query()->where('factor_key', $key)->update(['weight' => $weight]);
        }

        AiScoringRule::query()->where('factor_key', 'response_sla')->update([
            'thresholds' => AiScoringRule::defaultConfig()['response_sla'],
        ]);

        AiScoringRule::clearCache();
        Cache::forget(AiOrchestrator::CACHE_DASHBOARD);

        return ApiResponse::success([
            'rules' => AiScoringRule::query()->orderBy('sort_order')->get(),
            'config' => AiScoringRule::resolved(),
        ], 'Scoring rules reset to defaults');
    }
}
