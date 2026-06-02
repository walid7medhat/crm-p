<?php

namespace App\Jobs;

use App\Models\BitrixSyncState;
use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class SyncBitrix24LeadsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 2;

    public function __construct(
        public int $userId,
        public bool $skipExisting = false,
    ) {}

    public function handle(): void
    {
        // The page-walker chains itself for the whole sync; keep its footprint
        // flat by not retaining the query log across the run.
        DB::connection()->disableQueryLog();

        $state = BitrixSyncState::firstOrCreate(
            ['key' => 'global_sync'],
            ['status' => 'idle', 'cursor' => 0],
        );

        if (in_array($state->status, ['cancelled', 'paused'], true)) {
            return;
        }

        if ($state->status !== 'running') {
            $state->update([
                'status' => 'running',
                'started_at' => now(),
                'finished_at' => null,
                'last_error' => null,
                'user_id' => $this->userId,
                'skip_existing' => $this->skipExisting,
            ]);
        }

        $client = new \App\Services\Bitrix24\Bitrix24Client();

        $cursor = (int) ($state->cursor ?? 0);

        $page = $client->listLeads($cursor);

        $b24Leads = $page['result'] ?? [];
        $next     = $page['next'] ?? null;
        $total    = (int) ($page['total'] ?? 0);

        // Persist the Bitrix24 grand total so the UI can show "x / total"
        // instead of "x / —". Bitrix returns it on every page; keep the
        // largest value we've seen in case an early page reported 0.
        if ($total > (int) ($state->total ?? 0)) {
            $state->update(['total' => $total]);
        }

        // check existing (optional optimization)
        $existingMap = [];
        if ($this->skipExisting && !empty($b24Leads)) {
            $ids = array_column($b24Leads, 'ID');

            $existingMap = Lead::whereIn('bitrix24_id', $ids)
                ->pluck('id', 'bitrix24_id')
                ->toArray();
        }

        $processed = 0;

        foreach ($b24Leads as $b24) {

            $b24Id = (int) ($b24['ID'] ?? 0);

            if ($this->skipExisting && isset($existingMap[$b24Id])) {
                continue;
            }

            // 🔥 هنا أهم تعديل
            // ProcessBitrixLeadJob::dispatch($b24, $this->userId);
ProcessBitrixLeadJob::dispatch($b24, $this->userId)
    ->onQueue('bitrix');
            $processed++;
        }

        // update state
        $state->increment('processed', $processed);
        $state->update([
            'cursor' => $next ?? $cursor,
        ]);

        if ($next === null) {
            $state->update([
                'status' => 'done',
                'finished_at' => now(),
            ]);
            return;
        }

        // 🔥 chain next page. No artificial delay — the Bitrix24 client already
        // throttles/back-offs on 429. Stays on the default queue (NOT 'bitrix')
        // so page-walking isn't stuck behind the thousands of per-lead jobs;
        // the two queues drain in parallel. A fixed 2s/page delay just added
        // ~20 min of idle over a full 30k-lead (≈600-page) sync.
        self::dispatch($this->userId, $this->skipExisting);

    }

    public function failed(\Throwable $e): void
    {
        BitrixSyncState::where('key', 'global_sync')->update([
            'status' => 'failed',
            'last_error' => $e->getMessage(),
            'finished_at' => now(),
        ]);
    }
}