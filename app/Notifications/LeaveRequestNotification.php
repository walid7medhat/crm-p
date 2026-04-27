<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class LeaveRequestNotification extends Notification
{
    use Queueable;

    protected $leaveRequest;
    protected $type; // 'parent' or 'hr'

    public function __construct(LeaveRequest $leaveRequest, $type = 'parent')
    {
        $this->leaveRequest = $leaveRequest;
        $this->type = $type;
    }

    public function via($notifiable)
    {
        // 'mail',
        return [ 'database'];
    }

    public function toMail($notifiable)
    {
        $user = $this->leaveRequest->user;
        $leaveType = $this->leaveRequest->leaveType;
        
        if ($this->type == 'parent') {
            return (new MailMessage)
                ->subject('📋 New Leave Request - ' . $leaveType->name)
                ->greeting('Hello ' . $notifiable->name . ',')
                ->line('**' . $user->name . '** has submitted a leave request.')
                ->line('**Leave Type:** ' . $leaveType->name)
                ->line('**Duration:** ' . $this->leaveRequest->start_date->format('M d, Y') . ' → ' . $this->leaveRequest->end_date->format('M d, Y'))
                ->line('**Days:** ' . $this->leaveRequest->days)
                ->line('**Reason:** ' . ($this->leaveRequest->reason ?? 'No reason provided'))
                ->action('Review Request', url('/api/leaves/' . $this->leaveRequest->id))
                ->line('Please review and take action.');
        } else {
            return (new MailMessage)
                ->subject('✅ Leave Request Pending - HR Approval')
                ->greeting('Hello ' . $notifiable->name . ',')
                ->line('**' . $user->name . '** leave request has been approved by their manager.')
                ->line('**Leave Type:** ' . $leaveType->name)
                ->line('**Duration:** ' . $this->leaveRequest->start_date->format('M d, Y') . ' → ' . $this->leaveRequest->end_date->format('M d, Y'))
                ->line('**Days:** ' . $this->leaveRequest->days)
                ->action('Review Request', url('/api/leaves/' . $this->leaveRequest->id))
                ->line('Please review and take final approval.');
        }
    }

    public function toDatabase($notifiable)
    {
        $user = $this->leaveRequest->user;
        $leaveType = $this->leaveRequest->leaveType;
        
        if ($this->type == 'parent') {
            return [
                'type' => 'leave_request',
                'leave_request_id' => $this->leaveRequest->id,
                'title' => 'New Leave Request',
                'message' => $user->name . ' requested a ' . $leaveType->name . ' for ' . $this->leaveRequest->days . ' days',
                'status' => 'pending_parent',
                'action_url' => '/leaves/' . $this->leaveRequest->id,
            ];
        } else {
            return [
                'type' => 'leave_request_hr',
                'leave_request_id' => $this->leaveRequest->id,
                'title' => 'Leave Request - Pending HR Approval',
                'message' => $user->name . ' leave request has been approved by manager. ' . $this->leaveRequest->days . ' days',
                'status' => 'pending_hr',
                'action_url' => '/leaves/' . $this->leaveRequest->id,
            ];
        }
    }

    public function toArray($notifiable)
    {
        return [
            'leave_request_id' => $this->leaveRequest->id,
            'type' => $this->type,
            'status' => $this->leaveRequest->status,
        ];
    }
}