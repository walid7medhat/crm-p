<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Models\MarketingStage;
use App\Models\MarketingTask;
use App\Services\Marketing\MarketingHistoryRecorder;
use App\Services\Marketing\MarketingLinkClassifier;
use App\Services\Marketing\MarketingPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TaskController extends MarketingBaseController
{
    /** Fields each category is allowed to keep; everything else is cleared on save. */
    private const CATEGORY_FIELDS = [
        'production' => ['department', 'type', 'shooting_by', 'editing_by', 'channels', 'reference', 'notes', 'due_date'],
        'design' => ['department', 'assigned_to', 'deliverables', 'tov', 'reference', 'notes', 'due_date'],
        'content' => ['department', 'assigned_to', 'reference', 'notes', 'due_date'],
    ];

    private const OPTIONAL_FIELDS = ['department','type', 'assigned_to', 'shooting_by', 'editing_by', 'deliverables', 'channels', 'tov', 'reference', 'notes', 'due_date'];

    public function show(MarketingTask $task): JsonResponse
    {
        $task->load(MarketingPresenter::TASK_RELATIONS)
            ->loadCount(MarketingPresenter::taskCounts())
            ->load([
                'comments' => fn ($q) => $q->with('user:id,name,avatar')->latest('id'),
                'histories' => fn ($q) => $q->with('user:id,name,avatar')->latest('id'),
                'posts' => fn ($q) => $q->with(['link', 'poster:id,name'])->orderBy('scheduled_date'),
                'stage',
            ]);

        return response()->json([
            'task' => MarketingPresenter::task($task) + [
                'stage' => $task->stage ? ['id' => $task->stage->id, 'name' => $task->stage->name, 'color' => $task->stage->color, 'is_done' => $task->stage->is_done] : null,
                'comments' => $task->comments->map(fn ($c) => MarketingPresenter::comment($c))->values(),
                'histories' => $task->histories->map(fn ($h) => MarketingPresenter::history($h))->values(),
                'posts' => $task->posts->map(fn ($p) => MarketingPresenter::post($p))->values(),
            ],
            'can_manage' => $this->isAdmin(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules(true) + [
            'stage_id' => ['nullable', 'integer'],
            'links' => ['nullable', 'array', 'max:20'],
            'links.*.url' => ['required', 'url', 'max:2048'],
            'links.*.label' => ['nullable', 'string', 'max:255'],
        ]);

        $category = $validated['category'];
        $stage = isset($validated['stage_id'])
            ? MarketingStage::where('category', $category)->find($validated['stage_id'])
            : MarketingStage::where('category', $category)->orderBy('sort_order')->orderBy('id')->first();

        if (!$stage) {
            return response()->json(['message' => 'No stage found for this board. Ask an admin to add one.'], 422);
        }

        $task = DB::transaction(function () use ($validated, $category, $stage) {
            // New cards land on top of their column.
            MarketingTask::where('stage_id', $stage->id)->increment('position');

            $task = MarketingTask::create($this->onlyCategoryFields($category, $validated) + [
                'category' => $category,
                'title' => $validated['title'],
                'stage_id' => $stage->id,
                'position' => 0,
                'created_by' => Auth::id(),
                'completed_at' => $stage->is_done ? now() : null,
            ]);

            foreach ($validated['links'] ?? [] as $link) {
                $label = trim($link['label'] ?? '') ?: MarketingLinkClassifier::defaultLabel($link['url']);
                $task->links()->create([
                    'url' => $link['url'],
                    'label' => $label,
                    'kind' => MarketingLinkClassifier::kind($link['url'], $label),
                    'added_by' => Auth::id(),
                ]);
            }

            MarketingHistoryRecorder::record($task, 'created', [[
                'field' => 'stage_id', 'label' => 'Stage', 'old' => null, 'new' => $stage->name,
            ]]);

            return $task;
        });

        return response()->json(['task' => $this->fresh($task)], 201);
    }

    public function update(Request $request, MarketingTask $task): JsonResponse
    {
        $validated = $request->validate($this->rules(false, $task));

        $before = MarketingHistoryRecorder::snapshot($task);

        $data = $this->onlyCategoryFields($task->category, $validated, partial: true);
        if (array_key_exists('title', $validated)) {
            $data['title'] = $validated['title'];
        }

        $task->fill($data)->save();

        $changes = MarketingHistoryRecorder::diff($before, $task->fresh());
        if ($changes) {
            MarketingHistoryRecorder::record($task, 'updated', $changes);
        }

        return response()->json(['task' => $this->fresh($task)]);
    }

    /**
     * Drag & drop: move to a stage (same category only) at a given index, renumbering
     * the source and target columns. Entering a "done" stage stamps completed_at.
     */
    public function move(Request $request, MarketingTask $task): JsonResponse
    {
        $validated = $request->validate([
            'stage_id' => ['required', 'integer'],
            'position' => ['required', 'integer', 'min:0'],
        ]);

        $target = MarketingStage::where('category', $task->category)->find($validated['stage_id']);
        if (!$target) {
            return response()->json(['message' => 'Cards can only move between stages of the same board.'], 422);
        }

        $fromStageId = $task->stage_id;

        DB::transaction(function () use ($task, $target, $fromStageId, $validated) {
            $ids = MarketingTask::where('stage_id', $target->id)
                ->where('id', '!=', $task->id)
                ->orderBy('position')->orderByDesc('id')
                ->pluck('id')->all();

            array_splice($ids, min($validated['position'], count($ids)), 0, [$task->id]);

            foreach ($ids as $index => $id) {
                MarketingTask::whereKey($id)->update(['position' => $index]);
            }

            if ($fromStageId !== $target->id) {
                $task->refresh();
                $task->stage_id = $target->id;
                if ($target->is_done) {
                    $task->completed_at ??= now();
                } else {
                    $task->completed_at = null;
                }
                $task->save();

                MarketingTask::where('stage_id', $fromStageId)
                    ->orderBy('position')->orderByDesc('id')
                    ->pluck('id')
                    ->each(fn ($id, $index) => MarketingTask::whereKey($id)->update(['position' => $index]));

                MarketingHistoryRecorder::record($task, 'moved', MarketingHistoryRecorder::stageChange($fromStageId, $target->id));
            }
        });

        $task->refresh();

        return response()->json([
            'task' => $this->fresh($task),
            // UI suggests adding the final link when a card reaches Done empty-handed.
            'suggest_link' => $target->is_done && $fromStageId !== $target->id && !$task->links()->exists(),
        ]);
    }

    public function approve(Request $request, MarketingTask $task): JsonResponse
    {
        $validated = $request->validate(['approved' => ['required', 'boolean']]);

        if ($task->is_approved !== $validated['approved']) {
            $task->update([
                'is_approved' => $validated['approved'],
                'approved_by' => $validated['approved'] ? Auth::id() : null,
                'approved_at' => $validated['approved'] ? now() : null,
            ]);
            MarketingHistoryRecorder::record($task, $validated['approved'] ? 'approved' : 'unapproved');
        }

        return response()->json(['task' => $this->fresh($task)]);
    }

    /** Admin only (route middleware). */
    public function destroy(MarketingTask $task): JsonResponse
    {
        MarketingHistoryRecorder::record($task, 'deleted');
        $task->delete();

        return response()->json(['message' => 'Card deleted.']);
    }

    private function rules(bool $creating, ?MarketingTask $task = null): array
    {
        $req = $creating ? 'required' : 'sometimes';
        $member = ['nullable', 'integer', $this->memberRule(
            $task ? [$task->assigned_to, $task->shooting_by, $task->editing_by] : []
        )];

        return array_filter([
            'category' => $creating ? ['required', Rule::in(MarketingStage::CATEGORIES)] : null,
            'title' => [$req, 'string', 'max:255'],
            'type' => ['nullable', Rule::in(MarketingTask::TYPES)],
            'assigned_to' => $member,
            'shooting_by' => $member,
            'editing_by' => $member,
            'deliverables' => ['nullable', 'array'],
            'deliverables.*' => [Rule::in(MarketingTask::DELIVERABLES)],
            'channels' => ['nullable', 'array'],
            'channels.*' => ['boolean'],
            'tov' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'due_date' => ['nullable', 'date'],
            'department' => [$req, Rule::in(MarketingTask::DEPARTMENTS)],
        ]);
    }

    /** Keep only the fields that belong to this category; clear the rest. */
    private function onlyCategoryFields(string $category, array $validated, bool $partial = false): array
    {
        $allowed = self::CATEGORY_FIELDS[$category];
        $data = [];

        foreach (self::OPTIONAL_FIELDS as $field) {
            if (!in_array($field, $allowed, true)) {
                if (!$partial) {
                    $data[$field] = null;
                }
                continue;
            }
            if ($partial && !array_key_exists($field, $validated)) {
                continue;
            }
            $data[$field] = $validated[$field] ?? null;
        }

        if (isset($data['channels']) && is_array($data['channels'])) {
            $data['channels'] = array_intersect_key(
                array_map('boolval', $data['channels']),
                array_flip(MarketingTask::CHANNELS)
            );
        }
        if (isset($data['deliverables'])) {
            $data['deliverables'] = array_values(array_unique($data['deliverables']));
        }
        if (($data['type'] ?? null) === null && $category === 'production' && !$partial) {
            $data['type'] = 'video';
        }

        return $data;
    }

    private function fresh(MarketingTask $task): array
    {
        return MarketingPresenter::task(
            $task->fresh()->load(MarketingPresenter::TASK_RELATIONS)->loadCount(MarketingPresenter::taskCounts())
        );
    }
}
