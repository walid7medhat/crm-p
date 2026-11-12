<?php

namespace App\Observers;

use App\Services\LiveWatch\LiveWatchEventRecorder;
use Spatie\Activitylog\Models\Activity;

/**
 * Forwards Spatie activitylog rows (listings/catalog) for the monitored user only.
 */
class LiveWatchActivityObserver
{
    private const ALLOWED_LOGS = [
        'listing',
        'owner',
        'developer',
        'project',
        'area',
        'stage',
        'user',
    ];

    public function __construct(private LiveWatchEventRecorder $recorder)
    {
    }

    public function created(Activity $activity): void
    {
        $userId = (int) ($activity->causer_id ?? 0);
        if ($userId <= 0 || $activity->causer_type !== 'App\\Models\\User') {
            return;
        }

        $logName = (string) ($activity->log_name ?? '');
        if (! in_array($logName, self::ALLOWED_LOGS, true)) {
            return;
        }

        $event = (string) ($activity->event ?? 'updated');
        $subjectType = class_basename((string) ($activity->subject_type ?? $logName));

        $this->recorder->recordBusinessForMonitoredUser(
            $userId,
            $logName === 'listing' ? 'listings' : $logName,
            $event,
            $logName.'.'.$event,
            strtolower($subjectType) ?: $logName,
            $activity->subject_id ? (int) $activity->subject_id : null,
            [
                'description' => is_string($activity->description)
                    ? mb_substr($activity->description, 0, 120)
                    : null,
            ]
        );
    }
}
