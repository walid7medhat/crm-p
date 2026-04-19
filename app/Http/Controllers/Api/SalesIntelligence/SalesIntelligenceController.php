<?php

namespace App\Http\Controllers\Api\SalesIntelligence;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AgentScore;
use App\Models\Lead;
use App\Models\LeadDistributionLog;
use App\Models\SalesIntelligenceSetting;
use App\Models\ScoringRule;
use App\Models\User;
use App\Services\SalesIntelligence\AgentMetricAggregator;
use App\Services\SalesIntelligence\AgentScoringEngine;
use App\Services\SalesIntelligence\LeadDistributionService;
use App\Services\SalesIntelligence\SalesIntelligenceOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SalesIntelligenceController extends Controller
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

    public function overview(): JsonResponse
    {
        try {
            $data = Cache::remember(SalesIntelligenceOrchestrator::CACHE_OVERVIEW, 120, function () {
                $sub = DB::table('agent_scores')
                    ->selectRaw('user_id, MAX(calculated_at) as mx')
                    ->groupBy('user_id');

                $latest = AgentScore::query()
                    ->joinSub($sub, 't', function ($join) {
                        $join->on('agent_scores.user_id', '=', 't.user_id')
                            ->on('agent_scores.calculated_at', '=', 't.mx');
                    })
                    ->get();

                return [
                    'agents_tracked' => $latest->count(),
                    'avg_score' => round((float) $latest->avg('score'), 2),
                    'tiers' => [
                        'hot' => $latest->where('tier', 'hot')->count(),
                        'warm' => $latest->where('tier', 'warm')->count(),
                        'cold' => $latest->where('tier', 'cold')->count(),
                    ],
                    'last_calculated_at' => $latest->max('calculated_at'),
                ];
            });
        } catch (\Throwable $e) {
            $data = [
                'agents_tracked' => 0,
                'avg_score' => null,
                'tiers' => ['hot' => 0, 'warm' => 0, 'cold' => 0],
                'last_calculated_at' => null,
                'warning' => 'Partial data: '.$e->getMessage(),
            ];
        }

        return ApiResponse::success($data, 'Sales intelligence overview');
    }

    public function settings(): JsonResponse
    {
        $row = SalesIntelligenceSetting::current();

        return ApiResponse::success([
            'max_leads_per_agent_per_day' => (int) $row->max_leads_per_agent_per_day,
            'distribution_mode' => $row->distribution_mode,
            'ai_mode' => $row->ai_mode,
            'require_attendance' => (bool) $row->require_attendance,
            'metrics_lookback_days' => (int) $row->metrics_lookback_days,
            'automation_flags' => $row->automation_flags ?? [],
        ], 'Settings loaded');
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'max_leads_per_agent_per_day' => 'sometimes|integer|min:1|max:500',
            'distribution_mode' => 'sometimes|in:weighted,round_robin,performance_first,manual,hybrid,ai_assisted',
            'ai_mode' => 'sometimes|in:rules_only,ai_assisted,hybrid',
            'require_attendance' => 'sometimes|boolean',
            'metrics_lookback_days' => 'sometimes|integer|min:7|max:730',
            'automation_flags' => 'sometimes|array',
            'automation_flags.recalculate_on_deal_close' => 'sometimes|boolean',
            'automation_flags.recalculate_on_activity' => 'sometimes|boolean',
            'automation_flags.auto_assign_new_leads' => 'sometimes|boolean',
            'automation_flags.eligible_roles' => 'sometimes|array',
            'automation_flags.eligible_roles.*' => 'string',
        ]);

        $row = SalesIntelligenceSetting::current();
        $flags = array_merge($row->automation_flags ?? [], $data['automation_flags'] ?? []);
        unset($data['automation_flags']);
        if (!empty($flags)) {
            $data['automation_flags'] = $flags;
        }

        $row->update($data);
        $row->refresh();
        Cache::forget(SalesIntelligenceOrchestrator::CACHE_OVERVIEW);

        return ApiResponse::success([
            'max_leads_per_agent_per_day' => (int) $row->max_leads_per_agent_per_day,
            'distribution_mode' => $row->distribution_mode,
            'ai_mode' => $row->ai_mode,
            'require_attendance' => (bool) $row->require_attendance,
            'metrics_lookback_days' => (int) $row->metrics_lookback_days,
            'automation_flags' => $row->automation_flags ?? [],
        ], 'Settings updated');
    }

    public function scoringRules(): JsonResponse
    {
        $rules = ScoringRule::query()->orderBy('id')->get();

        return ApiResponse::success($rules, 'Scoring rules');
    }

    public function updateScoringRules(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'rules' => 'required|array|min:1',
            'rules.*.id' => 'required|integer|exists:scoring_rules,id',
            'rules.*.weight' => 'required|numeric|min:0|max:1',
            'rules.*.low_value' => 'nullable|numeric',
            'rules.*.medium_value' => 'nullable|numeric',
            'rules.*.high_value' => 'nullable|numeric',
            'rules.*.direction' => 'sometimes|in:higher_better,lower_better',
        ]);

        $sum = collect($payload['rules'])->sum('weight');
        if (abs($sum - 1.0) > 0.08 && abs($sum - 100) > 2) {
            return ApiResponse::error('Weights should sum to 1.0 (or 100 for percentage).', 422);
        }

        if (abs($sum - 100) <= 1) {
            foreach ($payload['rules'] as &$r) {
                $r['weight'] = ((float) $r['weight']) / 100.0;
            }
            unset($r);
        }

        foreach ($payload['rules'] as $r) {
            ScoringRule::query()->whereKey($r['id'])->update([
                'weight' => $r['weight'],
                'low_value' => $r['low_value'] ?? null,
                'medium_value' => $r['medium_value'] ?? null,
                'high_value' => $r['high_value'] ?? null,
                'direction' => $r['direction'] ?? 'higher_better',
            ]);
        }

        app(AgentScoringEngine::class)->forgetRulesCache();
        Cache::forget(SalesIntelligenceOrchestrator::CACHE_OVERVIEW);

        return ApiResponse::success(ScoringRule::query()->orderBy('id')->get(), 'Scoring rules updated');
    }

    public function agents(Request $request): JsonResponse
    {
        $search = (string) $request->query('q', '');

        $query = User::query()
            ->where('status', 'active')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['sales', 'team_lead', 'manager']))
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->limit(200);

        $users = $query->get(['id', 'name', 'email', 'avatar']);
        $ids = $users->pluck('id')->all();

        $scores = collect();
        if ($ids !== []) {
            $sub = DB::table('agent_scores')
                ->selectRaw('user_id, MAX(calculated_at) as mx')
                ->whereIn('user_id', $ids)
                ->groupBy('user_id');

            $scores = AgentScore::query()
                ->joinSub($sub, 't', function ($join) {
                    $join->on('agent_scores.user_id', '=', 't.user_id')
                        ->on('agent_scores.calculated_at', '=', 't.mx');
                })
                ->get()
                ->keyBy('user_id');
        }

        $mapped = $users->map(function (User $u) use ($scores) {
            $s = $scores->get($u->id);

            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'avatar' => $u->avatar,
                'score' => $s ? (float) $s->score : null,
                'rank' => $s ? $s->tier : null,
                'calculated_at' => $s?->calculated_at,
            ];
        });

        return ApiResponse::success($mapped, 'Agents');
    }

    public function recalculate(Request $request, SalesIntelligenceOrchestrator $orchestrator): JsonResponse
    {
        $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
        ]);
        $uid = $request->input('user_id');
        $count = $orchestrator->recalculateAll($uid ? [(int) $uid] : null);

        return ApiResponse::success(['recalculated' => $count], 'Recalculation complete');
    }

    public function previewScore(Request $request, AgentMetricAggregator $aggregator, AgentScoringEngine $engine): JsonResponse
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'rules' => 'nullable|array',
            'rules.*.factor_name' => 'required_with:rules|string|max:64',
            'rules.*.weight' => 'required_with:rules|numeric|min:0|max:1',
            'rules.*.low_value' => 'nullable|numeric',
            'rules.*.medium_value' => 'nullable|numeric',
            'rules.*.high_value' => 'nullable|numeric',
            'rules.*.direction' => 'nullable|in:higher_better,lower_better',
        ]);

        $draftRules = null;
        if (!empty($data['rules'])) {
            $draftRules = collect($data['rules'])->map(fn ($r) => [
                'factor_name' => $r['factor_name'],
                'weight' => (float) $r['weight'],
                'low_value' => isset($r['low_value']) ? (float) $r['low_value'] : null,
                'medium_value' => isset($r['medium_value']) ? (float) $r['medium_value'] : null,
                'high_value' => isset($r['high_value']) ? (float) $r['high_value'] : null,
                'direction' => $r['direction'] ?? 'higher_better',
            ])->all();
        }

        if ($draftRules) {
            $computed = $aggregator->computeForUser((int) $data['user_id']);
            unset($computed['user_id']);
            $row = $computed;
        } else {
            $metric = $aggregator->persistForUser((int) $data['user_id']);
            $row = $metric->only([
                'conversion_rate',
                'avg_response_time',
                'revenue',
                'deals_won',
                'deals_lost',
                'activity_count',
                'follow_up_score',
                'closing_speed',
            ]);
        }

        $result = $engine->scoreFromMetrics($row, $draftRules);

        return ApiResponse::success([
            'metrics' => $row,
            'total_score' => $result['score'],
            'rank' => $result['tier'],
            'breakdown' => $result['breakdown'],
        ], 'Preview score');
    }

    public function distribute(Request $request, LeadDistributionService $distribution): JsonResponse
    {
        $data = $request->validate([
            'lead_id' => 'required|integer|exists:leads,id',
            'mode' => 'nullable|string',
            'manual_user_id' => 'nullable|integer|exists:users,id',
            'dry_run' => 'sometimes|boolean',
        ]);

        $lead = Lead::query()->findOrFail((int) $data['lead_id']);
        $mode = (string) ($data['mode'] ?? '');
        $dry = (bool) ($data['dry_run'] ?? false);

        $out = $distribution->assignLead(
            $lead,
            $mode,
            isset($data['manual_user_id']) ? (int) $data['manual_user_id'] : null,
            $dry
        );

        if (!$out['user']) {
            return ApiResponse::error($out['meta']['error'] ?? 'Assignment failed', 422, $out['meta'] ?? null);
        }

        return ApiResponse::success([
            'assigned_to' => [
                'id' => $out['user']->id,
                'name' => $out['user']->name,
            ],
            'score_at_assignment' => $out['score'],
            'method' => $out['method'],
            'meta' => $out['meta'],
            'dry_run' => $out['dry_run'],
        ], $dry ? 'Dry run complete' : 'Lead assigned');
    }

    public function distributionLogs(Request $request): JsonResponse
    {
        $perPage = min(50, max(5, (int) $request->query('per_page', 15)));

        $paginator = LeadDistributionLog::query()
            ->with(['assignee:id,name,email', 'lead:id,lead_name,lead_number'])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return ApiResponse::success($paginator, 'Distribution logs');
    }

    public function aiSuggest(Request $request, LeadDistributionService $distribution): JsonResponse
    {
        $data = $request->validate([
            'lead_id' => 'required|integer|exists:leads,id',
        ]);

        $lead = Lead::query()->findOrFail((int) $data['lead_id']);
        $s = $distribution->suggestForLead($lead);

        return ApiResponse::success([
            'suggested_agent' => $s['best'] ? [
                'id' => $s['best']->id,
                'name' => $s['best']->name,
                'email' => $s['best']->email,
            ] : null,
            'adjusted_score' => $s['score'],
            'close_probability' => $s['close_probability'],
            'rationale' => $s['rationale'],
        ], 'AI suggestion (heuristic)');
    }
}
