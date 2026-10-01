<?php

namespace App\Services\WebPush;

use App\Jobs\SendLeadAssignmentWebPush;
use App\Models\User;
use App\Notifications\AssignmentPocNotification;
use Illuminate\Support\Facades\Log;

class LeadAssignmentTestNotifier
{
    /**
     * Never throws, so a failed toast or push cannot undo the assignment.
     * Pusher and Web Push are separate; either one can fail on its own.
     */
    public function notifyIfChanged(mixed $previousResponsibleId, mixed $newResponsibleId, int $leadId): void
    {
        try {
            $recipientId = $this->recipientId($previousResponsibleId, $newResponsibleId);
            if ($recipientId === null) {
                return;
            }

            $recipient = User::query()->find($recipientId);
            if (! $recipient || $recipient->status !== 'active') {
                return;
            }

            try {
                $recipient->notify(new AssignmentPocNotification());
            } catch (\Throwable $e) {
                Log::warning('lead_assignment.test_notification_failed', [
                    'lead_id' => $leadId,
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                SendLeadAssignmentWebPush::dispatchSync($recipientId, $leadId);
            } catch (\Throwable $e) {
                Log::warning('lead_assignment.test_web_push_dispatch_failed', [
                    'lead_id' => $leadId,
                    'error' => $e::class,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('lead_assignment.test_notification_failed', [
                'lead_id' => $leadId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function recipientId(mixed $previousResponsibleId, mixed $newResponsibleId): ?int
    {
        $next = (int) $newResponsibleId;
        if ($next < 1 || (int) $previousResponsibleId === $next) {
            return null;
        }

        return $next;
    }
}
