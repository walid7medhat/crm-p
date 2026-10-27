<?php

namespace App\Notifications;

use App\Models\MarketingTask;
use App\Models\MarketingTaskComment;
use App\Models\User;
use Illuminate\Bus\Queueable;
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
        return ['database']; // add 'broadcast' / 'mail' if you use them
    }

   public function toArray($notifiable): array
    {
        return [
            // shown in the bell and on the notifications page
            'message' => "{$this->by->name} mentioned you in \"{$this->task->title}\": " . Str::limit($this->comment->body, 100),

            // routing: must match marketingFromNotification()
            'type'               => 'marketing',
            'marketing_task_id'  => $this->task->id,
            'marketing_category' => $this->task->category,
            'drawer_tab'         => 'comments',
            'comment_id'         => $this->comment->id,

            // notifications page: avatar, name pill, type chip
            'notification_type'         => 'marketing_mention',
            'responsible_person_name'   => $this->by->name,
            'responsible_person_avatar' => $this->by->avatar_url ?? $this->by->avatar,
        ];
    }
}