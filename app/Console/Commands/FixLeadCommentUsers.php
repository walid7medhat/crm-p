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

        LeadComment::whereNotNull('bitrix24_id')
            ->whereNull('user_id')
            ->chunk(50, function ($comments) use ($webhook, &$count) {

                foreach ($comments as $comment) {

                    // 🟡 call Bitrix API
                    $response = Http::get($webhook . 'crm.timeline.comment.get', [
                        'id' => $comment->bitrix24_id
                    ]);

                    if (!$response->ok()) {
                        $this->error("❌ API error for comment {$comment->id}");
                        continue;
                    }

                    $data = $response->json();

                    $b24Comment = $data['result'] ?? null;

                    if (!$b24Comment) {
                        continue;
                    }

                    $bitrixAuthorId = $b24Comment['AUTHOR_ID'] ?? null;

                    if (!$bitrixAuthorId) {
                        continue;
                    }

                    // 🟢 map user
                    $user = User::where('bitrix24_id', $bitrixAuthorId)->first();

                    if ($user) {
                        $comment->update([
                            'user_id' => $user->id
                        ]);

                        $count++;
                        $this->line("✔ Fixed comment ID {$comment->id}");
                    }
                }
            });

        $this->info("✅ Done. Fixed {$count} comments");

        return Command::SUCCESS;
    }
}