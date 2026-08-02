<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LeadActivity;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class FixLeadActivityUsers extends Command
{
    protected $signature = 'activities:sync-authors-fast';
    protected $description = 'Ultra fast sync LeadActivity authors with resume support';

    public function handle()
    {
        $this->info('🚀 Fast syncing activities (with resume)...');

        $webhook = config('bitrix24.webhook_url');

        $updated = 0;
        $skipped = 0;
        $errors = 0;

        // 🔥 آخر ID
        $lastId = Cache::get('activities_sync_last_id', 0);

        // 🔥 cache كل اليوزر مرة واحدة
        $users = User::whereNotNull('bitrix24_id')
            ->pluck('id', 'bitrix24_id')
            ->toArray();

        // 🔥 total بعد lastId
        $total = LeadActivity::whereNotNull('bitrix24_id')
            ->where(function ($q) {
                $q->where('user_id', 1)
                  ->orWhereNull('user_id');
            })
            ->where('id', '>', $lastId)
            ->count();

        if ($total === 0) {
            $this->info('✅ No activities to process.');
            return Command::SUCCESS;
        }

        // 🔥 progress bar
        $bar = $this->output->createProgressBar($total);
        $bar->setFormat("%current%/%max% [%bar%] %percent:3s%% | U:%message%");
        $bar->setMessage("0 | S:0 | E:0");
        $bar->start();

        LeadActivity::whereNotNull('bitrix24_id')
            ->where(function ($q) {
                $q->where('user_id', 1)
                  ->orWhereNull('user_id');
            })
            ->where('id', '>', $lastId)
            ->orderBy('id')
            ->chunkById(200, function ($activities) use (
                $webhook,
                &$updated,
                &$skipped,
                &$errors,
                &$users,
                &$lastId,
                $bar
            ) {

                foreach ($activities as $activity) {

                    try {
                        $response = Http::timeout(8)
                            ->retry(2, 200)
                            ->get($webhook . 'crm.activity.get', [
                                'id' => $activity->bitrix24_id
                            ]);

                        if ($response->ok()) {

                            $b24 = $response->json('result');

                            if ($b24 && !empty($b24['AUTHOR_ID'])) {

                                $bitrixId = $b24['AUTHOR_ID'];

                                // 🔥 من cache بدل DB
                                if (isset($users[$bitrixId])) {
                                    $activity->update([
                                        'user_id' => $users[$bitrixId]
                                    ]);
                                    $updated++;
                                } else {
                                    $skipped++;
                                }

                            } else {
                                $skipped++;
                            }

                        } else {
                            $errors++;
                        }

                    } catch (\Throwable $e) {
                        $errors++;
                    }

                    // 🔥 حفظ التقدم بعد كل record
                    $lastId = $activity->id;
                    Cache::put('activities_sync_last_id', $lastId);

                    // 🔥 تحديث progress
                    $bar->setMessage("{$updated} | S:{$skipped} | E:{$errors}");
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);

        // 🔥 خلصنا → نمسح الكاش
        Cache::forget('activities_sync_last_id');

        $this->info("✅ Done");
        $this->info("Updated: {$updated}");
        $this->info("Skipped: {$skipped}");
        $this->info("Errors: {$errors}");

        return Command::SUCCESS;
    }
}