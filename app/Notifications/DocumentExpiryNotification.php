<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentExpiryNotification extends Notification
{
    use Queueable;

    protected $user;
    protected $documentLabel;
    protected $documentNumber;
    protected $expiryDate;
    protected $daysLeft;

    public function __construct(User $user, string $documentLabel, ?string $documentNumber, $expiryDate, $daysLeft)
    {
        $this->user = $user;
        $this->documentLabel = $documentLabel;
        $this->documentNumber = $documentNumber;
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
            ->subject("{$urgency}: {$this->documentLabel} Expiring for {$this->user->name}")
            ->greeting("Dear {$notifiable->name},")
            ->line("Employee **{$this->user->name}** has a {$this->documentLabel} expiring soon.")
            ->line("**Employee Details:**")
            ->line("- Name: {$this->user->name}")
            ->line("- Email: {$this->user->email}")
            ->line("- Employee Code: {$this->user->employeeProfile?->employee_code}")
            ->line("")
            ->line("**{$this->documentLabel} Details:**")
            ->line("- Number: " . ($this->documentNumber ?: 'N/A'))
            ->line("- Expiry Date: {$this->expiryDate->format('F j, Y')}")
            ->line("- Days Left: {$this->daysLeft} days")
            ->line("")
            ->when($this->daysLeft <= 30, function ($message) {
                return $message->line("⚠️ **ACTION REQUIRED:** {$this->documentLabel} expires in less than 30 days. Please take immediate action.");
            })
            ->action('View Employee', url('/api/employees/' . $this->user->id))
            ->line('Please coordinate with the employee to renew this document.');
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'document_expiry',
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'document_label' => $this->documentLabel,
            'document_number' => $this->documentNumber,
            'expiry_date' => $this->expiryDate->toDateString(),
            'days_left' => $this->daysLeft,
            'urgency' => $this->daysLeft <= 30 ? 'urgent' : 'warning',
            'message' => "{$this->user->name}'s {$this->documentLabel} expires in {$this->daysLeft} days",
            'action_url' => '/hr/employees/' . $this->user->id,
        ];
    }

    public function toArray($notifiable)
    {
        return [
            'user_id' => $this->user->id,
            'document_label' => $this->documentLabel,
            'expiry_date' => $this->expiryDate,
            'days_left' => $this->daysLeft,
        ];
    }
}
