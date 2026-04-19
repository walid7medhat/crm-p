<?php

namespace App\Services;

use App\Models\AssignmentPattern;
use App\Models\Deal;
use App\Models\LeadAssignmentLog;
use App\Models\SalesTemporalStat;
use App\Models\User;
use Carbon\Carbon;

class LeadAssignmentInsightsService
{
    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $patterns = AssignmentPattern::query()
            ->where('samples', '>=', 2)
            ->get(['context_fingerprint', 'sales_id', 'success_rate', 'samples', 'context_source']);

        $bestByContext = $patterns
            ->groupBy('context_fingerprint')
            ->map(function ($grp) {
                /** @var \Illuminate\Support\Collection<int, AssignmentPattern> $grp */
                return $grp->sortByDesc(fn (AssignmentPattern $p) => (float) $p->success_rate * log(2 + (int) $p->samples))->first();
            })
            ->filter()
            ->values()
            ->take(25)
            ->map(function (?AssignmentPattern $top) {
                if (!$top) {
                    return null;
                }
                $u = User::query()->find($top->sales_id);

                return [
                    'context_fingerprint' => $top->context_fingerprint,
                    'context_source' => $top->context_source,
                    'best_sales_id' => (int) $top->sales_id,
                    'best_sales_name' => $u?->name,
                    'success_rate' => round((float) $top->success_rate, 4),
                    'samples' => (int) $top->samples,
                ];
            })
            ->filter()
            ->values();

        $hourly = SalesTemporalStat::query()
            ->selectRaw('hour, SUM(assignments_count) as ac, SUM(wins_count) as wc')
            ->groupBy('hour')
            ->get()
            ->keyBy('hour')
            ->map(function ($r) {
                $ac = max(1, (int) $r->ac);

                return [
                    'assignments' => (int) $r->ac,
                    'wins' => (int) $r->wc,
                    'win_rate' => round((int) $r->wc / $ac, 4),
                ];
            });

        $bestHours = $hourly->sortByDesc(fn ($v) => $v['win_rate'])->keys()->take(6)->values()->all();

        $trends = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = Carbon::today()->subDays($i)->toDateString();
            $won = Deal::query()->where('status', 'completed')->whereDate('updated_at', $d)->count();
            $lost = Deal::query()->where('status', 'cancelled')->whereDate('updated_at', $d)->count();
            $assigned = LeadAssignmentLog::query()->whereDate('created_at', $d)->count();
            $trends[] = [
                'date' => $d,
                'deals_won' => $won,
                'deals_lost' => $lost,
                'assignments_logged' => $assigned,
            ];
        }

        return [
            'best_sales_by_context' => $bestByContext->all(),
            'best_assignment_hours' => $bestHours,
            'hourly_win_rates' => $hourly->sortKeys()->all(),
            'conversion_trends_14d' => $trends,
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
