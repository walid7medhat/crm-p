<?php

namespace App\Notifications;

use App\Models\MarketingTask;
use App\Models\MarketingTaskComment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class MarketingMention extends Notification
{
    use Queueable;

    public function __construct(
        private MarketingTask $task,
        private MarketingTaskComment $comment,
        private User $by,
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable): array
    {
        return $this->payload();
    }

    public function toArray($notifiable): array
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
            // bell + notifications page
            'message' => "{$this->by->name} mentioned you in \"{$this->task->title}\": " . Str::limit($this->comment->body, 100),

            // same keys as MarketingTaskNotification, so the live pop-up can handle it
            'type'               => 'marketing',
            'action_type'        => 'mentioned',
            'marketing_task_id'  => $this->task->id,
            'marketing_category' => $this->task->category,
            'title'              => $this->task->title,
            'user_id'            => $this->by->id,
            'user_name'          => $this->by->name,
            'changes'            => [],
            'timestamp'          => now()->toISOString(),

            // routing: must match marketingFromNotification()
            'drawer_tab' => 'comments',
            'comment_id' => $this->comment->id,

            // notifications page: avatar, name pill, type chip
            'notification_type'         => 'marketing_mention',
            'responsible_person_name'   => $this->by->name,
            'responsible_person_avatar' => $this->by->avatar_url ?? $this->by->avatar,
        ];
    }
}