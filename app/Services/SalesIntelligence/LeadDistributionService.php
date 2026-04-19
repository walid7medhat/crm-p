<?php

namespace App\Services\SalesIntelligence;

use App\Models\AgentScore;
use App\Models\Attendance;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\LeadDistributionLog;
use App\Models\SalesIntelligenceSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LeadDistributionService
{
    public function __construct(
        protected AgentMetricAggregator $metrics,
        protected AgentScoringEngine $scoring,
    ) {}

    /**
     * @return array{user: User|null, score: ?float, method: string, meta: array, dry_run: bool}
     */
    public function assignLead(Lead $lead, string $mode, ?int $manualUserId = null, bool $dryRun = false): array
    {
        $settings = SalesIntelligenceSetting::current();
        $effectiveMode = $mode !== '' ? $mode : $settings->distribution_mode;

        if ($effectiveMode === 'manual') {
            if (!$manualUserId) {
                return [
                    'user' => null,
                    'score' => null,
                    'method' => 'manual',
                    'meta' => ['error' => 'manual_user_id_required'],
                    'dry_run' => $dryRun,
                ];
            }
            $user = User::query()->find($manualUserId);
            if (!$user) {
                return ['user' => null, 'score' => null, 'method' => 'manual', 'meta' => ['error' => 'user_not_found'], 'dry_run' => $dryRun];
            }

            return $this->finalizeAssignment($lead, $user, null, 'manual', ['picked' => 'manual_override'], $dryRun);
        }

        $pool = $this->buildEligiblePool($settings);
        if ($pool->isEmpty()) {
            return [
                'user' => null,
                'score' => null,
                'method' => $effectiveMode,
                'meta' => ['error' => 'no_eligible_agents', 'hint' => 'Relax attendance or max-per-day, or ensure active sales users exist.'],
                'dry_run' => $dryRun,
            ];
        }

        $scored = $this->attachLatestScores($pool);

        $methodUsed = $effectiveMode;
        $meta = ['pool_size' => $scored->count()];

        if ($effectiveMode === 'round_robin') {
            $ordered = $scored->sortBy(fn ($r) => $r['user']->id)->values();
            $pick = $this->pickRoundRobin((int) ($settings->round_robin_last_user_id ?? 0), $ordered);
            $meta['round_robin'] = true;
        } elseif ($effectiveMode === 'performance_first') {
            $pick = $scored->sortByDesc(fn ($row) => $row['score'] ?? 0)->first();
        } elseif ($effectiveMode === 'weighted') {
            $pick = $this->pickWeightedRandom($scored);
            $meta['weighted'] = true;
        } elseif (in_array($effectiveMode, ['hybrid', 'ai_assisted'], true)) {
            $pick = $this->pickHybrid($lead, $scored, $settings);
            $meta['hybrid_ai_assist'] = true;
            $methodUsed = 'hybrid';
        } else {
            $pick = $this->pickWeightedRandom($scored);
        }

        if (!$pick || !($pick['user'] instanceof User)) {
            return ['user' => null, 'score' => null, 'method' => $methodUsed, 'meta' => $meta + ['error' => 'pick_failed'], 'dry_run' => $dryRun];
        }

        /** @var User $user */
        $user = $pick['user'];
        $score = $pick['score'] ?? null;

        $meta = array_merge($meta, $pick['meta'] ?? []);

        $result = $this->finalizeAssignment($lead, $user, $score, $methodUsed, $meta, $dryRun);

        if (!$dryRun && $effectiveMode === 'round_robin' && $result['user']) {
            $settings->round_robin_last_user_id = $result['user']->id;
            $settings->save();
        }

        return $result;
    }

    /**
     * @return array{best: User|null, score: ?float, close_probability: float, rationale: array}
     */
    public function suggestForLead(Lead $lead): array
    {
        $settings = SalesIntelligenceSetting::current();
        $pool = $this->buildEligiblePool($settings);
        if ($pool->isEmpty()) {
            return [
                'best' => null,
                'score' => null,
                'close_probability' => 0.0,
                'rationale' => ['reason' => 'no_eligible_agents'],
            ];
        }

        $scored = $this->attachLatestScores($pool);
        $ranked = $scored->map(function ($row) use ($lead) {
            $user = $row['user'];
            $base = (float) ($row['score'] ?? 50);
            $matchBoost = $this->smartMatchBoost($lead, $user);
            $adjusted = max(0, min(100, $base * (0.75 + 0.25 * $matchBoost)));

            return [
                'user' => $user,
                'base_score' => $base,
                'adjusted' => $adjusted,
                'match_boost' => $matchBoost,
            ];
        })->sortByDesc('adjusted')->values();

        $top = $ranked->first();
        $closeProbability = $this->estimateCloseProbability($lead, (float) ($top['adjusted'] ?? 40));

        return [
            'best' => $top['user'] ?? null,
            'score' => isset($top['adjusted']) ? (float) $top['adjusted'] : null,
            'close_probability' => $closeProbability,
            'rationale' => [
                'top_match_boost' => $top['match_boost'] ?? null,
                'lead_source' => $lead->lead_source,
                'budget' => $lead->budget ?? null,
            ],
        ];
    }

    protected function finalizeAssignment(Lead $lead, User $user, ?float $score, string $method, array $meta, bool $dryRun): array
    {
        if (!$dryRun) {
            DB::transaction(function () use ($lead, $user, $score, $method, $meta) {
                $lead->update([
                    'responsible_person_id' => $user->id,
                    'last_stage_change_at' => now(),
                ]);

                LeadDistributionLog::query()->create([
                    'lead_id' => $lead->id,
                    'assigned_to' => $user->id,
                    'score_at_assignment' => $score,
                    'method' => $method,
                    'meta' => $meta,
                    'created_at' => now(),
                ]);
            });
        }

        return [
            'user' => $user,
            'score' => $score,
            'method' => $method,
            'meta' => $meta,
            'dry_run' => $dryRun,
        ];
    }

    protected function buildEligiblePool(SalesIntelligenceSetting $settings): Collection
    {
        $flags = $settings->automation_flags ?? [];
        $roles = $flags['eligible_roles'] ?? ['sales'];

        $users = User::query()
            ->where('status', 'active')
            ->where(function ($q) {
                $q->where('on_vacation', false)->orWhereNull('on_vacation');
            })
            ->whereHas('roles', fn ($q) => $q->whereIn('name', $roles))
            ->get();

        if ($settings->require_attendance) {
            $presentIds = $this->presentUserIdsToday();
            if ($presentIds !== null && $presentIds->isNotEmpty()) {
                $users = $users->filter(fn (User $u) => $presentIds->contains($u->id));
            }
        }

        $max = max(1, (int) $settings->max_leads_per_agent_per_day);
        $start = Carbon::today('Asia/Dubai')->startOfDay();
        $end = Carbon::today('Asia/Dubai')->endOfDay();

        $counts = Lead::query()
            ->selectRaw('responsible_person_id as uid, COUNT(*) as c')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('responsible_person_id')
            ->pluck('c', 'uid');

        $filtered = $users->filter(function (User $u) use ($counts, $max) {
            $c = (int) ($counts[$u->id] ?? 0);

            return $c < $max;
        })->values();

        if ($filtered->isEmpty() && $users->isNotEmpty()) {
            return $users->values();
        }

        return $filtered;
    }

    /**
     * @return Collection<int, int>|null  null if no snapshot (caller should not filter)
     */
    protected function presentUserIdsToday(): ?Collection
    {
        $date = Carbon::today('Asia/Dubai')->toDateString();

        return Cache::remember("sales_intel:present_user_ids:{$date}", 300, function () use ($date) {
            $rows = Attendance::query()
                ->whereDate('date', $date)
                ->get(['user_id', 'status']);

            if ($rows->isEmpty()) {
                return null;
            }

            return $rows
                ->filter(fn ($r) => in_array(strtolower((string) $r->status), ['present', 'late'], true))
                ->pluck('user_id')
                ->filter()
                ->unique()
                ->values();
        });
    }

    /**
     * @param  Collection<int, User>  $users
     * @return Collection<int, array{user: User, score: ?float, meta?: array}>
     */
    protected function attachLatestScores(Collection $users): Collection
    {
        $ids = $users->pluck('id')->all();
        if ($ids === []) {
            return collect();
        }

        $sub = DB::table('agent_scores')
            ->selectRaw('user_id, MAX(calculated_at) as mx')
            ->whereIn('user_id', $ids)
            ->groupBy('user_id');

        $latest = AgentScore::query()
            ->select('agent_scores.*')
            ->joinSub($sub, 't', function ($join) {
                $join->on('agent_scores.user_id', '=', 't.user_id')
                    ->on('agent_scores.calculated_at', '=', 't.mx');
            })
            ->get()
            ->keyBy('user_id');

        return $users->map(function (User $u) use ($latest) {
            $s = $latest->get($u->id);

            return [
                'user' => $u,
                'score' => $s ? (float) $s->score : null,
                'meta' => [],
            ];
        });
    }

    protected function pickWeightedRandom(Collection $scored): ?array
    {
        if ($scored->isEmpty()) {
            return null;
        }

        $weights = $scored->map(function ($row) {
            $base = $row['score'] ?? 55;

            return max(1.0, $base * $base / 100.0);
        })->all();

        $total = array_sum($weights);
        if ($total <= 0) {
            return $scored->random();
        }

        $r = mt_rand() / mt_getrandmax() * $total;
        $acc = 0.0;
        foreach ($scored->values() as $i => $row) {
            $acc += $weights[$i] ?? 0;
            if ($r <= $acc) {
                return $row;
            }
        }

        return $scored->last();
    }

    protected function pickRoundRobin(int $lastUserId, Collection $ordered): ?array
    {
        if ($ordered->isEmpty()) {
            return null;
        }

        $ids = $ordered->pluck('user.id')->all();
        if (!$lastUserId) {
            return $ordered->first();
        }

        $pos = array_search($lastUserId, $ids, true);
        $nextPos = $pos === false ? 0 : (($pos + 1) % count($ids));

        return $ordered->get($nextPos);
    }

    protected function pickHybrid(Lead $lead, Collection $scored, SalesIntelligenceSetting $settings): ?array
    {
        $top = $scored->map(function ($row) use ($lead) {
            $boost = $this->smartMatchBoost($lead, $row['user']);
            $adjusted = (float) ($row['score'] ?? 50) * (0.8 + 0.2 * $boost);

            return array_merge($row, [
                'score' => $adjusted,
                'meta' => ['match_boost' => $boost],
            ]);
        })->sortByDesc('score')->take(3)->values();

        if ($top->isEmpty()) {
            return null;
        }

        $choice = $this->pickWeightedRandom($top);

        return $choice;
    }

    protected function smartMatchBoost(Lead $lead, User $agent): float
    {
        $source = (string) ($lead->lead_source ?? '');
        if ($source === '') {
            return 0.75;
        }

        $winsWithSource = Deal::query()
            ->where('responsible_person_id', $agent->id)
            ->where('status', 'completed')
            ->where('source', $source)
            ->count();

        $winsTotal = max(1, Deal::query()
            ->where('responsible_person_id', $agent->id)
            ->where('status', 'completed')
            ->count());

        $sourceFit = min(1.0, $winsWithSource / max(1, $winsTotal / 3));

        $budget = $lead->budget ?? null;
        $budgetFit = 0.5;
        if ($budget !== null && (float) $budget > 0) {
            $avgDeal = (float) Deal::query()
                ->where('responsible_person_id', $agent->id)
                ->where('status', 'completed')
                ->avg('deal_total_amount');
            if ($avgDeal > 0) {
                $ratio = (float) $budget / $avgDeal;
                $budgetFit = 1.0 - min(1.0, abs(log(max($ratio, 0.01), 10)) / 2);
            }
        }

        return round(0.55 * $sourceFit + 0.45 * $budgetFit, 4);
    }

    protected function estimateCloseProbability(Lead $lead, float $agentAdjustedScore): float
    {
        $intel = (float) ($lead->score ?? 50);

        return round(max(5.0, min(95.0, 0.45 * $intel + 0.35 * $agentAdjustedScore + ($lead->budget ? 5 : 0))), 2);
    }
}
