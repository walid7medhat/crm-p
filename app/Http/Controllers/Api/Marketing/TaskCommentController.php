<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Models\MarketingTask;
use App\Models\MarketingTaskComment;
use App\Models\User;
use App\Services\Marketing\MarketingHistoryRecorder;
use App\Services\Marketing\MarketingPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Notifications\MarketingMention;
use Illuminate\Support\Facades\Notification;

class TaskCommentController extends MarketingBaseController
{
    public function store(Request $request, MarketingTask $task): JsonResponse
    {
        $validated = $this->validateComment($request);

        $body = trim($validated['body']);
        if ($body === '') {
            return response()->json(['message' => 'Comment cannot be empty.'], 422);
        }

        $mentioned = $this->resolveMentions($body, $validated['mentions'] ?? []);

        $comment = $task->comments()->create([
            'user_id'  => Auth::id(),
            'body'     => $body,
            'mentions' => $this->mentionPayload($mentioned),
        ]);

        MarketingHistoryRecorder::record($task, 'commented', [[
            'field' => 'comment', 'label' => 'Comment', 'old' => null, 'new' => Str::limit($body, 120),
        ]]);

        if ($mentioned->isNotEmpty()) {
            Notification::send($mentioned, new MarketingMention($task, $comment, Auth::user()));
        }

        return response()->json(['comment' => MarketingPresenter::comment($comment->load('user:id,name,display_name,avatar'))], 201);
    }

    public function update(Request $request, MarketingTask $task, MarketingTaskComment $comment): JsonResponse
    {
        if ((int) $comment->task_id !== (int) $task->id) {
            abort(404);
        }

        if ((int) $comment->user_id !== (int) Auth::id() && ! $this->isAdmin()) {
            return response()->json(['message' => 'You can only edit your own comments.'], 403);
        }

        $validated = $this->validateComment($request);
        $body = trim($validated['body']);
        if ($body === '') {
            return response()->json(['message' => 'Comment cannot be empty.'], 422);
        }

        $oldBody = $comment->body;
        if ($oldBody === $body) {
            return response()->json(['comment' => MarketingPresenter::comment($comment->load('user:id,name,display_name,avatar'))]);
        }

        $oldMentionIds = collect($comment->mentions ?? [])->pluck('id')->map(fn ($id) => (int) $id);
        $mentioned = $this->resolveMentions($body, $validated['mentions'] ?? []);

        $comment->update([
            'body'     => $body,
            'mentions' => $this->mentionPayload($mentioned),
        ]);

        MarketingHistoryRecorder::record($task, 'comment_updated', [[
            'field' => 'comment',
            'label' => 'Comment',
            'old'   => Str::limit($oldBody, 120),
            'new'   => Str::limit($body, 120),
        ]]);

        $newlyMentioned = $mentioned->reject(fn ($u) => $oldMentionIds->contains((int) $u->id))->values();
        if ($newlyMentioned->isNotEmpty()) {
            Notification::send($newlyMentioned, new MarketingMention($task, $comment, Auth::user()));
        }

        return response()->json(['comment' => MarketingPresenter::comment($comment->fresh()->load('user:id,name,display_name,avatar'))]);
    }

    private function validateComment(Request $request): array
    {
        return $request->validate([
            'body'       => ['required', 'string', 'max:5000'],
            'mentions'   => ['nullable', 'array', 'max:20'],
            'mentions.*' => ['integer'],
        ]);
    }

    /** Match the @tag the UI inserts (display / short name), not the full DB name. */
    private function resolveMentions(string $body, array $mentionIds): Collection
    {
        return $this->memberQuery()
            ->whereIn('id', $mentionIds)
            ->where('id', '!=', Auth::id())
            ->get(['id', 'name', 'display_name'])
            ->filter(function ($u) use ($body) {
                $tag = User::resolveDisplayName($u);

                return $tag && Str::contains($body, '@'.$tag);
            })
            ->values();
    }

    private function mentionPayload(Collection $mentioned): array
    {
        return $mentioned->map(fn ($u) => [
            'id'   => $u->id,
            'name' => User::resolveDisplayName($u),
        ])->all();
    }
}
