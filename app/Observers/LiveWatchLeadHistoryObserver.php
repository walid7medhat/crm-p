<?php

namespace App\Observers;

use App\Models\LeadHistory;
use App\Services\LiveWatch\LiveWatchEventRecorder;

/**
 * Mirrors lead/deal history rows into Live Watch when the actor is the monitored user.
 * Does not alter LeadHistory behaviour and avoids scanning unrelated CRM endpoints.
 */
class LiveWatchLeadHistoryObserver
{
    public function __construct(private LiveWatchEventRecorder $recorder)
    {
    }

    public function created(LeadHistory $history): void
    {
        $userId = (int) ($history->user_id ?? 0);
        if ($userId <= 0) {
            return;
        }

        $changes = is_array($history->changes) ? $history->changes : [];
        $action = (string) ($changes['action'] ?? 'updated');

        if (! empty($history->deal_id)) {
            $this->recorder->recordBusinessForMonitoredUser(
                $userId,
                'deals',
                $action,
                'deal.'.$action,
                'deal',
                (int) $history->deal_id,
                $this->safeMeta($changes, ['stage', 'status', 'from', 'to', 'field'])
            );

            return;
        }

        if (! empty($history->lead_id)) {
            $this->recorder->recordBusinessForMonitoredUser(
                $userId,
                'leads',
                $action,
                'lead.'.$action,
                'lead',
                (int) $history->lead_id,
                $this->safeMeta($changes, ['stage', 'status', 'from', 'to', 'field', 'assigned_to', 'merge_type'])
            );
        }
    }

    private function safeMeta(array $changes, array $keys): array
    {
        $meta = [];
        foreach ($keys as $key) {
            if (! array_key_exists($key, $changes)) {
                continue;
            }
            $value = $changes[$key];
            if (is_scalar($value) || $value === null) {
                $meta[$key] = $value;
            }
        }

        return $meta;
    }
}
