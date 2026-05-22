<?php

namespace App\Jobs;

use App\Models\BitrixSyncState;
use App\Models\Lead;
use App\Services\Bitrix24\Bitrix24Client;
use App\Services\Bitrix24\Bitrix24LeadImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Self-chaining sync job: processes ONE Bitrix24 page (≤50 leads) per run, saves
 * progress to BitrixSyncState, then dispatches itself for the next cursor. Each
 * run finishes in seconds, survives worker restarts, and 30k+ leads can be
 * processed without ever exceeding a single worker's timeout.
 */
class SyncBitrix24LeadsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Hard timeout per page-run (one page = ≤50 leads + their sub-fetches). */
    public int $timeout = 600;

    /** Retry once on transient Bitrix24 / network errors. */
    public int $tries = 2;
    public int $backoff = 30;

    public function __construct(
        public int $userId,
        public bool $skipExisting = false,
    ) {}

    public function handle(): void
    {
        $state = BitrixSyncState::firstOrCreate(
            ['key' => 'global_sync'],
            ['status' => 'idle', 'cursor' => 0],
        );

        // Cancel gate: admin can pause by writing 'cancelled' to the row.
        if (in_array($state->status, ['cancelled', 'paused'], true)) {
            return;
        }

        // First page of this session: mark running.
        if ($state->status !== 'running') {
            $state->forceFill([
                'status'        => 'running',
                'started_at'    => now(),
                'finished_at'   => null,
                'last_error'    => null,
                'user_id'       => $this->userId,
                'skip_existing' => $this->skipExisting,
            ])->save();
        }

        // Restore auth context so LeadHistoryHelper::log gets the right user_id
        // (auth()->id() is null inside a queued job by default).
        // Auth::loginUsingId($this->userId);

        $client   = new Bitrix24Client();
        $importer = new Bitrix24LeadImporter($client, $this->userId);

        $cursor = (int) ($state->cursor ?? 0);

        try {
            $page = $client->listLeads($cursor);
        } catch (\Throwable $e) {
            $state->forceFill([
                'status'      => 'failed',
                'last_error'  => $e->getMessage(),
                'finished_at' => now(),
            ])->save();
            throw $e;
        }

        $b24Leads = $page['result'] ?? [];
        $total    = (int) ($page['total'] ?? $state->total ?? 0);
        $next     = $page['next'] ?? null;

        // Pre-resolve existing IDs in this page (cheap one-shot whereIn).
        $existingMap = [];
        if ($this->skipExisting && !empty($b24Leads)) {
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

        $newCount = 0;
        $existingCount = 0;
        $errorCount = 0;
        $processedInPage = 0;

        foreach ($b24Leads as $b24) {
            $b24Id = (int) ($b24['ID'] ?? 0);

            if ($this->skipExisting && isset($existingMap[$b24Id])) {
                $existingCount++;
                $processedInPage++;
                continue;
            }

            try {
                $r = $importer->importOne($b24);
                if ($r['created']) {
                    $newCount++;
                } else {
                    $existingCount++;
                }
            } catch (\Throwable $e) {
                $errorCount++;
                Log::error('Bitrix24 sync: importOne failed', [
                    'bitrix24_id' => $b24Id,
                    'error'       => $e->getMessage(),
                ]);
            }

            $processedInPage++;

            // Bounded memory: nudge GC every 25 leads.
            if ($processedInPage % 25 === 0 && function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }

        // Persist progress for this page.
        $state->forceFill([
            'cursor'         => $next ?? $cursor,
            'total'          => $total,
            'processed'      => (int) $state->processed + $processedInPage,
            'new_count'      => (int) $state->new_count + $newCount,
            'existing_count' => (int) $state->existing_count + $existingCount,
            'error_count'    => (int) $state->error_count + $errorCount,
        ])->save();

        if ($next === null) {
            // No more pages — mark done. (Cursor stays at the last value so the
            // controller's start() endpoint can reset it cleanly on next run.)
            $state->forceFill([
                'status'      => 'done',
                'finished_at' => now(),
            ])->save();
            return;
        }

        // Self-chain — next page runs as a fresh queue job. This is what keeps
        // each run short and crash-resilient: if the worker dies mid-sync, the
        // saved cursor + progress survive and the next dispatch picks up.
        self::dispatch($this->userId, $this->skipExisting);
    }

    /** Final-attempt failure handler — surface the error in the UI state row. */
    public function failed(\Throwable $exception): void
    {
        $state = BitrixSyncState::where('key', 'global_sync')->first();
        if ($state) {
            $state->forceFill([
                'status'      => 'failed',
                'last_error'  => $exception->getMessage(),
                'finished_at' => now(),
            ])->save();
        }
    }
}
