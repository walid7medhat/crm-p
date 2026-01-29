<?php

namespace App\Notifications;

use App\Models\LeadActivity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class ActivityReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $activity;
    public $timeframe;
    public $isLeadOwner;

    /**
     * Create a new notification instance.
     */
    public function __construct(LeadActivity $activity, string $timeframe, bool $isLeadOwner = false)
    {
        $this->activity = $activity;
        $this->timeframe = $timeframe;
        $this->isLeadOwner = $isLeadOwner;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $lead = $this->activity->lead;
        $activityUser = $this->activity->user;
        $reminderTime = $this->activity->reminder_date->format('F j, Y \a\t g:i A');
        
        $subject = $this->getSubject();
        $message = $this->getMessage();
        
        $url = url('/leads/' . $lead->id . '?tab=activities');
        
        return (new MailMessage)
            ->subject($subject)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line($message)
            ->line('Lead: ' . $lead->name)
            ->line('Activity: ' . $this->activity->title)
            ->line('Scheduled for: ' . $reminderTime)
            ->line('Assigned to: ' . $activityUser->name)
            ->action('View Activity', $url)
            ->line('Thank you for using our CRM system!');
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        $lead = $this->activity->lead;
        
        return [
            'type' => 'activity_reminder',
            'activity_id' => $this->activity->id,
            'lead_id' => $lead->id,
            'lead_name' => $lead->name,
            'lead_number' => $lead->lead_number,
            'activity_title' => $this->activity->title,
            'reminder_date' => $this->activity->reminder_date->toISOString(),
            'timeframe' => $this->timeframe,
            'is_lead_owner' => $this->isLeadOwner,
            'assigned_user_id' => $this->activity->user_id,
            'assigned_user_name' => $this->activity->user->name,
            'message' => $this->getMessage(),
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Get the broadcast representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $lead = $this->activity->lead;
        
        return new BroadcastMessage([
            'type' => 'activity_reminder',
            'activity' => [
                'id' => $this->activity->id,
                'title' => $this->activity->title,
                'reminder_date' => $this->activity->reminder_date->toISOString(),
                'reminder_date_formatted' => $this->activity->reminder_date->format('M j, Y g:i A'),
            ],
            'lead' => [
                'id' => $lead->id,
                'name' => $lead->name,
                'lead_number' => $lead->lead_number,
            ],
            'timeframe' => $this->timeframe,
            'is_lead_owner' => $this->isLeadOwner,
            'message' => $this->getMessage(),
            'timestamp' => now()->toISOString(),
            'url' => '/leads/' . $lead->id . '?tab=activities'
        ]);
    }

    /**
     * Get notification subject.
     */
    private function getSubject(): string
    {
        $timeframeText = ucfirst($this->timeframe);
        $prefix = $this->isLeadOwner ? 'Lead Activity Reminder: ' : 'Activity Reminder: ';
        
        return $prefix . $timeframeText . ' - ' . $this->activity->title;
    }

    /**
     * Get notification message.
     */
    private function getMessage(): string
    {
        $lead = $this->activity->lead;
        $activityUser = $this->activity->user;
        $reminderTime = $this->activity->reminder_date->format('F j, Y \a\t g:i A');
        
        $leadName = $lead->name ?: "Lead #{$lead->lead_number}";
        $assignedTo = $activityUser->name;
        
        $prefix = $this->isLeadOwner ? 
            "There's an activity scheduled for your lead" : 
            "You have a scheduled activity";
            
        $timeframeMessage = '';
        
        switch ($this->timeframe) {
            case 'today':
                $timeframeMessage = 'happening today';
                break;
            case 'tomorrow':
                $timeframeMessage = 'scheduled for tomorrow';
                break;
            case 'overdue':
                $timeframeMessage = 'that is overdue';
                break;
            default:
                $timeframeMessage = 'coming up';
                break;
        }
        
        return "{$prefix} '{$this->activity->title}' {$timeframeMessage} for lead '{$leadName}' at {$reminderTime}. Assigned to: {$assignedTo}.";
    }
}