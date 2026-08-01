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

        $count = 0;
        $errors = 0;

        // 🔥 آخر ID اتعالج
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

                                    $count++;
                                }
                            }
                        }
                    } else {
                        $errors++;
                        \Log::warning('Bitrix API failed', [
                            'comment_id' => $comment->id,
                            'status' => $response->status()
                        ]);
                    }

                } catch (\Throwable $e) {
                    $errors++;
                    \Log::error('Error syncing comment', [
                        'comment_id' => $comment->id,
                        'error' => $e->getMessage()
                    ]);
                }

                // 🔥 أهم نقطة: نحفظ التقدم في كل الحالات
                $lastId = $comment->id;
                Cache::put('comments_sync_last_id', $lastId);
            }
        }

        // خلص كل حاجة
        Cache::forget('comments_sync_last_id');

        $this->info("✅ Done. Fixed {$count} comments, Errors: {$errors}");
        \Log::info("comments:sync-authors DONE. Fixed {$count}, Errors {$errors}");

        return Command::SUCCESS;
    }
}