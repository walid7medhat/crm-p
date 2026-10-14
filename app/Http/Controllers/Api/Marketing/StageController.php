<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Models\MarketingStage;
use App\Models\MarketingTask;
use App\Services\Marketing\MarketingHistoryRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Admin only (route middleware): manage each board's stages. */
class StageController extends MarketingBaseController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['required', Rule::in(MarketingStage::CATEGORIES)],
            'name' => ['required', 'string', 'max:60'],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'icon' => ['nullable', 'string', 'max:80'],
            'is_done' => ['boolean'],
        ]);

        $stage = MarketingStage::create($validated + [
            'sort_order' => (int) MarketingStage::where('category', $validated['category'])->max('sort_order') + 1,
        ]);

        return response()->json(['stage' => $stage], 201);
    }

    public function update(Request $request, MarketingStage $stage): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:60'],
            'color' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'icon' => ['nullable', 'string', 'max:80'],
            'is_done' => ['sometimes', 'boolean'],
        ]);

        $stage->update($validated);

        return response()->json(['stage' => $stage]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['required', Rule::in(MarketingStage::CATEGORIES)],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['ids'] as $index => $id) {
                MarketingStage::where('category', $validated['category'])->whereKey($id)->update(['sort_order' => $index]);
            }
        });

        return response()->json(['message' => 'Stages reordered.']);
    }

    /** A stage that still has cards needs a target stage (same board) to move them into. */
    public function destroy(Request $request, MarketingStage $stage): JsonResponse
    {
        if (MarketingStage::where('category', $stage->category)->count() <= 1) {
            return response()->json(['message' => 'A board needs at least one stage.'], 422);
        }

        $count = MarketingTask::where('stage_id', $stage->id)->count();

        if ($count > 0) {
            $targetId = $request->input('move_to_stage_id');
            $target = $targetId
                ? MarketingStage::where('category', $stage->category)->where('id', '!=', $stage->id)->find($targetId)
                : null;

            if (!$target) {
                return response()->json([
                    'message' => "This stage has {$count} card(s). Choose a stage to move them to.",
                    'tasks_count' => $count,
                ], 422);
            }

            DB::transaction(function () use ($stage, $target) {
                MarketingTask::where('stage_id', $stage->id)->get()->each(fn (MarketingTask $task) => MarketingHistoryRecorder::record(
                    $task, 'moved', MarketingHistoryRecorder::stageChange($stage->id, $target->id)
                ));

                $offset = (int) MarketingTask::where('stage_id', $target->id)->max('position') + 1;
                MarketingTask::where('stage_id', $stage->id)
                    ->update([
                        'stage_id' => $target->id,
                        'position' => DB::raw('position + ' . $offset),
                        'completed_at' => $target->is_done ? DB::raw('COALESCE(completed_at, NOW())') : null,
                    ]);
                $stage->delete();
            });
        } else {
            $stage->delete();
        }

        return response()->json(['message' => 'Stage deleted.']);
    }
}
