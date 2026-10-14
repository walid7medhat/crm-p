<?php

namespace App\Services\Marketing;

use App\Events\MarketingBoardUpdated;
use App\Models\MarketingTask;
use App\Models\User;
use App\Notifications\MarketingTaskNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Turns a recorded marketing history row into:
 *  - a live board refresh for every marketing member (MarketingBoardUpdated, Pusher), and
 *  - bell + pop-up notifications (MarketingTaskNotification) for the people on the card.
 * Never notifies the person who did it. Failures are logged, never thrown — saving a card
 * must not fail because Pusher or the queue is down.
 */
class MarketingNotifier
{
    /** History action → notification action (others only refresh boards). */
    private const NOTIFY = [
        'moved' => 'stage_changed',
        'link_added' => 'link_added',
        'commented' => 'commented',
        'approved' => 'approved',
        'deleted' => 'deleted',
    ];

    private const ROLE_FIELDS = [
        'assigned_to' => 'designer',
        'shooting_by' => 'shooter',
        'editing_by' => 'editor',
    ];

    public static function dispatch(MarketingTask $task, string $action, array $changes = []): void
    {
        try {
            $actor = Auth::user();

            event(MarketingBoardUpdated::fromTask($task, $action, $actor));

            // Newly assigned people get a personal "you were assigned" (on create or edit).
            if (in_array($action, ['created', 'updated'], true)) {
                self::notifyAssigned($task, $action, $changes, $actor);
            }

            if (isset(self::NOTIFY[$action])) {
                foreach (self::cardPeople($task, $actor) as $user) {
                    $user->notify(new MarketingTaskNotification($task, self::NOTIFY[$action], $actor, $changes));
                }
            }
        } catch (\Throwable $e) {
            Log::warning('MarketingNotifier: '.$e->getMessage(), ['task' => $task->id, 'action' => $action]);
        }
    }

    private static function notifyAssigned(MarketingTask $task, string $action, array $changes, ?User $actor): void
    {
        $changedFields = collect($changes)->pluck('field')->all();

        foreach (self::ROLE_FIELDS as $field => $role) {
            $userId = $task->{$field};
            if (! $userId || (int) $userId === (int) $actor?->id) {
                continue;
            }
            // On edit, only fields that actually changed to someone.
            if ($action === 'updated' && ! in_array($field, $changedFields, true)) {
                continue;
            }
            $role = $field === 'assigned_to' && $task->category !== 'design' ? 'assignee' : $role;
            User::find($userId)?->notify(new MarketingTaskNotification($task, 'assigned', $actor, [], $role));
        }
    }

    /** Assignee, shooter, editor and creator of the card — minus whoever acted. */
    private static function cardPeople(MarketingTask $task, ?User $actor)
    {
        $ids = collect([$task->assigned_to, $task->shooting_by, $task->editing_by, $task->created_by])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn ($id) => $id === (int) $actor?->id);

        return $ids->isEmpty() ? collect() : User::whereIn('id', $ids)->where('status', 'active')->get();
    }
}
