<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $leaveRequest;
    protected $type; // 'parent' | 'hr' | 'employee_approved' | 'employee_rejected'

    public function __construct(LeaveRequest $leaveRequest, $type = 'parent')
    {
        $this->leaveRequest = $leaveRequest;
        $this->type = $type;
    }

    public function via($notifiable)
    {
        // 'broadcast' pushes this over Pusher on the notifiable's
        // private channel (App.Models.User.{id}) automatically.
        return ['database', 'broadcast'];
    }

    public function toMail($notifiable)
    {
        $user = $this->leaveRequest->user;
        $leaveType = $this->leaveRequest->leaveType;

        if ($this->type === 'parent') {
            return (new MailMessage)
                ->subject('📋 New Leave Request - ' . $leaveType->name)
                ->greeting('Hello ' . $notifiable->name . ',')
                ->line('**' . $user->name . '** has submitted a leave request.')
                ->line('**Leave Type:** ' . $leaveType->name)
                ->line('**Duration:** ' . $this->leaveRequest->start_date->format('M d, Y') . ' → ' . $this->leaveRequest->end_date->format('M d, Y'))
                ->line('**Days:** ' . $this->leaveRequest->days)
                ->action('Review Request', url('/api/leaves/' . $this->leaveRequest->id));
        }

        if ($this->type === 'hr') {
            return (new MailMessage)
                ->subject('✅ Leave Request Pending - HR Approval')
                ->greeting('Hello ' . $notifiable->name . ',')
                ->line('**' . $user->name . '** leave request has been approved by their manager.')
                ->line('**Leave Type:** ' . $leaveType->name)
                ->action('Review Request', url('/api/leaves/' . $this->leaveRequest->id));
        }

        if ($this->type === 'employee_approved') {
            return (new MailMessage)
                ->subject('✅ Your Leave Request Was Approved')
                ->greeting('Hello ' . $notifiable->name . ',')
                ->line('Your **' . $leaveType->name . '** request (' .
                    $this->leaveRequest->start_date->format('M d, Y') . ' → ' .
                    $this->leaveRequest->end_date->format('M d, Y') . ') has been approved.');
        }

        // employee_rejected
        return (new MailMessage)
            ->subject('❌ Your Leave Request Was Rejected')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Your **' . $leaveType->name . '** request was rejected.')
            ->line('**Reason:** ' . ($this->leaveRequest->rejection_reason ?? '—'));
    }

    public function toDatabase($notifiable)
    {
        $user = $this->leaveRequest->user;
        $leaveType = $this->leaveRequest->leaveType;

        return match ($this->type) {
            'parent' => [
                'type' => 'leave_request',
                'leave_request_id' => $this->leaveRequest->id,
                'title' => 'New Leave Request',
                'message' => $user->name . ' requested ' . $leaveType->name . ' for ' . $this->leaveRequest->days . ' day(s)',
                'status' => 'pending_parent',
                'action_url' => '/leaves/' . $this->leaveRequest->id,
            ],
            'hr' => [
                'type' => 'leave_request_hr',
                'leave_request_id' => $this->leaveRequest->id,
                'title' => 'Leave Request - Pending HR Approval',
                'message' => $user->name . ' leave request approved by manager, needs HR review',
                'status' => 'pending_hr',
                'action_url' => '/leaves/' . $this->leaveRequest->id,
            ],
            'employee_approved' => [
                'type' => 'leave_request_status',
                'leave_request_id' => $this->leaveRequest->id,
                'title' => 'Leave Approved',
                'message' => 'Your ' . $leaveType->name . ' request has been approved',
                'status' => 'approved',
                'action_url' => '/profile',
            ],
            'employee_rejected' => [
                'type' => 'leave_request_status',
                'leave_request_id' => $this->leaveRequest->id,
                'title' => 'Leave Rejected',
                'message' => 'Your ' . $leaveType->name . ' request has been rejected',
                'status' => 'rejected',
                'action_url' => '/profile',
            ],
            default => [],
        };
    }

    public function toArray($notifiable)
    {
        return $this->toDatabase($notifiable);
    }
      public function broadcastType(): string
        {
            return $this->toArray(null)['type'] ?? 'notification';
        }
}