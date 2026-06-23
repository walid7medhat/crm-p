<?php

namespace App\Http\Controllers\Api;

use App\Events\LeadUpdated;
use App\Http\Controllers\Controller;
use App\Services\Bitrix24\Bitrix24Client;
use App\Services\Bitrix24\Bitrix24LeadImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Real-time Bitrix24 event receiver (outbound webhooks / events) — pushes
 * changes the moment they happen, instead of polling. Register these events in
 * Bitrix24 (Developer resources → Other → Outbound webhook) pointing at
 * POST /api/bitrix24/webhook:
 *   ONCRMLEADADD, ONCRMLEADUPDATE, ONCRMACTIVITYADD, ONCRMTIMELINECOMMENTADD
 *
 * Each event carries only the entity ID, so we fetch the lead from Bitrix24 and
 * re-sync just that one lead via importOneFast (idempotent). We always return
 * 200 quickly so Bitrix24 doesn't retry/disable the handler.
 */
class Bitrix24WebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        // ---- verify the shared secret (auth[application_token] for events) ----
        $expected = config('bitrix24.event_token');
        $token = $request->input('auth.application_token')
            ?? $request->input('application_token')
            ?? $request->input('token');

        if ($expected && ! hash_equals((string) $expected, (string) $token)) {
            // Diagnostic: show the token Bitrix actually sent (copy it into
            // BITRIX24_EVENT_TOKEN) and where it sits in the payload.
            Log::channel('bitrix_leads')->warning('Bitrix24 webhook: bad token', [
                'event'          => $request->input('event'),
                'received_token' => $token,
                'request_keys'   => array_keys($request->all()),
                'auth'           => $request->input('auth'),
            ]);
            return response()->json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        $event = strtoupper((string) $request->input('event'));
        $entityId = (int) (
            $request->input('data.FIELDS.ID')
            ?? $request->input('data.FIELDS.id')
            ?? 0
        );

        Log::channel('bitrix_leads')->info('Bitrix24 webhook', ['event' => $event, 'id' => $entityId]);

        if ($entityId <= 0) {
            return response()->json(['ok' => true, 'skipped' => 'no id']);
        }

        try {
            $client = new Bitrix24Client();
            $importer = new Bitrix24LeadImporter($client, (int) config('bitrix24.fallback_user_id', 1));

            match ($event) {
                'ONCRMLEADADD', 'ONCRMLEADUPDATE' => $this->syncLead($client, $importer, $entityId),
                'ONCRMACTIVITYADD'                => $this->syncLead($client, $importer, $this->leadIdOfActivity($client, $entityId)),
                'ONCRMTIMELINECOMMENTADD'         => $this->syncLead($client, $importer, $this->leadIdOfComment($client, $entityId)),
                default                           => null,
            };
        } catch (\Throwable $e) {
            // Swallow + log: still 200 so Bitrix24 doesn't hammer retries.
            Log::channel('bitrix_leads')->error('Bitrix24 webhook failed', [
                'event' => $event, 'id' => $entityId, 'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    /** Fetch a single lead from Bitrix24, re-sync it, then push it live to the board. */
    private function syncLead(Bitrix24Client $client, Bitrix24LeadImporter $importer, ?int $leadId): void
    {
        if (! $leadId) {
            return;
        }
        $b24 = $client->getLead($leadId);
        if (! $b24) {
            return;
        }

        $result = $importer->importOneFast($b24);
        $lead = $result['lead'] ?? null;

        // Real-time Kanban sync; source=bitrix suppresses toasts and bell notifications.
        if ($lead) {
            event(new LeadUpdated(
                $lead,
                $result['created'] ? 'created' : 'updated',
                null,
                null,
                'bitrix'
            ));
        }
    }

    /** Resolve the owning lead id from an activity (OWNER_TYPE_ID 1 = lead). */
    private function leadIdOfActivity(Bitrix24Client $client, int $activityId): ?int
    {
        $a = $client->getActivity($activityId);
        $ownerType = (int) ($a['OWNER_TYPE_ID'] ?? 0);
        $ownerId = (int) ($a['OWNER_ID'] ?? 0);
        return ($ownerType === 1 && $ownerId > 0) ? $ownerId : null;
    }

    /** Resolve the lead id from a timeline comment (ENTITY_TYPE 'lead'). */
    private function leadIdOfComment(Bitrix24Client $client, int $commentId): ?int
    {
        $c = $client->getTimelineComment($commentId);
        $entityType = strtolower((string) ($c['ENTITY_TYPE'] ?? ''));
        $entityId = (int) ($c['ENTITY_ID'] ?? 0);
        return ($entityType === 'lead' && $entityId > 0) ? $entityId : null;
    }
}
