<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPropertyFinderEventJob;
use App\Models\PropertyFinderWebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

/**
 * Property Finder Enterprise webhook receiver — POST /api/propertyfinder/webhook.
 * Subscribe with `php artisan propertyfinder:webhooks subscribe`.
 *
 * PF expects a 2xx within 5 seconds and retries otherwise, so we only verify the
 * HMAC signature, store the event (deduped on PF's event id) and queue the work.
 */
class PropertyFinderWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $raw = $request->getContent();

        // X-Signature = hex HMAC-SHA256 of the raw body, keyed with the subscription secret.
        $secret = config('propertyfinder.webhook_secret');
        if (filled($secret)) {
            $expected = hash_hmac('sha256', $raw, $secret);
            $given = strtolower(trim((string) $request->header('X-Signature')));
            if (! hash_equals($expected, $given)) {
                Log::channel('propertyfinder')->warning('PF webhook: bad signature', [
                    'ip' => $request->ip(),
                    'type' => $request->input('type'),
                ]);

                return response()->json(['ok' => false, 'error' => 'invalid signature'], 401);
            }
        }

        $payload = json_decode($raw, true);
        $eventId = is_array($payload) ? (string) ($payload['id'] ?? '') : '';
        $type = is_array($payload) ? (string) ($payload['type'] ?? '') : '';

        if ($eventId === '' || $type === '') {
            Log::channel('propertyfinder')->warning('PF webhook: unrecognised body', ['body' => mb_substr($raw, 0, 1000)]);

            return response()->json(['ok' => true, 'skipped' => 'no event id']);
        }

        $isLeadEvent = str_starts_with($type, 'lead.');

        $event = PropertyFinderWebhookEvent::firstOrCreate(
            ['event_id' => $eventId],
            [
                'type' => $type,
                'pf_lead_id' => $isLeadEvent ? (string) Arr::get($payload, 'entity.id') : null,
                'payload' => $payload,
                'status' => $isLeadEvent ? 'pending' : 'ignored',
            ]
        );

        Log::channel('propertyfinder')->info('PF webhook', [
            'event_id' => $eventId, 'type' => $type, 'pf_lead_id' => $event->pf_lead_id, 'duplicate' => ! $event->wasRecentlyCreated,
        ]);

        // Retries of an already-stored event are acknowledged without re-processing.
        if ($event->wasRecentlyCreated && $isLeadEvent) {
            ProcessPropertyFinderEventJob::dispatch($event->id);
        }

        return response()->json(['ok' => true]);
    }
}
