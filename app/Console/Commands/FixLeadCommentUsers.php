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

            LeadComment::whereNotNull('bitrix24_id')
                ->where(function ($q) {
                    $q->where('user_id', 1)
                    ->orWhereNull('user_id');
                })
                ->chunk(50, function ($comments) use ($webhook, &$count, &$errors) {

                    foreach ($comments as $comment) {

                        try {

                            $this->line("➡️ Checking comment {$comment->id}");

                            // 🟡 call Bitrix API
                            $response = Http::get($webhook . 'crm.timeline.comment.get', [
                                'id' => $comment->bitrix24_id
                            ]);

                            if (!$response->ok()) {
                                $this->error("❌ API error for comment {$comment->id}");
                                $errors++;
                                continue;
                            }

                            $data = $response->json();
                            $b24Comment = $data['result'] ?? null;

                            if (!$b24Comment) {
                                $this->warn("⚠️ No result for comment {$comment->id}");
                                continue;
                            }

                            $bitrixAuthorId = $b24Comment['AUTHOR_ID'] ?? null;

                            if (!$bitrixAuthorId) {
                                $this->warn("⚠️ No AUTHOR_ID for comment {$comment->id}");
                                continue;
                            }

                            // 🟢 map user
                            $user = User::where('bitrix24_id', $bitrixAuthorId)->first();

                            if (!$user) {
                                $this->warn("⚠️ No local user for Bitrix ID {$bitrixAuthorId}");
                                continue;
                            }

                            // 🟢 update
                            $comment->update([
                                'user_id' => $user->id
                            ]);

                            $count++;
                            $this->info("✔ Fixed comment ID {$comment->id}");

                        } catch (\Throwable $e) {
                            $errors++;
                            $this->error("💥 Exception on comment {$comment->id}: " . $e->getMessage());
                        }
                    }
                });

            $this->info("✅ Done. Fixed {$count} comments, Errors: {$errors}");

            return Command::SUCCESS;
        }
}