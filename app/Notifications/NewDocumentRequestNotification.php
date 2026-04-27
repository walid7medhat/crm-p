<?php

namespace App\Notifications;

use App\Models\DocumentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;

class NewDocumentRequestNotification extends Notification
{
    use Queueable;

    protected $documentRequest;

    public function __construct(DocumentRequest $documentRequest)
    {
        $this->documentRequest = $documentRequest;
    }

    public function via($notifiable): array
    {
        // mail
        return [ 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('📄 New Document Request - ' . $this->documentRequest->documentType->name)
            ->line('Employee: ' . $this->documentRequest->user->name)
            ->line('Document Type: ' . $this->documentRequest->documentType->name)
            ->line('Description: ' . ($this->documentRequest->description ?? 'No description'))
            ->action('View Request', url('/api/document-requests/' . $this->documentRequest->id))
            ->line('Please review and take action.');
    }

    public function toDatabase($notifiable): array
    {
        return [
            'document_request_id' => $this->documentRequest->id,
            'title' => 'New Document Request',
            'message' => $this->documentRequest->user->name . ' requested a ' . $this->documentRequest->documentType->name,
            'type' => 'document_request',
            'status' => 'pending',
        ];
    }
}