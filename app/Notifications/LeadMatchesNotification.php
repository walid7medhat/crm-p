<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Match Studio alerts. With a lead_id the bell opens that lead on its Matches tab;
 * without one (digests, a listing that fits several clients) it opens the dock's Matches panel.
 */
class LeadMatchesNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected array $payload)
    {
    }

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray($notifiable): array
    {
        return array_merge(['type' => 'lead_matches'], $this->payload);
    }

    public function broadcastType(): string
    {
        return 'lead_matches';
    }
}
