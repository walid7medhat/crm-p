<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Jobs\SyncBitrix24LeadsJob;
use App\Models\BitrixSyncState;
use App\Models\Lead;
use App\Services\Bitrix24\Bitrix24Client;
use App\Services\Bitrix24\Bitrix24Exception;
use App\Services\Bitrix24\Bitrix24LeadImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class Bitrix24SyncController extends Controller
{
    /**
     * Synchronous batched sync (legacy, kept for compatibility / one-off small ranges).
     * For 30k-lead full sync prefer POST /api/leads/bitrix24/start-queue.
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

            $page = $client->listLeads($start);
            $b24Leads = $page['result'] ?? [];
            $total    = (int) ($page['total'] ?? 0);

            $existingMap = [];
            if ($skipExisting && !empty($b24Leads)) {
                $ids = array_filter(
                    array_map(fn ($l) => (int) ($l['ID'] ?? 0), $b24Leads),
                    fn ($v) => $v > 0
                );
                if (!empty($ids)) {
                    $existingMap = Lead::whereIn('bitrix24_id', $ids)
                        ->pluck('id', 'bitrix24_id')
                        ->all();
                }
            }

            $imported = 0;
            $newLeads = [];
            $existingLeads = [];
            $errors = [];

            foreach ($b24Leads as $b24) {
                $b24Id = (int) ($b24['ID'] ?? 0);

                if ($skipExisting && isset($existingMap[$b24Id])) {
                    $existingLeads[] = [
                        'lead_id'     => (int) $existingMap[$b24Id],
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
                        'bitrix24_id' => $b24Id ?: null,
                        'error'       => $e->getMessage(),
                    ];
                }
            }

            // Trust Bitrix24's own paging cursor for next.
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
                'lead_id'         => $r['lead']->id,
                'bitrix24_id'     => $bitrixId,
                'created'         => $r['created'],
                'already_existed' => !$r['created'],
            ], $r['created']
                ? 'Lead imported from Bitrix24'
                : 'Lead already existed locally — refreshed timeline and stage.');
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

    /**
     * Queue a full Bitrix24 sync. Resets progress, dispatches the self-chaining
     * job which will process one Bitrix24 page (~50 leads) per run.
     *   POST /api/leads/bitrix24/start-queue { skip_existing?: bool }
     */
    public function start(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user || !$user->hasRole(['admin', 'super_admin'])) {
            return ApiResponse::error('Unauthorized', 403);
        }

        $request->validate([
            'skip_existing' => 'nullable|boolean',
            'restart'       => 'nullable|boolean',
        ]);
        $skipExisting = (bool) $request->input('skip_existing', false);

        $state = BitrixSyncState::firstOrCreate(
            ['key' => 'global_sync'],
            ['status' => 'idle', 'cursor' => 0],
        );

        if ($state->status === 'running') {
            return ApiResponse::error('A sync is already running. Wait for it to finish (or cancel it first).', 409);
        }

        // Resume from the saved cursor by DEFAULT — the sync continues from where
        // it stopped (done / cancelled / failed / paused / idle all keep their
        // cursor + counters), and a manual cursor edit in the DB is honoured.
        // Only an explicit `restart` flag wipes progress back to 0. (First-ever
        // run resumes too, but its cursor is already 0, so it starts from the top.)
        $restart = (bool) $request->input('restart', false);
        $resume  = !$restart;

        if ($restart) {
            $state->forceFill([
                'cursor'         => 0,
                'total'          => 0,
                'processed'      => 0,
                'new_count'      => 0,
                'existing_count' => 0,
                'error_count'    => 0,
                'started_at'     => null,
                'finished_at'    => null,
            ])->save();
        }

        // Flip status to 'idle' so the job's cancel-gate doesn't trip; clear
        // last_error either way; remember which user kicked it off + the flag.
        $state->forceFill([
            'status'        => 'idle',
            'last_error'    => null,
            'finished_at'   => null,
            'user_id'       => $user->id,
            'skip_existing' => $skipExisting,
        ])->save();

        // Fetch the Bitrix24 grand total up-front (one quick page call) and store
        // it now, so the UI shows "0 / total" immediately — without waiting for a
        // queue worker to run the first chunk. On resume we keep whatever's larger.
        try {
            $page  = (new Bitrix24Client())->listLeads((int) $state->cursor);
            $total = (int) ($page['total'] ?? 0);
            if ($total > (int) ($state->total ?? 0)) {
                $state->forceFill(['total' => $total])->save();
            }
        } catch (\Throwable $e) {
            // Non-fatal: the running job will fill in the total on its first chunk.
        }

        SyncBitrix24LeadsJob::dispatch($user->id, $skipExisting);

        return ApiResponse::success([
            'status'        => 'queued',
            'resumed'       => $resume,
            'cursor'        => (int) $state->cursor,
            'skip_existing' => $skipExisting,
        ], $resume
            ? "Resuming sync from cursor {$state->cursor}."
            : 'Sync queued — starting from the beginning.');
    }

    /**
     * Live sync state for the UI poller. Reads BitrixSyncState — no hardcoded
     * values; numbers reflect the running job's progress.
     *   GET /api/bitrix24/queue-status
     */
    public function status(): JsonResponse
    {
        $state = BitrixSyncState::where('key', 'global_sync')->first();

        if (!$state) {
            return ApiResponse::success([
                'status'         => 'idle',
                'progress'       => 0,
                'processed'      => 0,
                'total'          => 0,
                'new_count'      => 0,
                'existing_count' => 0,
                'error_count'    => 0,
                'cursor'         => 0,
                'last_error'     => null,
                'started_at'     => null,
                'finished_at'    => null,
                'skip_existing'  => false,
            ], 'No sync has been started yet');
        }

        $total     = (int) $state->total;
        $processed = (int) $state->processed;
        $progress  = $total > 0
            ? (int) min(100, floor(($processed / $total) * 100))
            : ($state->status === 'done' ? 100 : 0);

        return ApiResponse::success([
            'status'         => $state->status ?: 'idle',
            'progress'       => $progress,
            'processed'      => $processed,
            'total'          => $total,
            'new_count'      => (int) $state->new_count,
            'existing_count' => (int) $state->existing_count,
            'error_count'    => (int) $state->error_count,
            'cursor'         => (int) $state->cursor,
            'last_error'     => $state->last_error,
            'started_at'     => optional($state->started_at)->toIso8601String(),
            'finished_at'    => optional($state->finished_at)->toIso8601String(),
            'skip_existing'  => (bool) $state->skip_existing,
        ], 'Sync status');
    }

    /**
     * Cancel a running sync — flips status to 'cancelled' so the next
     * self-chained job run sees it and exits cleanly.
     *   POST /api/leads/bitrix24/cancel-queue
     */
    public function cancel(): JsonResponse
    {
        $user = auth()->user();
        if (!$user || !$user->hasRole(['admin', 'super_admin'])) {
            return ApiResponse::error('Unauthorized', 403);
        }

        $state = BitrixSyncState::where('key', 'global_sync')->first();
        if (!$state || $state->status !== 'running') {
            return ApiResponse::error('No running sync to cancel.', 409);
        }

        $state->forceFill([
            'status'      => 'cancelled',
            'finished_at' => now(),
        ])->save();

        return ApiResponse::success(null, 'Sync cancellation requested — will stop after the current chunk.');
    }
}