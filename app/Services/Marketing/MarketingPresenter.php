<?php

namespace App\Services\Marketing;

use App\Models\MarketingCalendarPost;
use App\Models\MarketingTask;
use App\Models\MarketingTaskComment;
use App\Models\MarketingTaskHistory;
use App\Models\MarketingTaskLink;
use App\Models\User;

/** Single JSON shape for marketing records, shared by every endpoint. */
class MarketingPresenter
{
    public const TASK_RELATIONS = [
        'assignee:id,name,avatar',
        'designer:id,name,avatar',
        'shooter:id,name,avatar',
        'editor:id,name,avatar',
        'approver:id,name',
        'creator:id,name',
        'links',
    ];

    /** withCount() definitions for board cards. */
    public static function taskCounts(): array
    {
        return [
            'comments',
            'posts',
            'posts as posted_count' => fn ($q) => $q->where('is_posted', true),
        ];
    }

    public static function user(?User $user): ?array
    {
        if (!$user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar_url' => asset('storage/' . $user->avatar),
        ];
    }

    public static function task(MarketingTask $task): array
    {
        return [
            'id' => $task->id,
            'category' => $task->category,
            'type' => $task->type,
            'title' => $task->title,
            'department' => $task->department,
            'stage_id' => $task->stage_id,
            'position' => $task->position,
            'assignee' => self::user($task->assignee),
            'designer' => self::user($task->designer),
            'also_video' => (bool) $task->also_video,
            'source_task_id' => $task->source_task_id,
            'shooter' => self::user($task->shooter),
            'editor' => self::user($task->editor),
            'deliverables' => $task->deliverables ?? [],
            'channels' => (object) ($task->channels ?? []),
            'tov' => $task->tov,
            'reference' => $task->reference,
            'reference_image_url' => $task->reference_image ? asset('storage/'.$task->reference_image) : null,
            'notes' => $task->notes,
            'is_approved' => $task->is_approved,
            'approved_by' => $task->approver?->name,
            'approved_at' => $task->approved_at?->toIso8601String(),
            'due_date' => $task->due_date?->format('Y-m-d'),
            'completed_at' => $task->completed_at?->toIso8601String(),
            'created_by' => $task->creator?->name,
            'created_at' => $task->created_at?->toIso8601String(),
            'updated_at' => $task->updated_at?->toIso8601String(),
            'links' => $task->relationLoaded('links') ? $task->links->map(fn ($l) => self::link($l))->values() : [],
            'comments_count' => (int) ($task->comments_count ?? 0),
            'posts_count' => (int) ($task->posts_count ?? 0),
            'posted_count' => (int) ($task->posted_count ?? 0),
        ];
    }

    public static function link(MarketingTaskLink $link): array
    {
        return [
            'id' => $link->id,
            'url' => $link->url,
            'label' => $link->label,
            'kind' => $link->kind,
            'created_at' => $link->created_at?->toIso8601String(),
        ];
    }

    public static function comment(MarketingTaskComment $comment): array
    {
        return [
            'id' => $comment->id,
            'body' => $comment->body,
            'user' => self::user($comment->user),
            'created_at' => $comment->created_at?->toIso8601String(),
        ];
    }

    public static function history(MarketingTaskHistory $history): array
    {
        return [
            'id' => $history->id,
            'action' => $history->action,
            'changes' => $history->changes ?? [],
            'user' => self::user($history->user),
            'created_at' => $history->created_at?->toIso8601String(),
        ];
    }

    public static function post(MarketingCalendarPost $post): array
    {
        return [
            'id' => $post->id,
            'task_id' => $post->task_id,
            'task_category' => $post->task?->category,
            'link' => $post->link ? self::link($post->link) : null,
            'title' => $post->title,
            'platform' => $post->platform,
            'scheduled_date' => $post->scheduled_date?->format('Y-m-d'),
            'is_posted' => $post->is_posted,
            'posted_by' => $post->poster?->name,
            'posted_at' => $post->posted_at?->toIso8601String(),
        ];
    }
}
