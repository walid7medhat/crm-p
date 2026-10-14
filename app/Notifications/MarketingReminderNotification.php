<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Marketing reminders (bell + live pop-up), sent by `marketing:send-reminders`:
 *   due_tomorrow · due_today · overdue — a card that isn't done yet
 *   post_today                          — a calendar post scheduled today, not posted yet
 */
class MarketingReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $kind,
        public string $title,
        public ?int $taskId,
        public ?string $category,
        public array $extra = [],
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
            'type' => 'marketing_reminder',
            'reminder' => $this->kind,
            'marketing_task_id' => $this->taskId,
            'marketing_category' => $this->category,
            'title' => $this->title,
            'extra' => $this->extra,
            'message' => $this->message(),
            'timestamp' => now()->toISOString(),
        ];
    }

    private function message(): string
    {
        $title = '“'.$this->title.'”';

        return match ($this->kind) {
            'due_tomorrow' => "Reminder: {$title} is due tomorrow",
            'due_today' => "Reminder: {$title} is due today",
            'overdue' => "{$title} is overdue by ".($this->extra['days'] ?? 1).' day'.(($this->extra['days'] ?? 1) === 1 ? '' : 's'),
            'post_today' => "Today's post: {$title} on ".($this->extra['platform'] ?? 'social media').' is not marked posted yet',
            default => "Marketing reminder: {$title}",
        };
    }
}
