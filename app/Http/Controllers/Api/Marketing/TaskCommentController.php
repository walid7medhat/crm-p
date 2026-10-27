<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Models\MarketingTask;
use App\Models\User;
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

        // Match the @tag the UI inserts (display / short name), not the full DB name.
        $mentioned = $this->memberQuery()
            ->whereIn('id', $validated['mentions'] ?? [])
            ->where('id', '!=', Auth::id())
            ->get(['id', 'name', 'display_name'])
            ->filter(function ($u) use ($body) {
                $tag = User::resolveDisplayName($u);
                return $tag && Str::contains($body, '@' . $tag);
            })
            ->values();

        $comment = $task->comments()->create([
            'user_id'  => Auth::id(),
            'body'     => $body,
            'mentions' => $mentioned->map(fn ($u) => [
                'id' => $u->id,
                'name' => User::resolveDisplayName($u),
            ])->all(),
        ]);

        MarketingHistoryRecorder::record($task, 'commented', [[
            'field' => 'comment', 'label' => 'Comment', 'old' => null, 'new' => Str::limit($body, 120),
        ]]);

        if ($mentioned->isNotEmpty()) {
            Notification::send($mentioned, new MarketingMention($task, $comment, Auth::user()));
        }

        return response()->json(['comment' => MarketingPresenter::comment($comment->load('user:id,name,display_name,avatar'))], 201);
    }
}
