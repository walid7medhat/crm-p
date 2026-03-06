<?php

namespace App\Notifications;

use App\Models\DealComment;
use App\Models\deal;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;

class DealCommentMentionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $comment;
    public $mentionedBy;
    public $deal;
    public $actionType = 'mentioned';

    /**
     * Create a new notification instance.
     */
    public function __construct(DealComment $comment)
    {
        $this->comment = $comment;
        $this->mentionedBy = $comment->user;
        $this->deal = $comment->deal;
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
        $dealName = $this->deal->deal_name ?: "deal #{$this->deal->deal_number}";
        // $url = url('/deals/' . $this->deal->id . '#comment-' . $this->comment->id);
        $url=url('kanban');
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
        $dealName = $this->deal->deal_name ?: "deal #{$this->deal->deal_number}";
        
        return [
            'type' => 'comment_mention',
            'comment_id' => $this->comment->id,
            'deal_id' => $this->deal->id,
            'deal_name' => $dealName,
            'deal_number' => $this->deal->deal_number,
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
        $dealName = $this->deal->deal_name ?: "deal #{$this->deal->deal_number}";
        
        return new BroadcastMessage([
            'comment' => [
                'id' => $this->comment->id,
                'comment' => substr($this->comment->comment, 0, 100),
                'has_attachments' => $this->comment->attachments->count() > 0,
                'attachments_count' => $this->comment->attachments->count(),
            ],
            'deal' => [
                'id' => $this->deal->id,
                'deal_name' => $dealName,
                'deal_number' => $this->deal->deal_number,
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
        $dealName = $this->deal->deal_name ?: "deal #{$this->deal->deal_number}";
        $userName = $this->mentionedBy->name;
        
        $attachmentsInfo = '';
        if ($this->comment->attachments->count() > 0) {
            $attachmentsInfo = " with {$this->comment->attachments->count()} attachment(s)";
        }
        
        return "{$userName} mentioned you in a comment{$attachmentsInfo} on deal: {$dealName}";
    }

    /**
     * Get notification subject.
     */
    private function getSubject(): string
    {
        $dealName = $this->deal->deal_name ?: "deal #{$this->deal->deal_number}";
        return "You were mentioned in a comment - {$dealName}";
    }
}