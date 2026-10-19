<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Models\MarketingStage;
use App\Models\MarketingTask;
use App\Services\Marketing\MarketingPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BoardController extends MarketingBaseController
{
    /**
     * One category's board: its stages (ordered) each with matching cards (ordered by position).
     */
    public function board(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['required', Rule::in(MarketingStage::CATEGORIES)],
            'search' => ['nullable', 'string', 'max:200'],
            'assignee' => ['nullable', 'integer'],
            'type' => ['nullable', Rule::in(MarketingTask::TYPES)],
            'deliverable' => ['nullable', Rule::in(MarketingTask::DELIVERABLES)],
            'due' => ['nullable', Rule::in(['overdue', 'week'])],
            'sort' => ['nullable', Rule::in(['asc', 'desc', 'due_asc', 'due_desc'])],
        ]);

        $category = $validated['category'];

        $stages = MarketingStage::where('category', $category)
            ->orderBy('sort_order')->orderBy('id')
            ->get();

        $query = MarketingTask::query()
            ->where('category', $category)
            ->with(MarketingPresenter::TASK_RELATIONS)
            ->withCount(MarketingPresenter::taskCounts())
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'),   fn ($q) => $q->whereDate('created_at', '<=', $request->date_to));

        $sort = $validated['sort'] ?? null;
        if ($sort === 'asc' || $sort === 'desc') {
            $query->orderBy('created_at', $sort)->orderBy('id', $sort);
        } elseif ($sort === 'due_asc' || $sort === 'due_desc') {
            // Cards with no due date stay at the bottom of the column.
            $query->orderByRaw('due_date IS NULL')
                ->orderBy('due_date', $sort === 'due_desc' ? 'desc' : 'asc')
                ->orderBy('id');
        } else {
            $query->orderBy('position')->orderByDesc('id');
        }

        if ($search = trim($validated['search'] ?? '')) {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->where('title', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhere('reference', 'like', $like)
                    ->orWhere('tov', 'like', $like)
                    ->orWhereHas('links', fn ($l) => $l->where(fn ($w) => $w->where('label', 'like', $like)->orWhere('url', 'like', $like)));
                foreach (['assignee', 'designer', 'shooter', 'editor'] as $rel) {
                    $q->orWhereHas($rel, fn ($u) => $u->where('name', 'like', $like));
                }
            });
        }

        if ($assignee = $validated['assignee'] ?? null) {
            $query->where(fn ($q) => $q->where('assigned_to', $assignee)
                ->orWhere('designed_by', $assignee)
                ->orWhere('shooting_by', $assignee)
                ->orWhere('editing_by', $assignee));
        }

        if ($type = $validated['type'] ?? null) {
            $query->where('type', $type);
        }

        if ($deliverable = $validated['deliverable'] ?? null) {
            $query->whereJsonContains('deliverables', $deliverable);
        }

        if (($validated['due'] ?? null) === 'overdue') {
            $doneStageIds = $stages->where('is_done', true)->pluck('id');
            $query->whereDate('due_date', '<', now()->toDateString())->whereNotIn('stage_id', $doneStageIds);
        } elseif (($validated['due'] ?? null) === 'week') {
            $query->whereBetween('due_date', [now()->toDateString(), now()->addDays(7)->toDateString()]);
        }

        $tasksByStage = $query->get()->groupBy('stage_id');

        return response()->json([
            'stages' => $stages->map(fn (MarketingStage $stage) => [
                'id' => $stage->id,
                'name' => $stage->name,
                'color' => $stage->color,
                'icon' => $stage->icon,
                'is_done' => $stage->is_done,
                'sort_order' => $stage->sort_order,
                'tasks' => ($tasksByStage[$stage->id] ?? collect())
                    ->map(fn (MarketingTask $t) => MarketingPresenter::task($t))->values(),
            ])->values(),
            'can_manage' => $this->isAdmin(),
        ]);
    }

    /** Assignee pool: active users with the `marketing` permission. */
    public function members(): JsonResponse
    {
        $members = $this->memberQuery()
            ->orderBy('name')
            ->get(['id', 'name', 'avatar'])
            ->map(fn ($u) => MarketingPresenter::user($u))
            ->values();

        return response()->json(['members' => $members, 'can_manage' => $this->isAdmin()]);
    }
}
