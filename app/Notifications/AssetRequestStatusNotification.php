<?php

namespace App\Notifications;

use App\Models\AssetRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class AssetRequestStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected AssetRequest $assetRequest, protected string $status)
    {
    }

    public function via($notifiable)
    {
        return ['database','broadcast'];
    }

    public function toArray($notifiable)
    {
        return [
            'type' => 'asset_request_status',
            'asset_request_id' => $this->assetRequest->id,
            'asset_item' => $this->assetRequest->asset_item,
            'status' => $this->status,
            'rejection_reason' => $this->assetRequest->rejection_reason,
            'message' => $this->status === 'approved'
                ? "Your request for {$this->assetRequest->asset_item} was approved."
                : "Your request for {$this->assetRequest->asset_item} was rejected.",
        ];
    }
    public function broadcastType(): string
        {
            return $this->toArray(null)['type'] ?? 'notification';
        }
}