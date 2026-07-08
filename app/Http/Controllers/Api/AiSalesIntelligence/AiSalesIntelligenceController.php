<?php

namespace App\Http\Controllers\Api\AiSalesIntelligence;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\AiSalesIntelligence\AiAgentMetricResource;
use App\Jobs\AiSalesIntelligence\RecalculateAllAiSalesIntelligenceJob;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AiSalesIntelligenceController extends Controller
{
    private const REQUIRED_TABLES = [
        'ai_agent_metrics',
        'ai_agent_rankings',
        'ai_agent_alerts',
        'ai_scoring_rules',
        'ai_sales_intelligence_settings',
    ];

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $user = auth()->user();
            if (!$user || !$user->hasRole('super_admin')) {
                return ApiResponse::error('Forbidden', 403);
            }

            $missing = array_values(array_filter(
                self::REQUIRED_TABLES,
                fn (string $table) => !Schema::hasTable($table)
            ));

            if ($missing !== []) {
                return ApiResponse::error(
                    'AI Sales Intelligence database tables are missing. Run: php artisan migrate --force && php artisan ai-sales-intelligence:bootstrap --sync',
                    503,
                    ['missing_tables' => $missing]
                );
            }

            return $next($request);
        });
    }

    public function dashboard(Request $request, AiAgentUserResolver $resolver): JsonResponse
    {
        try {
            AiSalesIntelligenceSetting::current();

            $branchId = $request->integer('branch_id') ?: null;
            $page = max(1, $request->integer('page') ?: 1);
            $perPage = min(50, max(5, $request->integer('per_page') ?: 15));
            $search = trim((string) $request->string('search'));
            $status = $request->filled('status') ? (string) $request->string('status') : null;

            $bootstrap = $this->bootstrapState($resolver);
            $this->maybeAutoBootstrap($bootstrap);

            $summaryKey = AiOrchestrator::CACHE_DASHBOARD.':summary:'.($branchId ?? 'all');
            $summary = Cache::remember($summaryKey, 120, fn () => $this->buildSummary($branchId));

            $agentsQuery = AiAgentMetric::query()
                ->with('user:id,name,email')
                ->when($branchId && Schema::hasColumn('users', 'company_branch_id'), function ($q) use ($branchId) {
                    $q->whereHas('user', fn ($uq) => $uq->where('company_branch_id', $branchId));
                })
                ->when($status, fn ($q) => $q->where('status', $status))
                ->when($search !== '', function ($q) use ($search) {
                    $q->whereHas('user', fn ($uq) => $uq->where('name', 'like', '%'.$search.'%'));
                })
                ->orderByDesc('overall_ai_score');

            $paginator = $agentsQuery->paginate($perPage, ['*'], 'page', $page);

            $rankings = AiAgentRanking::query()
                ->where('snapshot_date', now()->toDateString())
                ->orderBy('overall_rank')
                ->limit(20)
                ->get()
                ->map(fn ($row) => $this->serializeRanking($row))
                ->values()
                ->all();

            $alerts = AiAgentAlert::query()
                ->where('is_read', false)
                ->orderByDesc('created_at')
                ->limit(20)
                ->with('user:id,name')
                ->get()
                ->map(fn ($row) => $this->serializeAlert($row))
                ->values()
                ->all();

            return ApiResponse::success([
                'summary' => $summary,
                'agents' => [
                    'data' => AiAgentMetricResource::collection($paginator->getCollection())->resolve(),
                    'meta' => [
                        'current_page' => $paginator->currentPage(),
                        'last_page' => $paginator->lastPage(),
                        'per_page' => $paginator->perPage(),
                        'total' => $paginator->total(),
                    ],
                ],
                'rankings' => $rankings,
                'alerts' => $alerts,
                'bootstrap' => $this->bootstrapState($resolver),
            ], 'AI Sales Intelligence dashboard');
        } catch (Throwable $e) {
            Log::error('AI Sales Intelligence dashboard failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                'Failed to load AI Sales Intelligence dashboard. Run: php artisan ai-sales-intelligence:bootstrap --sync',
                500,
                ['detail' => config('app.debug') ? $e->getMessage() : null]
            );
        }
    }

    public function status(AiAgentUserResolver $resolver): JsonResponse
    {
        return ApiResponse::success([
            'bootstrap' => $this->bootstrapState($resolver),
            'agents_scored' => AiAgentMetric::query()->count(),
            'agents_expected' => count($resolver->scoredUserIds()),
        ], 'AI Sales Intelligence status');
    }

    public function agents(Request $request): JsonResponse
    {
        $page = max(1, $request->integer('page') ?: 1);
        $perPage = min(50, max(5, $request->integer('per_page') ?: 20));
        $search = trim((string) $request->string('search'));

        $query = AiAgentMetric::query()
            ->with('user:id,name,email')
            ->orderByDesc('overall_ai_score');

        if ($request->filled('agent_id')) {
            $query->where('user_id', $request->integer('agent_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($search !== '') {
            $query->whereHas('user', fn ($q) => $q->where('name', 'like', '%'.$search.'%'));
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return ApiResponse::success([
            'data' => AiAgentMetricResource::collection($paginator->getCollection())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], 'Agents loaded');
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
            'agent' => (new AiAgentMetricResource($metric))->resolve(),
            'observations' => $observations,
            'alerts' => $alerts,
            'ranking' => $ranking ? $this->serializeRanking($ranking) : null,
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
        $page = max(1, $request->integer('page') ?: 1);
        $perPage = min(100, max(10, $request->integer('per_page') ?: 25));

        $query = AiAgentAlert::query()->with('user:id,name')->orderByDesc('created_at');

        if ($request->boolean('unread_only')) {
            $query->where('is_read', false);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return ApiResponse::success([
            'data' => $paginator->getCollection()->map(fn ($row) => $this->serializeAlert($row))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], 'Alerts loaded');
    }

    public function recalculate(Request $request, AiOrchestrator $orchestrator): JsonResponse
    {
        $userId = $request->integer('user_id') ?: null;

        try {
            if ($userId) {
                $orchestrator->recalculateUser($userId);

                return ApiResponse::success(['recalculated' => 1, 'mode' => 'sync'], 'Agent recalculated');
            }

            if ($request->boolean('sync') || config('queue.default') === 'sync') {
                $count = $orchestrator->recalculateAll();

                return ApiResponse::success([
                    'recalculated' => $count,
                    'mode' => 'sync',
                ], 'Full recalculation completed');
            }

            RecalculateAllAiSalesIntelligenceJob::dispatch();
            Cache::put('ai_sales_intelligence:bootstrap_queued', true, 3600);
            Cache::put('ai_sales_intelligence:bootstrap_status', [
                'status' => 'running',
                'queued_at' => now()->toIso8601String(),
            ], 3600);

            return ApiResponse::success([
                'queued' => true,
                'mode' => 'queue',
            ], 'Full agent scan queued. Refresh in a few minutes.');
        } catch (Throwable $e) {
            Log::error('AI Sales Intelligence recalculate failed', ['message' => $e->getMessage()]);

            return ApiResponse::error('Recalculation failed: '.$e->getMessage(), 500);
        }
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
        Cache::forget(AiOrchestrator::CACHE_DASHBOARD.':summary:all');

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
        Cache::forget(AiOrchestrator::CACHE_DASHBOARD.':summary:all');

        return ApiResponse::success([
            'rules' => AiScoringRule::query()->orderBy('sort_order')->get(),
            'config' => AiScoringRule::resolved(),
        ], 'Scoring rules reset to defaults');
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildSummary(?int $branchId): array
    {
        $query = AiAgentMetric::query();
        if ($branchId && Schema::hasColumn('users', 'company_branch_id')) {
            $query->whereHas('user', fn ($q) => $q->where('company_branch_id', $branchId));
        }

        $metrics = $query->get();
        $lastComputed = $metrics->max('computed_at');

        return [
            'agents_tracked' => $metrics->count(),
            'avg_ai_score' => round((float) $metrics->avg('overall_ai_score'), 2),
            'excellent' => $metrics->where('status', 'excellent')->count(),
            'good' => $metrics->where('status', 'good')->count(),
            'needs_attention' => $metrics->where('status', 'needs_attention')->count(),
            'critical' => $metrics->where('status', 'critical')->count(),
            'high_risk' => $metrics->where('risk_level', 'high')->count(),
            'last_computed_at' => $lastComputed?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function bootstrapState(AiAgentUserResolver $resolver): array
    {
        $expected = count($resolver->scoredUserIds());
        $scored = AiAgentMetric::query()->count();
        $cached = Cache::get('ai_sales_intelligence:bootstrap_status');

        if (is_array($cached) && ($cached['status'] ?? '') === 'running') {
            return array_merge([
                'status' => 'running',
                'agents_expected' => $expected,
                'agents_scored' => $scored,
            ], $cached);
        }

        if ($expected > 0 && $scored < $expected) {
            return [
                'status' => $scored === 0 ? 'pending' : 'partial',
                'agents_expected' => $expected,
                'agents_scored' => $scored,
            ];
        }

        return [
            'status' => 'ready',
            'agents_expected' => $expected,
            'agents_scored' => $scored,
        ];
    }

    /**
     * @param  array<string, mixed>  $bootstrap
     */
    protected function maybeAutoBootstrap(array $bootstrap): void
    {
        if (!in_array($bootstrap['status'], ['pending', 'partial'], true)) {
            return;
        }

        if (Cache::has('ai_sales_intelligence:bootstrap_queued')) {
            return;
        }

        Cache::put('ai_sales_intelligence:bootstrap_queued', true, 3600);
        RecalculateAllAiSalesIntelligenceJob::dispatch();
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeRanking(AiAgentRanking $ranking): array
    {
        return [
            'id' => $ranking->id,
            'user_id' => $ranking->user_id,
            'snapshot_date' => $ranking->snapshot_date?->toDateString(),
            'overall_rank' => $ranking->overall_rank,
            'behavior_rank' => $ranking->behavior_rank,
            'pipeline_rank' => $ranking->pipeline_rank,
            'followup_rank' => $ranking->followup_rank,
            'qualification_rank' => $ranking->qualification_rank,
            'communication_rank' => $ranking->communication_rank,
            'conversion_rank' => $ranking->conversion_rank,
            'scores' => $ranking->scores,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeAlert(AiAgentAlert $alert): array
    {
        return [
            'id' => $alert->id,
            'user_id' => $alert->user_id,
            'alert_type' => $alert->alert_type,
            'severity' => $alert->severity,
            'title' => $alert->title,
            'message' => $alert->message,
            'meta' => $alert->meta,
            'is_read' => $alert->is_read,
            'created_at' => $alert->created_at?->toIso8601String(),
            'user' => $alert->relationLoaded('user') && $alert->user ? [
                'id' => $alert->user->id,
                'name' => $alert->user->name,
            ] : null,
        ];
    }
}
