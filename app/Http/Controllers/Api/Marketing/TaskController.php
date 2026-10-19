<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Models\MarketingStage;
use App\Models\MarketingTask;
use App\Services\Marketing\MarketingHistoryRecorder;
use App\Services\Marketing\MarketingLinkClassifier;
use App\Services\Marketing\MarketingPresenter;
use App\Services\Marketing\MarketingTaskMirror;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TaskController extends MarketingBaseController
{
    /** Fields each category is allowed to keep; everything else is cleared on save. */
    private const CATEGORY_FIELDS = [
        'production' => ['department', 'type', 'shooting_by', 'editing_by', 'channels', 'reference', 'notes', 'due_date'],
        'design' => ['department', 'assigned_to', 'deliverables', 'tov', 'reference', 'notes', 'due_date'],
        'content' => ['department', 'assigned_to', 'designed_by', 'also_video', 'reference', 'notes', 'due_date'],
    ];

    private const OPTIONAL_FIELDS = ['department', 'type', 'assigned_to', 'designed_by', 'also_video', 'shooting_by', 'editing_by', 'deliverables', 'channels', 'tov', 'reference', 'notes', 'due_date'];

    public function show(MarketingTask $task): JsonResponse
    {
        $canSeeHistory = $this->isAdmin();

        $task->load(MarketingPresenter::TASK_RELATIONS)
            ->loadCount(MarketingPresenter::taskCounts())
            ->load([
                'comments' => fn ($q) => $q->with('user:id,name,avatar')->latest('id'),
                'posts' => fn ($q) => $q->with(['link', 'poster:id,name'])->orderBy('scheduled_date'),
                'stage',
            ]);

        if ($canSeeHistory) {
            $task->load(['histories' => fn ($q) => $q->with('user:id,name,avatar')->latest('id')]);
        }

        return response()->json([
            'task' => MarketingPresenter::task($task) + [
                'stage' => $task->stage ? ['id' => $task->stage->id, 'name' => $task->stage->name, 'color' => $task->stage->color, 'is_done' => $task->stage->is_done] : null,
                'comments' => $task->comments->map(fn ($c) => MarketingPresenter::comment($c))->values(),
                'histories' => $canSeeHistory
                    ? $task->histories->map(fn ($h) => MarketingPresenter::history($h))->values()
                    : [],
                'posts' => $task->posts->map(fn ($p) => MarketingPresenter::post($p))->values(),
            ],
            'can_manage' => $canSeeHistory,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->blankToNull($request);
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

        $task = DB::transaction(function () use ($request, $validated, $category, $stage) {
            // New cards land on top of their column.
            MarketingTask::where('stage_id', $stage->id)->increment('position');

            $task = MarketingTask::create($this->referenceImageChanges($request, $this->onlyCategoryFields($category, $validated)) + [
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

            MarketingTaskMirror::sync($task);

            return $task;
        });

        return response()->json(['task' => $this->fresh($task)], 201);
    }

    public function update(Request $request, MarketingTask $task): JsonResponse
    {
        $this->blankToNull($request);
        $validated = $request->validate($this->rules(false, $task));

        $before = MarketingHistoryRecorder::snapshot($task);
        $oldImage = $task->reference_image;

        $data = $this->referenceImageChanges($request, $this->onlyCategoryFields($task->category, $validated, partial: true));
        if (array_key_exists('title', $validated)) {
            $data['title'] = $validated['title'];
        }

        DB::transaction(function () use ($task, $data, $before) {
            $task->fill($data)->save();

            $changes = MarketingHistoryRecorder::diff($before, $task->fresh());
            if ($changes) {
                MarketingHistoryRecorder::record($task, 'updated', $changes);
            }

            MarketingTaskMirror::sync($task->fresh());
        });

        $current = $task->fresh()->reference_image;
        if ($oldImage && $oldImage !== $current) {
            $this->forgetReferenceImage($oldImage);
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

    /**
     * Content card only. Sets the designer and puts the Design copy in the first Design stage.
     */
    public function reassign(Request $request, MarketingTask $task): JsonResponse
    {
        $validated = $request->validate([
            'designed_by' => ['required', 'integer', $this->memberRule([$task->designed_by])],
        ]);

        DB::transaction(function () use ($task, $validated) {
            MarketingTaskMirror::reassignDesigner($task, (int) $validated['designed_by']);
        });

        return response()->json(['task' => $this->fresh($task)]);
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
        $image = $task->reference_image;

        DB::transaction(function () use ($task) {
            if ($task->category === 'content' && !$task->source_task_id) {
                MarketingTask::where('source_task_id', $task->id)->get()->each(function (MarketingTask $mirror) {
                    MarketingHistoryRecorder::record($mirror, 'deleted');
                    $mirror->delete();
                });
            } elseif ($task->source_task_id) {
                $source = MarketingTask::find($task->source_task_id);
                if ($source) {
                    if ($task->category === 'design') {
                        $source->designed_by = null;
                    }
                    if ($task->category === 'production') {
                        $source->also_video = false;
                    }
                    $source->save();
                }
            }

            MarketingHistoryRecorder::record($task, 'deleted');
            $task->delete();
        });

        $this->forgetReferenceImage($image);

        return response()->json(['message' => 'Card deleted.']);
    }

    private function rules(bool $creating, ?MarketingTask $task = null): array
    {
        $req = $creating ? 'required' : 'sometimes';
        $member = ['nullable', 'integer', $this->memberRule(
            $task ? [$task->assigned_to, $task->designed_by, $task->shooting_by, $task->editing_by] : []
        )];

        return array_filter([
            'category' => $creating ? ['required', Rule::in(MarketingStage::CATEGORIES)] : null,
            'title' => [$req, 'string', 'max:255'],
            'type' => ['nullable', Rule::in(MarketingTask::TYPES)],
            'assigned_to' => $member,
            'designed_by' => $member,
            'also_video' => ['nullable', 'boolean'],
            'shooting_by' => $member,
            'editing_by' => $member,
            'deliverables' => ['nullable', 'array'],
            'deliverables.*' => [Rule::in(MarketingTask::DELIVERABLES)],
            'channels' => ['nullable', 'array'],
            'channels.*' => ['boolean'],
            'tov' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:5000'],
            'reference_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:8192'],
            'remove_reference_image' => ['nullable', 'boolean'],
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
                    $data[$field] = $field === 'also_video' ? false : null;
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

        if (array_key_exists('also_video', $data)) {
            $data['also_video'] = (bool) $data['also_video'];
        }

        return $data;
    }

    /** Multipart forms send unused optional fields as empty strings. */
    private function blankToNull(Request $request): void
    {
        $merged = [];
        foreach (['designed_by', 'assigned_to', 'shooting_by', 'editing_by', 'due_date', 'reference', 'notes', 'tov', 'type', 'stage_id'] as $field) {
            if ($request->input($field) === '') {
                $merged[$field] = null;
            }
        }
        if ($request->input('deliverables') === '' || $request->input('deliverables') === ['']) {
            $merged['deliverables'] = [];
        }
        if ($merged) {
            $request->merge($merged);
        }
    }

    /** @param array<string, mixed> $data */
    private function referenceImageChanges(Request $request, array $data): array
    {
        if ($request->hasFile('reference_image')) {
            $data['reference_image'] = $request->file('reference_image')->store('marketing/references', 'public');
        } elseif ($request->boolean('remove_reference_image')) {
            $data['reference_image'] = null;
        }

        return $data;
    }

    private function forgetReferenceImage(?string $path): void
    {
        if ($path && !MarketingTask::where('reference_image', $path)->exists()) {
            Storage::disk('public')->delete($path);
        }
    }

    private function fresh(MarketingTask $task): array
    {
        return MarketingPresenter::task(
            $task->fresh()->load(MarketingPresenter::TASK_RELATIONS)->loadCount(MarketingPresenter::taskCounts())
        );
    }
}
