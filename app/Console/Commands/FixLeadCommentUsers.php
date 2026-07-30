<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LeadComment;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class FixLeadCommentUsers extends Command
{
    protected $signature = 'comments:sync-authors';
    protected $description = 'Fetch author from Bitrix and update comments';

public function handle()
{
    $this->info('🚀 Start syncing comment authors...');

    $webhook = config('bitrix24.webhook_url');

    $count = 0;
    $errors = 0;

    // 🔥 آخر ID اتعالج
    $lastId = cache()->get('comments_sync_last_id', 0);

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

                $response = Http::get($webhook . 'crm.timeline.comment.get', [
                    'id' => $comment->bitrix24_id
                ]);

                if (!$response->ok()) {
                    $errors++;
                    continue;
                }

                $b24Comment = $response->json('result');

                if (!$b24Comment) continue;

                $bitrixAuthorId = $b24Comment['AUTHOR_ID'] ?? null;
                if (!$bitrixAuthorId) continue;

                $user = User::where('bitrix24_id', $bitrixAuthorId)->first();
                if (!$user) continue;

                $comment->update([
                    'user_id' => $user->id
                ]);

                $count++;

                // 🔥 update progress
                $lastId = $comment->id;
                cache()->put('comments_sync_last_id', $lastId);

            } catch (\Throwable $e) {
                $errors++;
            }
        }
    }

    cache()->forget('comments_sync_last_id');

    $this->info("✅ Done. Fixed {$count} comments, Errors: {$errors}");

    return Command::SUCCESS;
}
}