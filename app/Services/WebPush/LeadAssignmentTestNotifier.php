<?php

namespace App\Services\WebPush;

use App\Jobs\SendLeadAssignmentWebPush;
use App\Models\User;
use App\Notifications\AssignmentPocNotification;
use Illuminate\Support\Facades\Log;

class LeadAssignmentTestNotifier
{
    /**
     * Proof-of-concept only. Never throws, so a failed toast or push cannot undo the assignment.
     */
    public function notifyIfChanged(mixed $previousResponsibleId, mixed $newResponsibleId, int $leadId): void
    {
        try {
            $testUserId = $this->recipientId($previousResponsibleId, $newResponsibleId);
            if ($testUserId === null) {
                return;
            }

            $recipient = User::query()->find($testUserId);
            if (! $recipient) {
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
                SendLeadAssignmentWebPush::dispatchSync($testUserId);
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
        if ((int) $previousResponsibleId === (int) $newResponsibleId) {
            return null;
        }

        if (! config('services.lead_assignment_test.enabled')) {
            return null;
        }

        $testUserId = (int) config('services.lead_assignment_test.user_id');
        if ($testUserId < 1 || (int) $newResponsibleId !== $testUserId) {
            return null;
        }

        return $testUserId;
    }
}
