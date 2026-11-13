<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Models\MarketingStage;
use App\Models\MarketingTask;
use App\Services\Marketing\MarketingHistoryRecorder;
use App\Services\Marketing\MarketingLinkClassifier;
use App\Services\Marketing\MarketingPresenter;
use App\Services\Marketing\MarketingTaskMirror;
use App\Models\MarketingTaskComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Notifications\MarketingMention;
class TaskController extends MarketingBaseController
{
    /** Fields each category is allowed to keep; everything else is cleared on save. */
    private const CATEGORY_FIELDS = [
        'production' => ['department', 'type', 'shooting_by', 'editing_by', 'channels', 'deliverables', 'reference', 'notes', 'due_date'],
        'design' => ['department', 'assigned_to', 'deliverables', 'tov', 'reference', 'notes', 'due_date'],
        'content' => ['department', 'assigned_to', 'designed_by', 'also_video', 'deliverables', 'reference', 'notes', 'due_date'],
    ];

    private const OPTIONAL_FIELDS = ['department', 'type', 'assigned_to', 'designed_by', 'also_video', 'shooting_by', 'editing_by', 'deliverables', 'channels', 'tov', 'reference', 'notes', 'due_date'];

    public function show(MarketingTask $task): JsonResponse
    {
        $canSeeHistory = $this->isAdmin();
        $familyIds = MarketingTaskMirror::familyIds($task);

        $task->load(MarketingPresenter::TASK_RELATIONS)
            ->loadCount(MarketingPresenter::taskCounts())
            ->load([
                'posts' => fn ($q) => $q->with(['link', 'poster:id,name'])->orderBy('scheduled_date'),
                'stage',
            ]);

        MarketingTaskMirror::hydrateShared([$task]);

        $comments = MarketingTaskComment::query()
            ->whereIn('task_id', $familyIds)
            ->with('user:id,name,display_name,avatar')
            ->latest('id')
            ->get();

        if ($canSeeHistory) {
            $task->load(['histories' => fn ($q) => $q->with('user:id,name,avatar')->latest('id')]);
        }
       $unreadMentions = Auth::user()->unreadNotifications()
            ->where('type', MarketingMention::class)
            ->get()
            ->filter(fn ($n) => in_array((int) ($n->data['marketing_task_id'] ?? 0), $familyIds, true));

        $hadMention = $unreadMentions->isNotEmpty();
        $unreadMentions->each->markAsRead();
        return response()->json([
            'task' => MarketingPresenter::task($task) + [
                 'mentioned_me' => $hadMention,
                'stage' => $task->stage ? [
                    'id' => $task->stage->id,
                    'name' => $task->stage->name,
                    'color' => $task->stage->color,
                    'is_done' => $task->stage->is_done,
                    'is_archive' => (bool) $task->stage->is_archive,
                ] : null,
                'comments' => $comments->map(fn ($c) => MarketingPresenter::comment($c))->values(),
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
            ? MarketingStage::where('category', $category)->where('is_archive', false)->find($validated['stage_id'])
            : MarketingStage::firstOpen($category);

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

        if ($target->is_archive) {
            return response()->json(['message' => 'Use delete to move a card to Archive.'], 422);
        }

        $fromStage = $task->stage;
        if ($fromStage?->is_archive && ! $this->isAdmin()) {
            return response()->json(['message' => 'Only an admin can restore a card from Archive.'], 403);
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

    /**
     * Creator only: soft-delete by moving the card (and content mirrors) to Archive.
     * Archive is visible only to admin / super_admin.
     */
    public function destroy(MarketingTask $task): JsonResponse
    {
        if ((int) $task->created_by !== (int) Auth::id()) {
            return response()->json(['message' => 'You can only delete cards you created.'], 403);
        }

        $archive = MarketingStage::archiveFor($task->category);
        if (! $archive) {
            return response()->json(['message' => 'Archive stage is missing. Ask an admin to restore it.'], 422);
        }

        if ((int) $task->stage_id === (int) $archive->id) {
            return response()->json(['message' => 'Card is already archived.'], 422);
        }

        DB::transaction(function () use ($task, $archive) {
            $cards = collect([$task]);

            if ($task->category === 'content' && ! $task->source_task_id) {
                $cards = $cards->merge(MarketingTask::where('source_task_id', $task->id)->get());
            }

            foreach ($cards as $card) {
                $target = $card->id === $task->id
                    ? $archive
                    : (MarketingStage::archiveFor($card->category) ?: null);

                if (! $target || (int) $card->stage_id === (int) $target->id) {
                    continue;
                }

                $fromStageId = $card->stage_id;

                MarketingTask::where('stage_id', $target->id)->increment('position');

                $card->update([
                    'stage_id' => $target->id,
                    'position' => 0,
                    'completed_at' => $card->completed_at ?? now(),
                ]);

                MarketingHistoryRecorder::record(
                    $card,
                    'deleted',
                    MarketingHistoryRecorder::stageChange($fromStageId, $target->id)
                );
            }
        });

        return response()->json([
            'message' => 'Card moved to archive.',
            'task' => $this->fresh($task->fresh()),
        ]);
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
            $data['reference_image'] = \App\Support\Brand\BrandIsolation::storePublic($request->file('reference_image'), 'marketing', 'references');
        } elseif ($request->boolean('remove_reference_image')) {
            $data['reference_image'] = null;
        }

        return $data;
    }

    private function forgetReferenceImage(?string $path): void
    {
        if ($path && ! MarketingTask::where('reference_image', $path)->exists()) {
            \App\Support\Brand\BrandIsolation::deletePublicPath($path);
        }
    }

    private function fresh(MarketingTask $task): array
    {
        $fresh = $task->fresh()->load(MarketingPresenter::TASK_RELATIONS)->loadCount(MarketingPresenter::taskCounts());
        MarketingTaskMirror::hydrateShared([$fresh]);

        return MarketingPresenter::task($fresh);
    }
}
