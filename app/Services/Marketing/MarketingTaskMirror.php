<?php

namespace App\Services\Marketing;

use App\Models\MarketingStage;
use App\Models\MarketingTask;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * A content card stays on Content. Choosing a designer also places it on Design,
 * and choosing Video also places it on Production. Shared brief fields stay in step.
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

    private static function sourceOf(MarketingTask $task): ?MarketingTask
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
