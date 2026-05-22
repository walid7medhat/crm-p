<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Jobs\SyncBitrix24LeadsJob;
use App\Models\BitrixSyncShard;
use App\Models\BitrixSyncState;
use App\Models\Lead;
use App\Services\Bitrix24\Bitrix24Client;
use App\Services\Bitrix24\Bitrix24SyncOrchestrator;
use App\Services\Bitrix24\Bitrix24Exception;
use App\Services\Bitrix24\Bitrix24LeadImporter;
use App\Support\Bitrix24Schema;
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
        ]);
        $skipExisting = (bool) $request->input('skip_existing', false);

        $state = BitrixSyncState::firstOrCreate(
            ['key' => 'global_sync'],
            ['status' => 'idle', 'cursor' => 0],
        );

        if ($state->status === 'running') {
            return ApiResponse::error('A sync is already running. Wait for it to finish (or cancel it first).', 409);
        }

        // Decide: resume (keep cursor + counters) vs fresh start (reset everything).
        //   done   → fresh start, the previous sync already completed.
        //   cancelled / failed / paused → resume from saved cursor + counters.
        //   idle (never run, or just reset) → starts from cursor 0 anyway.
        $resume = in_array($state->status, ['cancelled', 'failed', 'paused'], true);

        if (!$resume) {
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

        $state->forceFill([
            'last_error'    => null,
            'finished_at'   => null,
            'user_id'       => $user->id,
            'skip_existing' => $skipExisting,
        ])->save();

        try {
            if (
                $resume
                && $state->sync_mode === 'parallel'
                && Bitrix24Schema::shardsTableExists()
                && BitrixSyncShard::incomplete()->exists()
            ) {
                Bitrix24SyncOrchestrator::resumeShards($user->id, $skipExisting);
            } elseif (!$resume && Bitrix24SyncOrchestrator::shouldUseParallelShards()) {
                Bitrix24SyncOrchestrator::startFresh($user->id, $skipExisting);
            } else {
                SyncBitrix24LeadsJob::dispatch($user->id, $skipExisting);
            }
        } catch (\Throwable $e) {
            $state->forceFill([
                'status'     => 'failed',
                'last_error' => 'Failed to queue sync: ' . $e->getMessage(),
            ])->save();

            return ApiResponse::error(
                'Failed to queue Bitrix24 sync. Check queue driver and run: php artisan bitrix24:doctor',
                500
            );
        }

        // Mark running immediately so the UI poller shows progress (do not leave as idle).
        $state->forceFill([
            'status'     => 'running',
            'started_at' => $state->started_at ?? now(),
        ])->save();

        return ApiResponse::success([
            'status'          => 'queued',
            'resumed'         => $resume,
            'cursor'          => (int) $state->cursor,
            'skip_existing'   => $skipExisting,
            'parallel_shards' => (int) config('bitrix24.parallel_shards', 1),
            'sync_mode'       => $resume
                ? ($state->sync_mode ?: 'sequential')
                : (Bitrix24SyncOrchestrator::shouldUseParallelShards() ? 'parallel' : 'sequential'),
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
                'status'           => 'idle',
                'progress'         => 0,
                'processed'        => 0,
                'total'            => 0,
                'new_count'        => 0,
                'existing_count'   => 0,
                'error_count'      => 0,
                'cursor'           => 0,
                'last_error'       => null,
                'started_at'       => null,
                'finished_at'      => null,
                'skip_existing'    => false,
                'leads_per_sec'    => 0,
                'eta_seconds'      => null,
                'parallel_shards'  => 1,
                'shards_completed' => 0,
                'sync_mode'        => 'sequential',
            ], 'No sync has been started yet');
        }

        $total     = (int) $state->total;
        $processed = (int) $state->processed;
        $progress  = $total > 0
            ? (int) min(100, floor(($processed / $total) * 100))
            : ($state->status === 'done' ? 100 : 0);

        return ApiResponse::success([
            'status'            => $state->status ?: 'idle',
            'progress'          => $progress,
            'processed'         => $processed,
            'total'             => $total,
            'new_count'         => (int) $state->new_count,
            'existing_count'    => (int) $state->existing_count,
            'error_count'       => (int) $state->error_count,
            'cursor'            => (int) $state->cursor,
            'last_error'        => $state->last_error,
            'started_at'        => optional($state->started_at)->toIso8601String(),
            'finished_at'       => optional($state->finished_at)->toIso8601String(),
            'updated_at'        => optional($state->updated_at)->toIso8601String(),
            'skip_existing'     => (bool) $state->skip_existing,
            'leads_per_sec'     => (float) ($state->leads_per_sec ?? 0),
            'eta_seconds'       => $state->eta_seconds,
            'parallel_shards'   => (int) ($state->parallel_shards ?? 1),
            'shards_completed'  => (int) ($state->shards_completed ?? 0),
            'sync_mode'         => $state->sync_mode ?? 'sequential',
        ], 'Sync status')->header('Cache-Control', 'no-store, no-cache, must-revalidate');
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
        if (!$state) {
            return ApiResponse::error('No sync state found.', 404);
        }

        if (!in_array($state->status, ['running', 'paused'], true)) {
            return ApiResponse::error('No running sync to cancel.', 409);
        }

        try {
            $state->forceFill([
                'status'      => 'cancelled',
                'finished_at' => now(),
            ])->save();

            Bitrix24SyncOrchestrator::cancelShards();
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to cancel sync: ' . $e->getMessage(), 500);
        }

        return ApiResponse::success(null, 'Sync cancellation requested — will stop after the current chunk.');
    }

    /**
     * Force-reset stuck sync UI state (admin). Clears running flag without deleting leads.
     *   POST /api/leads/bitrix24/reset-queue
     */
    public function reset(): JsonResponse
    {
        $user = auth()->user();
        if (!$user || !$user->hasRole(['admin', 'super_admin'])) {
            return ApiResponse::error('Unauthorized', 403);
        }

        try {
            $state = BitrixSyncState::where('key', 'global_sync')->first();
            if ($state) {
                $state->forceFill([
                    'status'      => 'cancelled',
                    'finished_at' => now(),
                    'last_error'  => null,
                    'sync_mode'   => 'sequential',
                ])->save();
            }

            Bitrix24SyncOrchestrator::cancelShards();
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to reset sync: ' . $e->getMessage(), 500);
        }

        $hint = Bitrix24Schema::shardsTableExists()
            ? null
            : ' Run php artisan migrate on the server to enable full Bitrix24 sync features.';

        return ApiResponse::success(null, 'Sync state reset. You can start a fresh sync.' . ($hint ?? ''));
    }
}
