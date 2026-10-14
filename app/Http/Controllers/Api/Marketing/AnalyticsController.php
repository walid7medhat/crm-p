<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Models\MarketingCalendarPost;
use App\Models\MarketingStage;
use App\Models\MarketingTask;
use App\Models\MarketingTaskHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Marketing analytics for a date range. "Current" figures (by stage, active) reflect the
 * board right now; period figures (done, workload, revisions, posts) use the range.
 */
class AnalyticsController extends MarketingBaseController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'category' => ['nullable', Rule::in(MarketingStage::CATEGORIES)],
        ]);

        $from = isset($validated['from']) ? Carbon::parse($validated['from'])->startOfDay() : now()->subDays(29)->startOfDay();
        $to = isset($validated['to']) ? Carbon::parse($validated['to'])->endOfDay() : now()->endOfDay();
        $category = $validated['category'] ?? null;

        $stages = MarketingStage::query()
            ->when($category, fn ($q) => $q->where('category', $category))
            ->orderBy('sort_order')->get();
        $doneStageIds = $stages->where('is_done', true)->pluck('id');
        $revisionStageIds = $stages->filter(fn ($s) => stripos($s->name, 'revision') !== false)->pluck('id');

        $tasks = MarketingTask::query()
            ->when($category, fn ($q) => $q->where('category', $category))
            ->get(['id', 'category', 'type', 'stage_id', 'assigned_to', 'shooting_by', 'editing_by', 'created_at', 'completed_at']);

        $doneInPeriod = $tasks->filter(fn ($t) => $t->completed_at && $t->completed_at->between($from, $to));
        $createdInPeriod = $tasks->filter(fn ($t) => $t->created_at && $t->created_at->between($from, $to));

        $posts = MarketingCalendarPost::query()
            ->whereBetween('scheduled_date', [$from->toDateString(), $to->toDateString()])
            ->when($category, fn ($q) => $q->whereHas('task', fn ($t) => $t->where('category', $category)))
            ->get(['id', 'platform', 'scheduled_date', 'is_posted']);

        $avgDays = $this->avgDays($doneInPeriod);

        // Same-length window right before the range, for "vs previous period" deltas.
        $prevTo = $from->copy()->subSecond();
        // Carbon 3's diff is signed — use the absolute span.
        $prevFrom = $prevTo->copy()->subSeconds((int) abs($from->diffInSeconds($to)))->startOfDay();
        $donePrev = $tasks->filter(fn ($t) => $t->completed_at && $t->completed_at->between($prevFrom, $prevTo));
        $postedPrev = MarketingCalendarPost::query()
            ->whereBetween('scheduled_date', [$prevFrom->toDateString(), $prevTo->toDateString()])
            ->where('is_posted', true)
            ->when($category, fn ($q) => $q->whereHas('task', fn ($t) => $t->where('category', $category)))
            ->count();

        return response()->json([
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'kpis' => [
                'total' => $tasks->count(),
                'active' => $tasks->whereNotIn('stage_id', $doneStageIds)->count(),
                'created' => $createdInPeriod->count(),
                'done' => $doneInPeriod->count(),
                'in_revision' => $tasks->whereIn('stage_id', $revisionStageIds)->count(),
                'avg_days_to_done' => $avgDays,
                'posts_scheduled' => $posts->count(),
                'posts_posted' => $posts->where('is_posted', true)->count(),
            ],
            'previous' => [
                'from' => $prevFrom->toDateString(),
                'to' => $prevTo->toDateString(),
                'created' => $tasks->filter(fn ($t) => $t->created_at && $t->created_at->between($prevFrom, $prevTo))->count(),
                'done' => $donePrev->count(),
                'avg_days_to_done' => $this->avgDays($donePrev),
                'posts_posted' => $postedPrev,
            ],
            // Posting heatmap: platform × weekday (1 = Mon … 7 = Sun).
            'heatmap' => collect(MarketingCalendarPost::PLATFORMS)->map(fn ($p) => [
                'key' => $p,
                'days' => collect(range(1, 7))->map(function ($d) use ($posts, $p) {
                    $cell = $posts->filter(fn ($post) => $post->platform === $p && $post->scheduled_date->dayOfWeekIso === $d);
                    return ['scheduled' => $cell->count(), 'posted' => $cell->where('is_posted', true)->count()];
                })->values(),
            ])->values(),
            'by_stage' => $this->byStage($stages, $tasks, (bool) $category),
            'by_category' => collect(MarketingStage::CATEGORIES)
                ->map(fn ($c) => ['key' => $c, 'count' => $tasks->where('category', $c)->count()])->values(),
            'by_type' => collect(MarketingTask::TYPES)
                ->map(fn ($t) => ['key' => $t, 'count' => $tasks->where('type', $t)->count()])->values(),
            'workload' => $this->workload($createdInPeriod, $doneInPeriod),
            'revisions' => $this->revisions($from, $to, $category),
            'posts_by_platform' => collect(MarketingCalendarPost::PLATFORMS)->map(fn ($p) => [
                'key' => $p,
                'scheduled' => $posts->where('platform', $p)->count(),
                'posted' => $posts->where('platform', $p)->where('is_posted', true)->count(),
            ])->values(),
            'weekly' => $this->weekly($from, $to, $createdInPeriod, $doneInPeriod),
        ]);
    }

    /** With no category filter, stages of the three boards are merged by name. */
    private function byStage(Collection $stages, Collection $tasks, bool $single): array
    {
        $counts = $tasks->countBy('stage_id');

        if ($single) {
            return $stages->map(fn ($s) => ['name' => $s->name, 'color' => $s->color, 'count' => (int) ($counts[$s->id] ?? 0)])->values()->all();
        }

        return $stages->groupBy('name')->map(fn ($group, $name) => [
            'name' => $name,
            'color' => $group->first()->color,
            'count' => (int) $group->sum(fn ($s) => $counts[$s->id] ?? 0),
        ])->values()->all();
    }

    private function avgDays(Collection $done): ?float
    {
        return $done->isEmpty() ? null
            : round($done->avg(fn ($t) => $t->created_at->diffInHours($t->completed_at) / 24), 1);
    }

    /**
     * Per person: cards shot / edited / assigned (design + content) created in the period,
     * plus how many of the cards they worked on reached Done in the period (leaderboard rank).
     */
    private function workload(Collection $tasks, Collection $done): array
    {
        $fields = ['shooting_by' => 'shot', 'editing_by' => 'edited', 'assigned_to' => 'assigned'];
        $rows = [];
        foreach ($fields as $field => $key) {
            foreach ($tasks->whereNotNull($field)->countBy($field) as $userId => $n) {
                $rows[$userId] ??= ['shot' => 0, 'edited' => 0, 'assigned' => 0];
                $rows[$userId][$key] = $n;
            }
        }

        $doneBy = [];
        foreach ($done as $t) {
            foreach (array_unique(array_filter([$t->shooting_by, $t->editing_by, $t->assigned_to])) as $userId) {
                $doneBy[$userId] = ($doneBy[$userId] ?? 0) + 1;
                $rows[$userId] ??= ['shot' => 0, 'edited' => 0, 'assigned' => 0];
            }
        }

        $users = User::whereIn('id', array_keys($rows))->get(['id', 'name', 'avatar'])->keyBy('id');

        return collect($rows)
            ->map(fn ($r, $id) => [
                'user_id' => (int) $id,
                'name' => $users[$id]->name ?? "#{$id}",
                'avatar_url' => isset($users[$id]) ? asset('storage/' . $users[$id]->avatar) : null,
            ] + $r + ['total' => array_sum($r), 'done' => $doneBy[$id] ?? 0])
            ->sortByDesc(fn ($r) => [$r['done'], $r['total']])
            ->values()->take(15)->all();
    }

    /** Moves into an "On Revision" stage during the period, credited to the card's responsible person. */
    private function revisions(Carbon $from, Carbon $to, ?string $category): array
    {
        $moves = MarketingTaskHistory::query()
            ->where('action', 'moved')
            ->whereBetween('created_at', [$from, $to])
            ->with('task:id,category,assigned_to,editing_by,shooting_by')
            ->get()
            ->filter(function ($h) use ($category) {
                $new = collect($h->changes ?? [])->firstWhere('field', 'stage_id')['new'] ?? '';
                return $h->task
                    && stripos((string) $new, 'revision') !== false
                    && (!$category || $h->task->category === $category);
            });

        $counts = $moves->countBy(fn ($h) => $h->task->assigned_to ?? $h->task->editing_by ?? $h->task->shooting_by ?? 0)
            ->forget(0);

        $names = User::whereIn('id', $counts->keys())->pluck('name', 'id');

        return $counts->map(fn ($n, $id) => ['user_id' => (int) $id, 'name' => $names[$id] ?? "#{$id}", 'count' => $n])
            ->sortByDesc('count')->values()->take(15)->all();
    }

    private function weekly(Carbon $from, Carbon $to, Collection $created, Collection $done): array
    {
        $weeks = [];
        for ($cursor = $from->copy()->startOfWeek(); $cursor->lte($to); $cursor->addWeek()) {
            $start = $cursor->copy();
            $end = $cursor->copy()->endOfWeek();
            $weeks[] = [
                'week' => $start->format('d M'),
                'created' => $created->filter(fn ($t) => $t->created_at->between($start, $end))->count(),
                'done' => $done->filter(fn ($t) => $t->completed_at->between($start, $end))->count(),
            ];
        }

        return $weeks;
    }
}
