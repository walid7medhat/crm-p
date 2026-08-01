<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LeadComment;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class FixLeadCommentUsers extends Command
{
    protected $signature = 'comments:sync-authors';
    protected $description = 'Fetch author from Bitrix and update comments';

    public function handle()
    {
        $this->info('🚀 Start syncing comment authors...');
        \Log::info('comments:sync-authors START ' . now());

        $webhook = config('bitrix24.webhook_url');

        $updated = 0;
        $skipped = 0;
        $errors = 0;

        $lastId = Cache::get('comments_sync_last_id', 0);

        while (true) {

            $comments = LeadComment::whereNotNull('bitrix24_id')
                ->where(function ($q) {
                    $q->where('user_id', 1)
                      ->orWhereNull('user_id');
                })
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit(50)
                ->get();

            if ($comments->isEmpty()) {
                break;
            }

            foreach ($comments as $comment) {

                $status = 'SKIPPED';

                try {
                    $this->line("➡️ Checking comment {$comment->id}");

                    $response = Http::timeout(10)->get(
                        $webhook . 'crm.timeline.comment.get',
                        ['id' => $comment->bitrix24_id]
                    );

                    if ($response->ok()) {

                        $b24Comment = $response->json('result');

                        if ($b24Comment) {
                            $bitrixAuthorId = $b24Comment['AUTHOR_ID'] ?? null;

                            if ($bitrixAuthorId) {
                                $user = User::where('bitrix24_id', $bitrixAuthorId)->first();

                                if ($user) {
                                    $comment->update([
                                        'user_id' => $user->id
                                    ]);

                                    $updated++;
                                    $status = 'UPDATED';
                                } else {
                                    $skipped++;
                                    $status = 'NO USER';
                                }
                            } else {
                                $skipped++;
                                $status = 'NO AUTHOR';
                            }
                        } else {
                            $skipped++;
                            $status = 'EMPTY RESPONSE';
                        }

                    } else {
                        $errors++;
                        $status = 'API ERROR';
                    }

                } catch (\Throwable $e) {
                    $errors++;
                    $status = 'EXCEPTION';
                }

                // 🔥 log لكل كومنت
                $this->line("   ➜ Status: {$status}");

                // 🔥 تحديث التقدم في كل الحالات
                $lastId = $comment->id;
                Cache::put('comments_sync_last_id', $lastId);
            }
        }

        Cache::forget('comments_sync_last_id');

        $this->info("✅ Done.");
        $this->info("Updated: {$updated}");
        $this->info("Skipped: {$skipped}");
        $this->info("Errors: {$errors}");

        \Log::info("comments:sync-authors DONE", [
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors
        ]);

        return Command::SUCCESS;
    }
}