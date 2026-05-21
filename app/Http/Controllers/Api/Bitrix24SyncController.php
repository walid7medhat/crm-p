<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Bitrix24\Bitrix24Client;
use App\Services\Bitrix24\Bitrix24Exception;
use App\Services\Bitrix24\Bitrix24LeadImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class Bitrix24SyncController extends Controller
{
    /**
     * Synchronous batched sync. Frontend calls this repeatedly with the
     * `next` cursor returned by the previous response, until `done` is true.
     *
     *   POST /api/leads/bitrix24/sync { start?: int, batch_size?: int (max 50) }
     *   -> { imported_in_batch, errors, next (int|null), total, done }
     */
    public function syncBatch(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user || !$user->hasRole(['admin', 'super_admin'])) {
            return ApiResponse::error('Only admins can sync from Bitrix24', 403);
        }

        $request->validate([
            'start'      => 'nullable|integer|min:0',
            'batch_size' => 'nullable|integer|min:1|max:50',
        ]);

        $batchSize = (int) ($request->input('batch_size') ?? config('bitrix24.batch_size', 25));
        $start = (int) ($request->input('start') ?? 0);

        try {
            $client   = new Bitrix24Client();
            $importer = new Bitrix24LeadImporter($client, $user->id);

            $page = $client->listLeads($start);
            $b24Leads = $page['result'] ?? [];
            $total = (int) ($page['total'] ?? 0);

            $sliced = array_slice($b24Leads, 0, $batchSize);

            $imported = 0;
            $errors = [];
            foreach ($sliced as $b24) {
                try {
                    $importer->importOne($b24);
                    $imported++;
                } catch (\Throwable $e) {
                    $errors[] = [
                        'bitrix24_id' => $b24['ID'] ?? null,
                        'error'       => $e->getMessage(),
                    ];
                }
            }

            // Advance the cursor by however many leads we actually processed
            // from the page. Empty page or processed-less-than-batch on a final
            // page both mean "no more rows to fetch".
            $advanced = count($sliced);
            $nextCursor = ($advanced > 0) ? ($start + $advanced) : null;
            if ($nextCursor !== null && $total > 0 && $nextCursor >= $total) {
                $nextCursor = null;
            }
            $done = $nextCursor === null;

            return ApiResponse::success([
                'imported_in_batch' => $imported,
                'errors'            => $errors,
                'next'              => $nextCursor,
                'total'             => $total,
                'done'              => $done,
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
            $lead = $importer->importOne($b24);

            return ApiResponse::success([
                'lead_id'     => $lead->id,
                'bitrix24_id' => $bitrixId,
            ], 'Lead imported from Bitrix24');
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
}
