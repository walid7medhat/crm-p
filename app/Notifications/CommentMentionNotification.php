<?php

namespace App\Notifications;

use App\Models\LeadComment;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;

class CommentMentionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $comment;
    public $mentionedBy;
    public $lead;
    public $actionType = 'mentioned';

    /**
     * Create a new notification instance.
     */
    public function __construct(LeadComment $comment)
    {
        $this->comment = $comment;
        $this->mentionedBy = $comment->user;
        $this->lead = $comment->lead;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable)
    {
        return ['database', 'broadcast', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        $leadName = $this->lead->lead_name ?: "Lead #{$this->lead->lead_number}";
        $url = url('/leads/' . $this->lead->id . '#comment-' . $this->comment->id);
        
        return (new MailMessage)
            ->subject($this->getSubject())
            ->line($this->getMessage())
            ->action('View Comment', $url)
            ->line('Thank you for using our application!');
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase($notifiable)
    {
        $leadName = $this->lead->lead_name ?: "Lead #{$this->lead->lead_number}";
        
        return [
            'type' => 'comment_mention',
            'comment_id' => $this->comment->id,
            'lead_id' => $this->lead->id,
            'lead_name' => $leadName,
            'lead_number' => $this->lead->lead_number,
            'action_type' => $this->actionType,
            'user_id' => $this->mentionedBy->id,
            'user_name' => $this->mentionedBy->name,
            'changes' => [
                'comment' => $this->comment->comment,
                'has_attachments' => $this->comment->attachments->count() > 0,
                'attachments_count' => $this->comment->attachments->count(),
            ],
            'message' => $this->getMessage(),
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Get the broadcast representation of the notification.
     */
    public function toBroadcast($notifiable)
    {
        $leadName = $this->lead->lead_name ?: "Lead #{$this->lead->lead_number}";
        
        return new BroadcastMessage([
            'comment' => [
                'id' => $this->comment->id,
                'comment' => substr($this->comment->comment, 0, 100),
                'has_attachments' => $this->comment->attachments->count() > 0,
                'attachments_count' => $this->comment->attachments->count(),
            ],
            'lead' => [
                'id' => $this->lead->id,
                'lead_name' => $leadName,
                'lead_number' => $this->lead->lead_number,
            ],
            'action_type' => $this->actionType,
            'user_id' => $this->mentionedBy->id,
            'user_name' => $this->mentionedBy->name,
            'changes' => [
                'comment' => substr($this->comment->comment, 0, 100),
                'has_attachments' => $this->comment->attachments->count() > 0,
            ],
            'message' => $this->getMessage(),
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Get notification message.
     */
    public function getMessage()
    {
        $leadName = $this->lead->lead_name ?: "Lead #{$this->lead->lead_number}";
        $userName = $this->mentionedBy->name;
        
        $attachmentsInfo = '';
        if ($this->comment->attachments->count() > 0) {
            $attachmentsInfo = " with {$this->comment->attachments->count()} attachment(s)";
        }
        
        return "{$userName} mentioned you in a comment{$attachmentsInfo} on lead: {$leadName}";
    }

    /**
     * Get notification subject.
     */
    private function getSubject(): string
    {
        $leadName = $this->lead->lead_name ?: "Lead #{$this->lead->lead_number}";
        return "You were mentioned in a comment - {$leadName}";
    }
}