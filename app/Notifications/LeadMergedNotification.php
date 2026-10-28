<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class LeadMergedNotification extends Notification
{
    use Queueable;

    public $lead;        // the PRIMARY lead
    public $dedupeKey;

    public function __construct(Lead $primaryLead, string $dedupeKey)
    {
        $this->lead = $primaryLead;
        $this->dedupeKey = $dedupeKey;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'lead_id'     => $this->lead->id,
            'lead_name'   => $this->lead->lead_name,
            'lead_number' => $this->lead->lead_number,
            'action_type' => 'merged',
            'user_id'     => null,
            'user_name'   => null,
            'changes'     => null,
            'message'     => $this->getMessage(),
            'dedupe_key'  => $this->dedupeKey,
            'timestamp'   => now()->toISOString(),
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'lead' => [
                'id'                    => $this->lead->id,
                'lead_name'             => $this->lead->lead_name,
                'lead_number'           => $this->lead->lead_number,
                'stage_id'              => $this->lead->stage_id,
                'responsible_person_id' => $this->lead->responsible_person_id,
            ],
            'action_type' => 'merged',
            'user_id'     => null,
            'user_name'   => null,
            'changes'     => null,
            'message'     => $this->getMessage(),
            'timestamp'   => now()->toISOString(),
        ]);
    }

    public function getMessage()
    {
        $leadName = $this->lead->lead_name ?: "Lead #{$this->lead->lead_number}";

        return "A lead has been merged into your lead: {$leadName}.";
    }
}