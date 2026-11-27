<?php

namespace App\Notifications;

use App\Models\DealActivity;
use App\Models\LeadActivity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

/**
 * Reminder for a lead activity or a deal activity (sent by activities:send-reminders).
 * Lead reminders carry lead_id, deal reminders carry deal_id; the bell opens the
 * matching record from that key.
 */
class ActivityReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $activity;
    public $timeframe;
    public $isLeadOwner;

    /**
     * @param  bool  $isLeadOwner  true when sent to the record's responsible person rather than the activity owner.
     */
    public function __construct(LeadActivity|DealActivity $activity, string $timeframe, bool $isLeadOwner = false)
    {
        $this->activity = $activity;
        $this->timeframe = $timeframe;
        $this->isLeadOwner = $isLeadOwner;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    private function isDeal(): bool
    {
        return $this->activity instanceof DealActivity;
    }

    private function recordLabel(): string
    {
        return $this->isDeal() ? 'deal' : 'lead';
    }

    private function recordName(): string
    {
        if ($this->isDeal()) {
            $deal = $this->activity->deal;
            return $deal?->deal_name ?: ('Deal #' . ($deal?->deal_number ?? $this->activity->deal_id));
        }

        $lead = $this->activity->lead;
        return $lead?->name ?: ('Lead #' . ($lead?->lead_number ?? $this->activity->lead_id));
    }

    private function path(): string
    {
        return $this->isDeal()
            ? '/kanban_deal?deal=' . $this->activity->deal_id
            : '/leads/' . $this->activity->lead_id . '?tab=activities';
    }

    /** lead_id / lead_number, or deal_id / deal_number. */
    private function recordKeys(): array
    {
        if ($this->isDeal()) {
            return [
                'deal_id' => $this->activity->deal_id,
                'deal_name' => $this->recordName(),
                'deal_number' => $this->activity->deal?->deal_number,
            ];
        }

        return [
            'lead_id' => $this->activity->lead_id,
            'lead_name' => $this->recordName(),
            'lead_number' => $this->activity->lead?->lead_number,
        ];
    }

    private function reminderTime(string $format): string
    {
        return $this->activity->reminder_date
            ->copy()
            ->setTimezone(config('app.timezone'))
            ->format($format);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->getSubject())
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line($this->getMessage())
            ->line(ucfirst($this->recordLabel()) . ': ' . $this->recordName())
            ->line('Activity: ' . $this->activity->title)
            ->line('Scheduled for: ' . $this->reminderTime('F j, Y \a\t g:i A'))
            ->line('Assigned to: ' . ($this->activity->user?->name ?? '—'))
            ->action('View Activity', url($this->path()))
            ->line('Thank you for using our CRM system!');
    }

    public function toDatabase(object $notifiable): array
    {
        return array_merge([
            'type' => 'activity_reminder',
            'activity_id' => $this->activity->id,
            'activity_title' => $this->activity->title,
            'reminder_date' => $this->activity->reminder_date->toISOString(),
            'timeframe' => $this->timeframe,
            'is_lead_owner' => $this->isLeadOwner,
            'assigned_user_id' => $this->activity->user_id,
            'assigned_user_name' => $this->activity->user?->name,
            'message' => $this->getMessage(),
            'url' => $this->path(),
            'timestamp' => now()->toISOString(),
        ], $this->recordKeys());
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $keys = $this->recordKeys();
        $record = $this->isDeal()
            ? ['deal' => ['id' => $keys['deal_id'], 'name' => $keys['deal_name'], 'deal_number' => $keys['deal_number']]]
            : ['lead' => ['id' => $keys['lead_id'], 'name' => $keys['lead_name'], 'lead_number' => $keys['lead_number']]];

        return new BroadcastMessage(array_merge([
            'type' => 'activity_reminder',
            'activity' => [
                'id' => $this->activity->id,
                'title' => $this->activity->title,
                'reminder_date' => $this->activity->reminder_date->toISOString(),
                'reminder_date_formatted' => $this->reminderTime('M j, Y g:i A'),
            ],
            'timeframe' => $this->timeframe,
            'is_lead_owner' => $this->isLeadOwner,
            'message' => $this->getMessage(),
            'timestamp' => now()->toISOString(),
            'url' => $this->path(),
        ], $record));
    }

    private function getSubject(): string
    {
        $prefix = $this->isLeadOwner
            ? (ucfirst($this->recordLabel()) . ' Activity Reminder: ')
            : 'Activity Reminder: ';

        return $prefix . ucfirst($this->timeframe) . ' - ' . $this->activity->title;
    }

    private function getMessage(): string
    {
        $label = $this->recordLabel();
        $prefix = $this->isLeadOwner
            ? "There's an activity scheduled for your {$label}"
            : 'You have a scheduled activity';

        $timeframeMessage = match ($this->timeframe) {
            'today' => 'happening today',
            'tomorrow' => 'scheduled for tomorrow',
            'overdue' => 'that is overdue',
            default => 'coming up',
        };

        $assignedTo = $this->activity->user?->name ?? '—';

        return "{$prefix} '{$this->activity->title}' {$timeframeMessage} for {$label} '{$this->recordName()}' at "
            . $this->reminderTime('F j, Y \a\t g:i A') . ". Assigned to: {$assignedTo}.";
    }
}
