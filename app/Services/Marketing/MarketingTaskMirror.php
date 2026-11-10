<?php

namespace App\Services\Marketing;

use App\Models\MarketingStage;
use App\Models\MarketingTask;
use App\Models\MarketingTaskComment;
use App\Models\MarketingTaskLink;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * A content card stays on Content. Choosing a designer also places it on Design,
 * and choosing Video also places it on Production. Shared brief fields stay in step.
 *
 * Comments and links are shared across the whole family (source + mirrors) so Content
 * and Design see the same thread even though each board has its own card row.
 */
class MarketingTaskMirror
{
    public static function sync(MarketingTask $task): void
    {
        $source = self::sourceOf($task);
        if (!$source) {
            return;
        }

        $fromSource = $task->is($source);

        if (!$fromSource) {
            $source->fill(self::shared($task));
            if ($task->category === 'design') {
                $source->designed_by = $task->assigned_to;
            }
            $source->save();
        }

        // Only the content card can pull its copies off the other boards.
        // Editing the Design or Video card itself keeps that card in place.
        self::upsert($source, 'design', (bool) $source->designed_by, [
            'assigned_to' => $source->designed_by,
            'type' => null,
        ], $fromSource);
        self::upsert($source, 'production', (bool) $source->also_video, [
            'type' => 'video',
        ], $fromSource);
    }

    /**
     * From a content card: set the designer and place the Design copy at the top
     * of the first Design stage, even if that copy was already further along.
     */
    public static function reassignDesigner(MarketingTask $task, int $designerId): void
    {
        $source = self::sourceOf($task);
        if (!$source || $source->category !== 'content') {
            throw ValidationException::withMessages([
                'designed_by' => 'Only a content card can be reassigned to Design.',
            ]);
        }

        $before = MarketingHistoryRecorder::snapshot($source);
        $source->designed_by = $designerId;
        if ($source->isDirty()) {
            $source->save();
            $changes = MarketingHistoryRecorder::diff($before, $source->fresh());
            if ($changes) {
                MarketingHistoryRecorder::record($source, 'updated', $changes);
            }
        }

        self::sync($source->fresh());

        $mirror = MarketingTask::where('source_task_id', $source->id)->where('category', 'design')->first();
        if ($mirror) {
            self::placeOnFirstStage($mirror->fresh());
        }
    }

    /** Content/source card that owns shared brief data, comments, and links. */
    public static function root(MarketingTask $task): MarketingTask
    {
        return self::sourceOf($task) ?? $task;
    }

    public static function rootId(MarketingTask $task): int
    {
        return (int) self::root($task)->id;
    }

    /** All task ids in the mirrored family (source + design/production copies). */
    public static function familyIds(MarketingTask $task): array
    {
        $rootId = self::rootId($task);

        return MarketingTask::query()
            ->where(fn ($q) => $q->where('id', $rootId)->orWhere('source_task_id', $rootId))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public static function belongsToFamily(MarketingTask $task, int $relatedTaskId): bool
    {
        return in_array($relatedTaskId, self::familyIds($task), true);
    }

    /**
     * Attach family-wide comment counts and links onto board/list cards so mirrors
     * show the same shared activity as the content source.
     *
     * @param  Collection<int, MarketingTask>|iterable<MarketingTask>  $tasks
     */
    public static function hydrateShared(iterable $tasks): void
    {
        $tasks = collect($tasks)->filter()->values();
        if ($tasks->isEmpty()) {
            return;
        }

        $rootIds = $tasks
            ->map(fn (MarketingTask $t) => (int) ($t->source_task_id ?: $t->id))
            ->unique()
            ->values()
            ->all();

        $members = MarketingTask::query()
            ->where(fn ($q) => $q->whereIn('id', $rootIds)->orWhereIn('source_task_id', $rootIds))
            ->get(['id', 'source_task_id']);

        $rootToMembers = [];
        foreach ($rootIds as $rootId) {
            $rootToMembers[$rootId] = [(int) $rootId];
        }
        foreach ($members as $member) {
            $rootId = (int) ($member->source_task_id ?: $member->id);
            $rootToMembers[$rootId][] = (int) $member->id;
        }
        foreach ($rootToMembers as $rootId => $ids) {
            $rootToMembers[$rootId] = array_values(array_unique($ids));
        }

        $allIds = collect($rootToMembers)->flatten()->unique()->values()->all();

        $commentCounts = MarketingTaskComment::query()
            ->whereIn('task_id', $allIds)
            ->selectRaw('task_id, count(*) as aggregate')
            ->groupBy('task_id')
            ->pluck('aggregate', 'task_id');

        $linksByTask = MarketingTaskLink::query()
            ->whereIn('task_id', $allIds)
            ->latest('id')
            ->get()
            ->groupBy('task_id');

        foreach ($tasks as $task) {
            $rootId = (int) ($task->source_task_id ?: $task->id);
            $memberIds = $rootToMembers[$rootId] ?? [(int) $task->id];

            $count = 0;
            $links = collect();
            foreach ($memberIds as $memberId) {
                $count += (int) ($commentCounts[$memberId] ?? 0);
                if ($linksByTask->has($memberId)) {
                    $links = $links->concat($linksByTask->get($memberId));
                }
            }

            $task->setAttribute('comments_count', $count);
            $task->setRelation(
                'links',
                $links->unique('id')->sortByDesc('id')->values()
            );
        }
    }

    public static function sourceOf(MarketingTask $task): ?MarketingTask
    {
        if ($task->category === 'content' && !$task->source_task_id) {
            return $task;
        }
        if ($task->source_task_id) {
            return MarketingTask::find($task->source_task_id);
        }

        return null;
    }

    /** @param array<string, mixed> $extra */
    private static function upsert(MarketingTask $source, string $category, bool $wanted, array $extra, bool $drop): void
    {
        $mirror = MarketingTask::where('source_task_id', $source->id)->where('category', $category)->first();

        if (!$wanted) {
            if ($drop && $mirror) {
                MarketingHistoryRecorder::record($mirror, 'deleted');
                $mirror->delete();
            }

            return;
        }

        $shared = self::shared($source);

        if ($mirror) {
            $before = MarketingHistoryRecorder::snapshot($mirror);
            $fill = $shared;
            if ($category === 'design') {
                $fill['assigned_to'] = $extra['assigned_to'];
            }
            $mirror->fill($fill);
            if (!$mirror->isDirty()) {
                return;
            }
            $mirror->save();
            $changes = MarketingHistoryRecorder::diff($before, $mirror->fresh());
            if ($changes) {
                MarketingHistoryRecorder::record($mirror, 'updated', $changes);
            }

            return;
        }

        $stage = MarketingStage::where('category', $category)->orderBy('sort_order')->orderBy('id')->first();
        if (!$stage) {
            $field = $category === 'design' ? 'designed_by' : 'also_video';
            $board = $category === 'design' ? 'Design' : 'Production';
            throw ValidationException::withMessages([
                $field => "Add a stage on the {$board} board before sending this card there.",
            ]);
        }

        MarketingTask::where('stage_id', $stage->id)->increment('position');

        $mirror = MarketingTask::create($shared + $extra + [
            'category' => $category,
            'stage_id' => $stage->id,
            'position' => 0,
            'source_task_id' => $source->id,
            'created_by' => Auth::id() ?: $source->created_by,
            'also_video' => false,
            'completed_at' => $stage->is_done ? now() : null,
        ]);

        MarketingHistoryRecorder::record($mirror, 'created', [[
            'field' => 'stage_id',
            'label' => 'Stage',
            'old' => null,
            'new' => $stage->name,
        ]]);
    }

    /** Drop a card at the top of the first stage on its board. */
    private static function placeOnFirstStage(MarketingTask $mirror): void
    {
        $stage = MarketingStage::where('category', $mirror->category)->orderBy('sort_order')->orderBy('id')->first();
        if (!$stage) {
            throw ValidationException::withMessages([
                'designed_by' => 'Add a stage on the Design board before sending this card there.',
            ]);
        }

        if ((int) $mirror->stage_id === (int) $stage->id && (int) $mirror->position === 0) {
            return;
        }

        $fromStageId = (int) $mirror->stage_id;

        MarketingTask::where('stage_id', $stage->id)->where('id', '!=', $mirror->id)->increment('position');

        $mirror->stage_id = $stage->id;
        $mirror->position = 0;
        $mirror->completed_at = $stage->is_done ? ($mirror->completed_at ?? now()) : null;
        $mirror->save();

        if ($fromStageId !== (int) $stage->id) {
            MarketingTask::where('stage_id', $fromStageId)
                ->orderBy('position')->orderByDesc('id')
                ->pluck('id')
                ->each(fn ($id, $index) => MarketingTask::whereKey($id)->update(['position' => $index]));

            MarketingHistoryRecorder::record($mirror, 'moved', MarketingHistoryRecorder::stageChange($fromStageId, $stage->id));
        }
    }

    /** @return array<string, mixed> */
    private static function shared(MarketingTask $from): array
    {
        return [
            'title' => $from->title,
            'department' => $from->department,
            'reference' => $from->reference,
            'reference_image' => $from->reference_image,
            'notes' => $from->notes,
            'due_date' => $from->due_date?->format('Y-m-d'),
        ];
    }
}
