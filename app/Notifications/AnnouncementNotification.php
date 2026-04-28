<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class AnnouncementNotification extends Notification
{
    use Queueable;

    protected $announcement;

    public function __construct(Announcement $announcement)
    {
        $this->announcement = $announcement;
    }

    public function via($notifiable)
    {
        // 'mail',
        return [ 'database'];
    }

    public function toMail($notifiable)
    {
        $title = $this->announcement->title;
        $startDate = $this->announcement->start_date->format('F j, Y');
        $endDate = $this->announcement->end_date ? $this->announcement->end_date->format('F j, Y') : null;
        
        $duration = $endDate ? "{$startDate} → {$endDate}" : "Starting {$startDate}";
        
        return (new MailMessage)
            ->subject("📢 New Announcement: {$title}")
            ->greeting("Dear {$notifiable->name},")
            ->line("**{$title}**")
            ->line($duration)
            ->line($this->announcement->description ?? 'No additional details provided.')
            ->action('View Announcement', url('/api/announcements/' . $this->announcement->id))
            ->line('Thank you for your attention.');
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'announcement',
            'announcement_id' => $this->announcement->id,
            'title' => 'New Announcement',
            'message' => $this->announcement->title,
            'start_date' => $this->announcement->start_date->toDateString(),
            'end_date' => $this->announcement->end_date?->toDateString(),
            'action_url' => '/announcements/' . $this->announcement->id,
        ];
    }

    public function toArray($notifiable)
    {
        return [
            'announcement_id' => $this->announcement->id,
            'title' => $this->announcement->title,
            'start_date' => $this->announcement->start_date,
        ];
    }
}