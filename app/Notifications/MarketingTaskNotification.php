<?php

namespace App\Notifications;

use App\Models\MarketingTask;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Marketing card activity for the people on the card (bell + live pop-up), same delivery as
 * DealUpdatedNotification: stored in the database and broadcast on the user's private channel.
 *
 * action: assigned | stage_changed | link_added | commented | approved | deleted
 */
class MarketingTaskNotification extends Notification
{
    use Queueable;

    public function __construct(
        public MarketingTask $task,
        public string $action,
        public ?User $actor = null,
        public array $changes = [],
        public ?string $role = null,
    ) {
    }

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable): array
    {
        return $this->payload();
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload());
    }

    private function payload(): array
    {
        return [
            'type' => 'marketing',
            'action_type' => $this->action,
            'marketing_task_id' => $this->task->id,
            'marketing_category' => $this->task->category,
            'title' => $this->task->title,
            'user_id' => $this->actor?->id,
            'user_name' => $this->actor?->name,
            'changes' => $this->changes,
            'message' => $this->message(),
            'timestamp' => now()->toISOString(),
        ];
    }

    private function message(): string
    {
        $who = $this->actor ? collect(explode(' ', $this->actor->name))->take(2)->implode(' ') : 'Someone';
        $title = '“'.$this->task->title.'”';
        $first = $this->changes[0] ?? [];

        return match ($this->action) {
            'assigned' => "{$who} assigned you".($this->role ? " as {$this->role}" : '')." on {$title}",
            'stage_changed' => "{$who} moved {$title} from ".($first['old'] ?? 'another stage').' to '.($first['new'] ?? 'another stage'),
            'link_added' => "{$who} added a link".(! empty($first['new']) ? " ({$first['new']})" : '')." to {$title}",
            'commented' => "{$who} commented on {$title}".(! empty($first['new']) ? ": {$first['new']}" : ''),
            'approved' => "{$who} approved the design {$title}",
            'deleted' => "{$who} deleted the card {$title}",
            default => "{$who} updated {$title}",
        };
    }
}
