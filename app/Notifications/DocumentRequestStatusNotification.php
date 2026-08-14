<?php

namespace App\Notifications;

use App\Models\DocumentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class DocumentRequestStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected DocumentRequest $documentRequest, protected string $status)
    {
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    public function toArray($notifiable)
    {
        return [
            'type' => 'document_request_status',
            'document_request_id' => $this->documentRequest->id,
            'document_type' => $this->documentRequest->documentType?->name,
            'status' => $this->status,
            'rejection_reason' => $this->documentRequest->rejection_reason,
            'message' => $this->status === 'approved'
                ? "Your {$this->documentRequest->documentType?->name} request was approved."
                : "Your {$this->documentRequest->documentType?->name} request was rejected.",
        ];
    }
      public function broadcastType(): string
        {
            return $this->toArray(null)['type'] ?? 'notification';
        }
}