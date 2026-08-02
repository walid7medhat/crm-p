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
                            {--fresh : Ignore cached progress and start from 0}
                            {--total=204797 : Total activity count, used only for the progress bar}';

    protected $description = 'Fast, resumable sync of lead activity authors from Bitrix24';

    protected const CACHE_KEY = 'activities_sync_page';
    protected const MAX_CONSECUTIVE_ERRORS = 5;
    protected const PAGE_SIZE = 50; // Bitrix default page size for crm.activity.list

    public function handle()
    {
        $this->info('🚀 Syncing activities...');

        $webhook = rtrim(config('bitrix24.webhook_url'), '/') . '/';

        $updated = 0;
        $skipped = 0;
        $consecutiveErrors = 0;

        $start = $this->option('fresh') ? 0 : (Cache::get(self::CACHE_KEY) ?? 0);

        if ($start > 0) {
            $this->info("↻ Resuming from offset {$start}");
        }

        // bitrix24_id -> local user id, normalized to string keys to avoid
        // int/string mismatches between DB values and JSON response values
        $users = User::whereNotNull('bitrix24_id')
            ->pluck('id', 'bitrix24_id')
            ->mapWithKeys(fn ($id, $bitrixId) => [(string) $bitrixId => $id])
            ->all();

        $bar = $this->output->createProgressBar((int) $this->option('total'));
        $bar->setProgress($start);
        $bar->start();

        do {
            try {
                $response = Http::timeout(15)
                    ->retry(2, 300)
                    ->get($webhook . 'crm.activity.list', [
                        'start' => $start,
                        'select' => ['ID', 'AUTHOR_ID'],
                        'order' => ['ID' => 'ASC'],
                    ]);

                if (!$response->ok()) {
                    $consecutiveErrors++;
                    $this->newLine();
                    $this->warn("HTTP {$response->status()} at offset {$start} (attempt {$consecutiveErrors})");

                    if ($consecutiveErrors >= self::MAX_CONSECUTIVE_ERRORS) {
                        $this->error('Too many consecutive HTTP errors, aborting. Progress saved — rerun to resume.');
                        break;
                    }

                    usleep(500_000);
                    continue;
                }

                $consecutiveErrors = 0; // reset only on a fully successful request

                $data = $response->json();
                $activities = $data['result'] ?? [];

                if (empty($activities)) {
                    break;
                }

                [$batchUpdated, $batchSkipped] = $this->processBatch($activities, $users);
                $updated += $batchUpdated;
                $skipped += $batchSkipped;

                $bar->advance(count($activities));

                // Bitrix returns 'next' as the offset for the next page, or absent/null when done
                $start = $data['next'] ?? null;

                if ($start !== null) {
                    Cache::put(self::CACHE_KEY, $start, now()->addDays(7));
                }

            } catch (\Throwable $e) {
                $consecutiveErrors++;
                $this->newLine();
                $this->warn("Exception at offset {$start}: {$e->getMessage()} (attempt {$consecutiveErrors})");

                if ($consecutiveErrors >= self::MAX_CONSECUTIVE_ERRORS) {
                    $this->error('Too many consecutive errors, aborting. Progress saved — rerun to resume.');
                    break;
                }

                usleep(500_000);
            }

        } while ($start !== null);

        $bar->finish();
        $this->newLine(2);

        if ($start === null) {
            // Only clear progress if we actually reached the end (not an abort-by-error)
            Cache::forget(self::CACHE_KEY);
            $this->info('✅ Done — reached end of activity list');
        } else {
            $this->warn("⏸ Stopped early at offset {$start}. Rerun the command to resume from here.");
        }

        $this->info("Updated: {$updated}");
        $this->info("Skipped: {$skipped}");

        return Command::SUCCESS;
    }

    /**
     * Bulk-fetch matching activities for this page and update them in as few
     * queries as possible (one SELECT ... WHERE IN, one bulk UPDATE via CASE).
     *
     * @return array{0:int,1:int} [updatedCount, skippedCount]
     */
    protected function processBatch(array $activities, array $users): array
    {
        $updated = 0;
        $skipped = 0;

        $bitrixIds = collect($activities)
            ->pluck('ID')
            ->filter()
            ->values();

        if ($bitrixIds->isEmpty()) {
            return [0, count($activities)];
        }

        // One query for the whole page instead of one query per row
        $existing = LeadActivity::whereIn('bitrix24_id', $bitrixIds)
            ->where(function ($q) {
                $q->where('user_id', 1)->orWhereNull('user_id');
            })
            ->pluck('id', 'bitrix24_id'); // bitrix24_id => local id

        $updates = []; // local_id => user_id

        foreach ($activities as $b24) {
            $bxActivityId = $b24['ID'] ?? null;

            if (!$bxActivityId || !$existing->has($bxActivityId)) {
                $skipped++;
                continue;
            }

            $authorId = isset($b24['AUTHOR_ID']) ? (string) $b24['AUTHOR_ID'] : null;

            if ($authorId && isset($users[$authorId])) {
                $updates[$existing[$bxActivityId]] = $users[$authorId];
                $updated++;
            } else {
                $skipped++;
            }
        }

        if (!empty($updates)) {
            $this->bulkUpdateUserId($updates);
        }

        return [$updated, $skipped];
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