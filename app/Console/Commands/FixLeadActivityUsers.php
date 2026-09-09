<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LeadActivity;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FixLeadActivityUsers extends Command
{
    protected $signature = 'activities:sync-authors-super-fast
                            {--fresh : Ignore cached progress and start from 0}';

    protected $description = 'Fast, resumable sync of lead activity authors from Bitrix24 (only rows still stuck on user_id = 1)';

    protected const CACHE_KEY = 'activities_sync_last_id';
    protected const MAX_CONSECUTIVE_ERRORS = 5;
    protected const PAGE_SIZE = 50; // Bitrix default page size for crm.activity.list

    public function handle()
    {
        $this->info('🚀 Syncing activities stuck on user_id = 1...');

        $webhook = rtrim(config('bitrix24.webhook_url'), '/') . '/';

        $updated = 0;
        $skipped = 0;
        $consecutiveErrors = 0;
        $aborted = false;

        $lastId = $this->option('fresh') ? 0 : (Cache::get(self::CACHE_KEY) ?? 0);

        if ($lastId > 0) {
            $this->info("↻ Resuming after local activity id {$lastId}");
        }

        // bitrix24_id -> local user id, normalized to string keys to avoid
        // int/string mismatches between DB values and JSON response values
        $users = User::whereNotNull('bitrix24_id')
            ->pluck('id', 'bitrix24_id')
            ->mapWithKeys(fn ($id, $bitrixId) => [(string) $bitrixId => $id])
            ->all();

        $query = LeadActivity::where('user_id', 1)
            ->whereNotNull('bitrix24_id')
            ->where('id', '>', $lastId);

        $bar = $this->output->createProgressBar($query->count());
        $bar->start();

        $query->orderBy('id')->chunkById(self::PAGE_SIZE, function ($chunk) use (
            $webhook, $users, $bar, &$updated, &$skipped, &$consecutiveErrors, &$aborted
        ) {
            if ($aborted) {
                return false;
            }

            $bitrixIds = $chunk->pluck('bitrix24_id')->filter()->values();

            if ($bitrixIds->isEmpty()) {
                $skipped += $chunk->count();
                $bar->advance($chunk->count());
                return;
            }

            try {
                $response = Http::timeout(15)
                    ->retry(2, 300)
                    ->get($webhook . 'crm.activity.list', [
                        'filter' => ['@ID' => $bitrixIds->all()],
                        'select' => ['ID', 'AUTHOR_ID'],
                    ]);

                if (!$response->ok()) {
                    $consecutiveErrors++;
                    $this->newLine();
                    $this->warn("HTTP {$response->status()} for batch starting at local id {$chunk->first()->id} (attempt {$consecutiveErrors})");

                    if ($consecutiveErrors >= self::MAX_CONSECUTIVE_ERRORS) {
                        $this->error('Too many consecutive HTTP errors, aborting. Progress saved — rerun to resume.');
                        $aborted = true;
                        return false;
                    }

                    $skipped += $chunk->count();
                    $bar->advance($chunk->count());
                    return;
                }

                $consecutiveErrors = 0;

                $remoteById = collect($response->json('result', []))->keyBy('ID');
                $updates = [];

                foreach ($chunk as $activity) {
                    $remote = $remoteById->get((string) $activity->bitrix24_id);
                    $authorId = $remote['AUTHOR_ID'] ?? null;

                    if ($authorId !== null && isset($users[(string) $authorId])) {
                        $updates[$activity->id] = $users[(string) $authorId];
                        $updated++;
                    } else {
                        $skipped++;
                    }
                }

                if (!empty($updates)) {
                    $this->bulkUpdateUserId($updates);
                }

                Cache::put(self::CACHE_KEY, $chunk->last()->id, now()->addDays(7));
                $bar->advance($chunk->count());

            } catch (\Throwable $e) {
                $consecutiveErrors++;
                $this->newLine();
                $this->warn("Exception for batch starting at local id {$chunk->first()->id}: {$e->getMessage()} (attempt {$consecutiveErrors})");

                if ($consecutiveErrors >= self::MAX_CONSECUTIVE_ERRORS) {
                    $this->error('Too many consecutive errors, aborting. Progress saved — rerun to resume.');
                    $aborted = true;
                    return false;
                }

                $skipped += $chunk->count();
                $bar->advance($chunk->count());
            }
        });

        $bar->finish();
        $this->newLine(2);

        if (!$aborted) {
            Cache::forget(self::CACHE_KEY);
            $this->info('✅ Done — no more activities stuck on user_id = 1');
        } else {
            $this->warn('⏸ Stopped early. Rerun the command to resume from here.');
        }

        $this->info("Updated: {$updated}");
        $this->info("Skipped: {$skipped}");

        return Command::SUCCESS;
    }

    /**
     * Bulk update user_id for many rows in a single query using CASE WHEN,
     * instead of N separate UPDATE statements.
     *
     * @param array<int,int> $updates local_activity_id => user_id
     */
    protected function bulkUpdateUserId(array $updates): void
    {
        $ids = array_keys($updates);

        $case = 'CASE id ';
        $bindings = [];

        foreach ($updates as $id => $userId) {
            $case .= 'WHEN ? THEN ? ';
            $bindings[] = $id;
            $bindings[] = $userId;
        }
        $case .= 'END';

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $bindings = array_merge($bindings, $ids);

        DB::update(
            "UPDATE lead_activities SET user_id = {$case} WHERE id IN ({$placeholders})",
            $bindings
        );
    }
}