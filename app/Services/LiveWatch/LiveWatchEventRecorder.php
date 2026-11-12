<?php

namespace App\Services\LiveWatch;

use App\Events\LiveWatchTimelineEvent;
use App\Models\LiveWatchEvent;
use App\Models\LiveWatchSetting;
use Illuminate\Support\Str;

class LiveWatchEventRecorder
{
    public function record(array $payload): ?LiveWatchEvent
    {
        $userId = (int) ($payload['user_id'] ?? 0);
        if ($userId <= 0) {
            return null;
        }

        $settings = LiveWatchSetting::current();
        $monitoredId = (int) ($settings->monitored_user_id ?? 0);
        if ($monitoredId === 0 || $userId !== $monitoredId) {
            return null;
        }

        $kind = (string) ($payload['event_kind'] ?? '');
        if (! in_array($kind, ['navigation', 'business'], true)) {
            return null;
        }

        $clientEventId = isset($payload['client_event_id'])
            ? Str::limit((string) $payload['client_event_id'], 64, '')
            : null;

        if ($clientEventId) {
            $existing = LiveWatchEvent::query()->where('client_event_id', $clientEventId)->first();
            if ($existing) {
                return $existing;
            }
        }

        // Soft dedupe for navigation remounts without a client id.
        if ($kind === 'navigation' && ! $clientEventId) {
            $window = (int) config('live_watch.navigation_dedupe_seconds', 8);
            $dup = LiveWatchEvent::query()
                ->where('user_id', $userId)
                ->where('event_kind', 'navigation')
                ->where('action', (string) ($payload['action'] ?? ''))
                ->where('event_type', (string) ($payload['event_type'] ?? ''))
                ->where('entity_type', $payload['entity_type'] ?? null)
                ->where('entity_id', $payload['entity_id'] ?? null)
                ->where('occurred_at', '>=', now()->subSeconds($window))
                ->exists();
            if ($dup) {
                return null;
            }
        }

        $metadata = $this->sanitizeMetadata($payload['metadata'] ?? null);

        try {
            $event = LiveWatchEvent::query()->create([
                'user_id' => $userId,
                'event_kind' => $kind,
                'event_type' => Str::limit((string) ($payload['event_type'] ?? 'unknown'), 64, ''),
                'module' => isset($payload['module']) ? Str::limit((string) $payload['module'], 64, '') : null,
                'action' => Str::limit((string) ($payload['action'] ?? 'unknown'), 64, ''),
                'entity_type' => isset($payload['entity_type']) ? Str::limit((string) $payload['entity_type'], 64, '') : null,
                'entity_id' => isset($payload['entity_id']) ? (int) $payload['entity_id'] : null,
                'metadata' => $metadata,
                'session_id' => isset($payload['session_id']) ? Str::limit((string) $payload['session_id'], 64, '') : null,
                'client_event_id' => $clientEventId ?: null,
                'occurred_at' => $payload['occurred_at'] ?? now(),
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            return LiveWatchEvent::query()->where('client_event_id', $clientEventId)->first();
        }

        broadcast(new LiveWatchTimelineEvent($event));

        return $event;
    }

    public function recordBusinessForMonitoredUser(
        int $userId,
        string $module,
        string $action,
        string $eventType,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $metadata = null
    ): ?LiveWatchEvent {
        return $this->record([
            'user_id' => $userId,
            'event_kind' => 'business',
            'event_type' => $eventType,
            'module' => $module,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata,
            'client_event_id' => null,
        ]);
    }

    private function sanitizeMetadata(mixed $metadata): ?array
    {
        if (! is_array($metadata)) {
            return null;
        }

        $blocked = [
            'password', 'password_confirmation', 'token', 'access_token', 'refresh_token',
            'authorization', 'cookie', 'secret', 'api_key', 'message', 'body', 'content',
            'form', 'payload', 'query', 'query_string', 'phone', 'mobile', 'email_body',
        ];

        $clean = [];
        foreach ($metadata as $key => $value) {
            $k = strtolower((string) $key);
            if (in_array($k, $blocked, true)) {
                continue;
            }
            if (is_array($value) || is_object($value)) {
                continue;
            }
            if (is_string($value)) {
                $clean[$key] = Str::limit($value, 120, '');
            } elseif (is_scalar($value) || $value === null) {
                $clean[$key] = $value;
            }
        }

        $encoded = json_encode($clean);
        $max = (int) config('live_watch.max_metadata_bytes', 2048);
        if ($encoded !== false && strlen($encoded) > $max) {
            return ['_truncated' => true];
        }

        return $clean === [] ? null : $clean;
    }
}
