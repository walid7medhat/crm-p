<?php

namespace App\Notifications;

use App\Models\AssetRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Contracts\Queue\ShouldQueue;

class NewAssetRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected AssetRequest $assetRequest)
    {
    }

    public function via($notifiable)
    {
        return ['database','broadcast'];
        // add 'mail' here if you also want emails: ['database', 'mail']
    }

    public function toArray($notifiable)
    {
        return [
            'type' => 'asset_request',
            'asset_request_id' => $this->assetRequest->id,
            'asset_item' => $this->assetRequest->asset_item,
            'user_id' => $this->assetRequest->user_id,
            'user_name' => $this->assetRequest->user?->name,
            'qty' => $this->assetRequest->qty,
            'message' => "{$this->assetRequest->user?->name} requested {$this->assetRequest->qty}x {$this->assetRequest->asset_item}",
        ];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('New Asset Request')
            ->line("{$this->assetRequest->user?->name} has submitted a new asset request.")
            ->line("Item: {$this->assetRequest->asset_item} (Qty: {$this->assetRequest->qty})")
            ->action('Review Request', url('/hr/assets'));
    }
      public function broadcastType(): string
        {
            return $this->toArray(null)['type'] ?? 'notification';
        }
}