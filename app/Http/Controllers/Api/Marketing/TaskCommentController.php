<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Models\MarketingTask;
use App\Services\Marketing\MarketingHistoryRecorder;
use App\Services\Marketing\MarketingPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Notifications\MarketingMention;
use Illuminate\Support\Facades\Notification;
class TaskCommentController extends MarketingBaseController
{
    public function store(Request $request, MarketingTask $task): JsonResponse
    {
        $validated = $request->validate([
            'body'       => ['required', 'string', 'max:5000'],
            'mentions'   => ['nullable', 'array', 'max:20'],
            'mentions.*' => ['integer'],
        ]);

        $body = trim($validated['body']);
        if ($body === '') {
            return response()->json(['message' => 'Comment cannot be empty.'], 422);
        }

        // Only real marketing members, not yourself, and only if "@Name" is still in the text.
        $mentioned = $this->memberQuery()
            ->whereIn('id', $validated['mentions'] ?? [])
            ->where('id', '!=', Auth::id())
            ->get(['id', 'name'])
            ->filter(fn ($u) => Str::contains($body, '@' . $u->name))
            ->values();

        $comment = $task->comments()->create([
            'user_id'  => Auth::id(),
            'body'     => $body,
            'mentions' => $mentioned->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->all(),
        ]);

        MarketingHistoryRecorder::record($task, 'commented', [[
            'field' => 'comment', 'label' => 'Comment', 'old' => null, 'new' => Str::limit($body, 120),
        ]]);

        if ($mentioned->isNotEmpty()) {
            Notification::send($mentioned, new MarketingMention($task, $comment, Auth::user()));
        }

        return response()->json(['comment' => MarketingPresenter::comment($comment->load('user:id,name,avatar'))], 201);
    }
}
