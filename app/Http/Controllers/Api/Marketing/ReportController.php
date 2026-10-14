<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Models\MarketingCalendarPost;
use App\Models\MarketingStage;
use App\Models\MarketingTask;
use App\Models\MarketingTaskHistory;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/**
 * Admin only (route middleware): the marketing team's daily report.
 * Kept simple: totals, one row per team member, what was finished, and what is overdue.
 * The same data feeds the on-screen popup (JSON) and the PDF download (server-rendered).
 */
class ReportController extends MarketingBaseController
{
    private const BOARDS = ['production' => 'Production', 'design' => 'Design', 'content' => 'Content'];

    public function daily(Request $request): JsonResponse
    {
        return response()->json($this->build($this->day($request)));
    }

    public function pdf(Request $request): Response
    {
        $report = $this->build($this->day($request));

        return Pdf::loadView('pdf.marketing-daily-report', ['r' => $report])
            ->setPaper('a4', 'portrait')
            ->setOption(['isFontSubsettingEnabled' => true])
            ->download("marketing-daily-report-{$report['date']}.pdf");
    }

    private function day(Request $request): Carbon
    {
        $validated = $request->validate(['date' => ['nullable', 'date', 'before_or_equal:today']]);

        return isset($validated['date']) ? Carbon::parse($validated['date']) : now();
    }

    private function build(Carbon $day): array
    {
        $start = $day->copy()->startOfDay();
        $end = $day->copy()->endOfDay();

        $doneStages = MarketingStage::where('is_done', true)->get(['id', 'name']);
        $doneStageNames = $doneStages->pluck('name')->unique()->all();

        $histories = MarketingTaskHistory::query()
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('user_id')
            ->with('user:id,name')
            ->get();

        // One row per person. "Finished" = distinct cards they moved into a done stage.
        $team = $histories->groupBy('user_id')->map(function (Collection $rows) use ($doneStageNames) {
            $finished = $rows->filter(function ($h) use ($doneStageNames) {
                $new = collect($h->changes ?? [])->firstWhere('field', 'stage_id')['new'] ?? null;

                return $h->action === 'moved' && in_array($new, $doneStageNames, true);
            })->pluck('task_id')->unique()->count();

            return [
                'name' => $rows->first()->user?->name ?? 'Unknown',
                'created' => $rows->where('action', 'created')->count(),
                'moved' => $rows->where('action', 'moved')->count(),
                'finished' => $finished,
                'links' => $rows->where('action', 'link_added')->count(),
                'comments' => $rows->where('action', 'commented')->count(),
                'posted' => $rows->where('action', 'posted')->count(),
                'total' => $rows->count(),
            ];
        })->sortByDesc('total')->values();

        $people = fn (MarketingTask $t) => collect([$t->shooter?->name, $t->editor?->name, $t->assignee?->name])
            ->filter()->unique()->implode(', ') ?: '—';

        $finished = MarketingTask::query()
            ->whereBetween('completed_at', [$start, $end])
            ->with(['assignee:id,name', 'shooter:id,name', 'editor:id,name'])
            ->withCount('links')
            ->orderBy('completed_at')
            ->get()
            ->map(fn (MarketingTask $t) => [
                'title' => $t->title,
                'board' => self::BOARDS[$t->category] ?? $t->category,
                'people' => $people($t),
                'time' => $t->completed_at->format('g:i A'),
                'has_link' => $t->links_count > 0,
            ])->values();

        $overdueQuery = MarketingTask::query()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $start->toDateString())
            ->whereNotIn('stage_id', $doneStages->pluck('id'));

        $overdue = (clone $overdueQuery)
            ->with(['stage:id,name', 'assignee:id,name', 'shooter:id,name', 'editor:id,name'])
            ->orderBy('due_date')
            ->limit(20)
            ->get()
            ->map(fn (MarketingTask $t) => [
                'title' => $t->title,
                'board' => self::BOARDS[$t->category] ?? $t->category,
                'stage' => $t->stage?->name ?? '—',
                'due' => $t->due_date->format('d M Y'),
                'days_late' => (int) abs($t->due_date->diffInDays($start)),
                'people' => $people($t),
            ])->values();

        $posts = MarketingCalendarPost::query()->whereDate('scheduled_date', $start->toDateString());

        return [
            'date' => $start->toDateString(),
            'date_label' => $start->format('l, j F Y'),
            'generated_at' => now()->format('d M Y, g:i A'),
            'generated_by' => auth()->user()?->name,
            'totals' => [
                'created' => $histories->where('action', 'created')->count(),
                'finished' => $finished->count(),
                'posts_posted' => (clone $posts)->where('is_posted', true)->count(),
                'posts_scheduled' => (clone $posts)->count(),
                'overdue' => (clone $overdueQuery)->count(),
            ],
            'team' => $team,
            'finished' => $finished,
            'overdue' => $overdue,
        ];
    }
}
