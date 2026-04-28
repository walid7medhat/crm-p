<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class PassportExpiryNotification extends Notification
{
    use Queueable;

    protected $user;
    protected $expiryDate;
    protected $daysLeft;

    public function __construct(User $user, $expiryDate, $daysLeft)
    {
        $this->user = $user;
        $this->expiryDate = $expiryDate;
        $this->daysLeft = $daysLeft;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        $urgency = $this->daysLeft <= 30 ? 'URGENT' : 'Reminder';
        
        return (new MailMessage)
            ->subject("{$urgency}: Passport Expiring for {$this->user->name}")
            ->greeting("Dear {$notifiable->name},")
            ->line("Employee **{$this->user->name}** has a passport expiring soon.")
            ->line("**Employee Details:**")
            ->line("- Name: {$this->user->name}")
            ->line("- Email: {$this->user->email}")
            ->line("- Phone: {$this->user->phone}")
            ->line("- Employee Code: {$this->user->employeeProfile?->employee_code}")
            ->line("")
            ->line("**Passport Details:**")
            ->line("- Passport Number: {$this->user->employeeProfile?->passport_number}")
            ->line("- Expiry Date: {$this->expiryDate->format('F j, Y')}")
            ->line("- Days Left: {$this->daysLeft} days")
            ->line("")
            ->when($this->daysLeft <= 30, function ($message) {
                return $message->line("⚠️ **ACTION REQUIRED:** Passport expires in less than 30 days. Please take immediate action.");
            })
            ->action('View Employee', url('/api/employees/' . $this->user->id))
            ->line('Please coordinate with the employee to renew their passport.');
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'passport_expiry',
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'passport_number' => $this->user->employeeProfile?->passport_number,
            'expiry_date' => $this->expiryDate->toDateString(),
            'days_left' => $this->daysLeft,
            'urgency' => $this->daysLeft <= 30 ? 'urgent' : 'warning',
            'message' => "{$this->user->name}'s passport expires in {$this->daysLeft} days",
            'action_url' => '/employees/' . $this->user->id,
        ];
    }

    public function toArray($notifiable)
    {
        return [
            'user_id' => $this->user->id,
            'expiry_date' => $this->expiryDate,
            'days_left' => $this->daysLeft,
        ];
    }
}