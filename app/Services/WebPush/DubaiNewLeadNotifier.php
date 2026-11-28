<?php

namespace App\Services\WebPush;

use App\Jobs\SendLeadAssignmentWebPush;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\DubaiNewLeadAlertNotification;
use App\Services\EligibleStagesAutoMergeService;
use Illuminate\Support\Facades\Log;

class DubaiNewLeadNotifier
{
    /**
     * Notify configured Dubai watchers when a lead enters the New Lead stage.
     * Never throws — a failed alert must not undo the lead write.
     */
    public function notifyIfEligible(Lead $lead, bool $onlyIfNewlyEligible = false): void
    {
        try {
            if ($onlyIfNewlyEligible && $this->wasAlreadyEligible($lead)) {
                return;
            }

            if (! $this->shouldNotify($lead)) {
                return;
            }

            $recipientIds = $this->recipientIds();
            if ($recipientIds === []) {
                return;
            }

            $recipients = User::query()
                ->whereIn('id', $recipientIds)
                ->where('status', 'active')
                ->get();

            foreach ($recipients as $recipient) {
                try {
                    $recipient->notify(new DubaiNewLeadAlertNotification((int) $lead->id));
                } catch (\Throwable $e) {
                    Log::warning('dubai_new_lead.notification_failed', [
                        'lead_id' => (int) $lead->id,
                        'user_id' => (int) $recipient->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                try {
                    SendLeadAssignmentWebPush::dispatchSync(
                        (int) $recipient->id,
                        (int) $lead->id,
                        'You have a new lead',
                        'A new Dubai lead is in the New Lead stage.',
                        'dubai_new_lead'
                    );
                } catch (\Throwable $e) {
                    Log::warning('dubai_new_lead.web_push_dispatch_failed', [
                        'lead_id' => (int) $lead->id,
                        'user_id' => (int) $recipient->id,
                        'error' => $e::class,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('dubai_new_lead.notification_failed', [
                'lead_id' => (int) $lead->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function shouldNotify(Lead $lead): bool
    {
        if (! (bool) config('services.dubai_new_lead_alert.enabled', true)) {
            return false;
        }

        $merge = app(EligibleStagesAutoMergeService::class);
        $newLeadStageId = $merge->newLeadStageId();
        if (! $newLeadStageId || (int) $lead->stage_id !== (int) $newLeadStageId) {
            return false;
        }

        return $merge->isDubaiBranch($lead);
    }

    /**
     * True when the lead already matched Dubai + New Lead before this update.
     */
    public function wasAlreadyEligible(Lead $lead): bool
    {
        $merge = app(EligibleStagesAutoMergeService::class);
        $newLeadStageId = $merge->newLeadStageId();
        if (! $newLeadStageId) {
            return false;
        }

        $previous = $lead->replicate();
        $previous->exists = true;
        foreach (['stage_id', 'branch', 'lead_branch_source', 'responsible_person_id'] as $field) {
            if (array_key_exists($field, $lead->getOriginal())) {
                $previous->setAttribute($field, $lead->getOriginal($field));
            }
        }

        // Keep relation lookups consistent with the previous responsible person.
        if ($previous->responsible_person_id) {
            $previous->setRelation(
                'responsiblePerson',
                User::query()->find($previous->responsible_person_id)
            );
        } else {
            $previous->setRelation('responsiblePerson', null);
        }

        return (int) $previous->stage_id === (int) $newLeadStageId
            && $merge->isDubaiBranch($previous);
    }

    /**
     * @return int[]
     */
    public function recipientIds(): array
    {
        $ids = config('services.dubai_new_lead_alert.user_ids', [1541, 195]);
        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id) => $id > 0)));
    }
}
