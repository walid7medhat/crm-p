<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Models\MarketingTask;
use App\Services\Marketing\MarketingHistoryRecorder;
use App\Services\Marketing\MarketingPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TaskCommentController extends MarketingBaseController
{
    public function store(Request $request, MarketingTask $task): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $body = trim($validated['body']);
        if ($body === '') {
            return response()->json(['message' => 'Comment cannot be empty.'], 422);
        }

        $comment = $task->comments()->create([
            'user_id' => Auth::id(),
            'body' => $body,
        ]);

        MarketingHistoryRecorder::record($task, 'commented', [[
            'field' => 'comment', 'label' => 'Comment', 'old' => null, 'new' => Str::limit($body, 120),
        ]]);

        return response()->json(['comment' => MarketingPresenter::comment($comment->load('user:id,name,avatar'))], 201);
    }
}
