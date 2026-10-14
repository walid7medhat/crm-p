<?php

namespace App\Events;

use App\Models\MarketingTask;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Live board sync (like DealUpdated): tells every marketing member's open board that a card
 * changed, so it can refresh without a reload. Carries ids only — the board re-fetches.
 */
class MarketingBoardUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @param list<int> $recipientIds */
    public function __construct(
        public int $taskId,
        public string $category,
        public string $action,
        public ?int $actorId,
        public ?string $actorName,
        public array $recipientIds,
        public ?string $title = null,
    ) {
    }

    public static function fromTask(MarketingTask $task, string $action, ?User $actor): self
    {
        $recipients = User::permission('marketing')->where('status', 'active')->pluck('id')
            // Super admins see Marketing without the permission; admins need it (covered above).
            ->merge(User::role('super_admin')->where('status', 'active')->pluck('id'))
            ->unique()
            ->reject(fn ($id) => (int) $id === (int) $actor?->id)
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return new self($task->id, $task->category, $action, $actor?->id, $actor?->name, $recipients, $task->title);
    }

    public function broadcastOn(): array
    {
        return array_map(fn ($id) => new PrivateChannel('user.'.$id), $this->recipientIds);
    }

    public function broadcastAs(): string
    {
        return 'marketing.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'task_id' => $this->taskId,
            'category' => $this->category,
            'action' => $this->action,
            'title' => $this->title,
            'user_id' => $this->actorId,
            'user_name' => $this->actorName,
            'timestamp' => now()->toISOString(),
        ];
    }
}
