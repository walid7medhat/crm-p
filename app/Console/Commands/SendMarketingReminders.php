<?php

namespace App\Console\Commands;

use App\Models\MarketingCalendarPost;
use App\Models\MarketingStage;
use App\Models\MarketingTask;
use App\Models\User;
use App\Notifications\MarketingReminderNotification;
use App\Services\Marketing\MarketingHistoryRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Marketing reminders for the people on each card:
 *  - due tomorrow, due today, and overdue (1, 3 and 7 days late — then it stops nagging)
 *  - calendar posts scheduled today that aren't marked posted yet
 * Each reminder is sent once (cache key per kind/card/day), so running it again is safe.
 */
class SendMarketingReminders extends Command
{
    protected $signature = 'marketing:send-reminders {--dry : list what would be sent without sending}';

    protected $description = 'Send marketing due-date and posting reminders';

    private const OVERDUE_DAYS = [1, 3, 7];

    private int $sent = 0;

    public function handle(): int
    {
        $this->sent = 0;
        $today = now()->startOfDay();
        $doneStageIds = MarketingStage::where('is_done', true)->pluck('id');

        $open = MarketingTask::query()
            ->whereNotNull('due_date')
            ->whereNotIn('stage_id', $doneStageIds)
            ->whereDate('due_date', '>=', $today->copy()->subDays(max(self::OVERDUE_DAYS)))
            ->whereDate('due_date', '<=', $today->copy()->addDay())
            ->get();

        foreach ($open as $task) {
            $days = (int) $task->due_date->copy()->startOfDay()->diffInDays($today, false);
            $kind = match (true) {
                $days === -1 => 'due_tomorrow',
                $days === 0 => 'due_today',
                in_array($days, self::OVERDUE_DAYS, true) => 'overdue',
                default => null,
            };
            if ($kind) {
                $this->remind($kind, $task->title, $task->id, $task->category, $this->peopleOn($task), ['days' => $days]);
            }
        }

        $posts = MarketingCalendarPost::query()
            ->whereDate('scheduled_date', $today->toDateString())
            ->where('is_posted', false)
            ->with('task')
            ->get();

        foreach ($posts as $post) {
            $people = $post->task ? $this->peopleOn($post->task) : collect();
            if ($post->created_by) {
                $people = $people->push((int) $post->created_by)->unique();
            }
            $this->remind(
                'post_today',
                $post->title,
                $post->task_id,
                $post->task?->category,
                $people,
                ['platform' => MarketingHistoryRecorder::humanize($post->platform)],
                'post'.$post->id,
            );
        }

        $this->info("Marketing reminders sent: {$this->sent}");
        Log::info("marketing:send-reminders sent {$this->sent}");

        return self::SUCCESS;
    }

    /** Assignee, shooter and editor; falls back to the creator if nobody is assigned. */
    private function peopleOn(MarketingTask $task)
    {
        $ids = collect([$task->assigned_to, $task->shooting_by, $task->editing_by])->filter()->map(fn ($id) => (int) $id)->unique();

        return $ids->isEmpty() && $task->created_by ? collect([(int) $task->created_by]) : $ids;
    }

    private function remind(string $kind, string $title, ?int $taskId, ?string $category, $userIds, array $extra, ?string $key = null): void
    {
        $key ??= 'task'.$taskId;
        $users = User::whereIn('id', $userIds)->where('status', 'active')->get();

        foreach ($users as $user) {
            $cacheKey = "marketing-reminder:{$kind}:{$key}:{$user->id}:".now()->toDateString();
            if ($this->option('dry')) {
                $this->line("[dry] {$kind} → {$user->name}: {$title}");
                continue;
            }
            // Cache::add only succeeds once → each reminder goes out once per day.
            if (! Cache::add($cacheKey, true, now()->addDays(2))) {
                continue;
            }
            $user->notify(new MarketingReminderNotification($kind, $title, $taskId, $category, $extra));
            $this->sent++;
        }
    }
}
