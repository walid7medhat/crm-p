<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Models\MarketingTask;
use App\Models\MarketingTaskLink;
use App\Services\Marketing\MarketingHistoryRecorder;
use App\Services\Marketing\MarketingLinkClassifier;
use App\Services\Marketing\MarketingPresenter;
use App\Services\Marketing\MarketingTaskMirror;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Links can be added at any stage (WIP folders, drafts, finals). Shared across mirrored cards. */
class TaskLinkController extends MarketingBaseController
{
    public function store(Request $request, MarketingTask $task): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        $label = trim($validated['label'] ?? '') ?: MarketingLinkClassifier::defaultLabel($validated['url']);
        $root = MarketingTaskMirror::root($task);

        $link = $root->links()->create([
            'url' => $validated['url'],
            'label' => $label,
            'kind' => MarketingLinkClassifier::kind($validated['url'], $label),
            'added_by' => Auth::id(),
        ]);

        MarketingHistoryRecorder::record($task, 'link_added', [[
            'field' => 'link', 'label' => 'Link', 'old' => null, 'new' => $label,
        ]]);

        return response()->json(['link' => MarketingPresenter::link($link)], 201);
    }

    public function destroy(MarketingTask $task, MarketingTaskLink $link): JsonResponse
    {
        abort_unless(MarketingTaskMirror::belongsToFamily($task, (int) $link->task_id), 404);

        MarketingHistoryRecorder::record($task, 'link_removed', [[
            'field' => 'link', 'label' => 'Link', 'old' => $link->label, 'new' => null,
        ]]);
        $link->delete();

        return response()->json(['message' => 'Link removed.']);
    }
}
