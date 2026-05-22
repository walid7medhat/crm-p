<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Jobs\SyncBitrix24LeadsJob;
use App\Services\Bitrix24\Bitrix24Client;
use App\Services\Bitrix24\Bitrix24Exception;
use App\Services\Bitrix24\Bitrix24LeadImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\BitrixSyncState;

class Bitrix24SyncController extends Controller
{
    /**
     * Synchronous batched sync. Frontend calls this repeatedly with the
     * `next` cursor returned by the previous response, until `done` is true.
     *
     *   POST /api/leads/bitrix24/sync { start?: int, batch_size?: int (max 50) }
     *   -> {
     *        imported_in_batch, new_count, existing_count,
     *        new_leads:      [{ lead_id, bitrix24_id }, ...],
     *        existing_leads: [{ lead_id, bitrix24_id }, ...],
     *        errors, next (int|null), total, done
     *      }
     */
   public function syncBatch(Request $request): JsonResponse
{
    $user = auth()->user();
    if (!$user || !$user->hasRole(['admin', 'super_admin'])) {
        return ApiResponse::error('Only admins can sync from Bitrix24', 403);
    }

    $request->validate([
        'start'         => 'nullable|integer|min:0',
        'skip_existing' => 'nullable|boolean',
    ]);

    $start = (int) ($request->input('start') ?? 0);
    $skipExisting = (bool) $request->input('skip_existing', false);

    try {
        $client   = new Bitrix24Client();
        $importer = new Bitrix24LeadImporter($client, $user->id);
$start = $start ?? 0;

        // ✅ call Bitrix
        $page = $client->listLeads($start);

        $b24Leads = $page['result'] ?? [];
        $total    = (int) ($page['total'] ?? 0);

        // ❌ مفيش slicing تاني
        $imported = 0;
        $newLeads = [];
        $existingLeads = [];
        $errors = [];

        // ✅ check existing مرة واحدة
        $existingIds = [];
        if ($skipExisting && !empty($b24Leads)) {
            $ids = array_map(fn ($l) => (int) ($l['ID'] ?? 0), $b24Leads);

            $existingIds = \App\Models\Lead::whereIn('bitrix24_id', $ids)
                ->pluck('bitrix24_id')
                ->map(fn ($v) => (int) $v)
                ->all();
        }

        $existingSet = array_flip($existingIds);

        foreach ($b24Leads as $b24) {
            $b24Id = (int) ($b24['ID'] ?? 0);

            if ($skipExisting && isset($existingSet[$b24Id])) {
                $localId = \App\Models\Lead::where('bitrix24_id', $b24Id)->value('id');

                $existingLeads[] = [
                    'lead_id'     => (int) $localId,
                    'bitrix24_id' => $b24Id,
                ];

                $imported++;
                continue;
            }

            try {
                $r = $importer->importOne($b24);

                $entry = [
                    'lead_id'     => $r['lead']->id,
                    'bitrix24_id' => $r['bitrix24_id'],
                ];

                if ($r['created']) {
                    $newLeads[] = $entry;
                } else {
                    $existingLeads[] = $entry;
                }

                $imported++;
            } catch (\Throwable $e) {
                $errors[] = [
                    'bitrix24_id' => $b24['ID'] ?? null,
                    'error'       => $e->getMessage(),
                ];
            }
        }

        // ✅ أهم جزء: next من Bitrix نفسه
        $nextCursor = $page['next'] ?? null;
        $done = $nextCursor === null;

        return ApiResponse::success([
            'imported_in_batch' => $imported,
            'new_count'         => count($newLeads),
            'existing_count'    => count($existingLeads),
            'new_leads'         => $newLeads,
            'existing_leads'    => $existingLeads,
            'errors'            => $errors,
            'next'              => $nextCursor,
            'total'             => $total,
            'done'              => $done,
            'skip_existing'     => $skipExisting,
        ], $done ? 'Bitrix24 sync complete' : 'Batch imported');

    } catch (\Throwable $e) {
        return ApiResponse::error('Bitrix24 sync failed: ' . $e->getMessage(), 500);
    }
}

    /**
     * Fetch + import a single Bitrix24 lead by its Bitrix24 ID.
     *   POST /api/leads/bitrix24/fetch/{bitrixId}
     */
    public function fetchOne(int $bitrixId): JsonResponse
    {
        $user = auth()->user();
        if (!$user || !$user->hasRole(['admin', 'super_admin'])) {
            return ApiResponse::error('Only admins can fetch from Bitrix24', 403);
        }

        try {
            $client = new Bitrix24Client();
            $b24 = $client->getLead($bitrixId);
            if (!$b24) {
                return ApiResponse::error("Bitrix24 lead {$bitrixId} not found", 404);
            }
            $importer = new Bitrix24LeadImporter($client, $user->id);
            $r = $importer->importOne($b24);

            return ApiResponse::success([
                'lead_id'      => $r['lead']->id,
                'bitrix24_id'  => $bitrixId,
                'created'      => $r['created'],
                'already_existed' => !$r['created'],
            ], $r['created'] ? 'Lead imported from Bitrix24' : 'Lead already existed locally — refreshed timeline and stage.');
        } catch (Bitrix24Exception $e) {
            if ($e->isNotFound()) {
                return ApiResponse::error(
                    "Bitrix24 lead #{$bitrixId} doesn't exist on your portal. Open the lead in Bitrix24 — the ID is in the URL (.../crm/lead/details/<ID>/).",
                    404
                );
            }
            return ApiResponse::error('Bitrix24 fetch failed: ' . $e->getMessage(), 502);
        } catch (\Throwable $e) {
            return ApiResponse::error('Bitrix24 fetch failed: ' . $e->getMessage(), 500);
        }
    }
   public function start(Request $request)
{
    $user = auth()->user();

    if (!$user || !$user->hasRole(['admin', 'super_admin'])) {
        return ApiResponse::error('Unauthorized', 403);
    }

    SyncBitrix24LeadsJob::dispatch($user->id);

    return ApiResponse::success([
        'status' => 'queued'
    ], 'Sync started from last saved position');
}

public function status()
{
    $state = \App\Models\BitrixSyncState::first();

    return response()->json([
        'success' => true,
        'data' => [
            'status' => 'running',
            'progress' => 0,
            'processed' => 0,
            'total' => 0,
            'new_count' => 0,
            'existing_count' => 0,
            'error_count' => 0,
            'cursor' => $state->cursor ?? 0,
        ]
    ]);
}
}

