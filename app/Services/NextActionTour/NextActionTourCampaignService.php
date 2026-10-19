<?php

namespace App\Services\NextActionTour;

use App\Events\NextActionTourCampaignUpdated;
use App\Models\NextActionTourCampaign;
use App\Models\NextActionTourUserProgress;
use App\Models\User;
use Illuminate\Support\Str;

class NextActionTourCampaignService
{
    public const MAX_VIEWS = 3;

    public function status(): array
    {
        $campaign = $this->latest();

        return [
            'is_active' => (bool) ($campaign?->is_active),
            'launch_id' => $campaign?->launch_id,
            'started_at' => $campaign?->started_at?->toIso8601String(),
            'stopped_at' => $campaign?->stopped_at?->toIso8601String(),
            'started_by' => $campaign?->started_by,
            'max_views' => self::MAX_VIEWS,
            'audience_count' => $this->audienceQuery()->count(),
        ];
    }

    public function start(User $actor): array
    {
        NextActionTourCampaign::query()
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'stopped_at' => now(),
            ]);

        $campaign = NextActionTourCampaign::query()->create([
            'launch_id' => (string) Str::uuid(),
            'is_active' => true,
            'started_by' => $actor->id,
            'started_at' => now(),
            'stopped_at' => null,
        ]);

        $this->fanOut($campaign->launch_id, true, 'start');

        return $this->status();
    }

    public function stop(User $actor): array
    {
        $campaign = $this->active();
        if ($campaign) {
            $campaign->forceFill([
                'is_active' => false,
                'stopped_at' => now(),
            ])->save();
            $this->fanOut($campaign->launch_id, false, 'stop');
        }

        return $this->status();
    }

    public function forUser(User $user): array
    {
        $campaign = $this->active();
        if (! $campaign) {
            return [
                'should_show' => false,
                'is_active' => false,
                'launch_id' => null,
                'views' => 0,
                'max_views' => self::MAX_VIEWS,
            ];
        }

        $progress = $this->progress($campaign->launch_id, $user->id);
        $views = (int) ($progress?->views ?? 0);

        return [
            'should_show' => $views < self::MAX_VIEWS,
            'is_active' => true,
            'launch_id' => $campaign->launch_id,
            'views' => $views,
            'max_views' => self::MAX_VIEWS,
        ];
    }

    public function recordShown(User $user, string $launchId): array
    {
        $campaign = $this->active();
        if (! $campaign || $campaign->launch_id !== $launchId) {
            return $this->forUser($user);
        }

        $progress = NextActionTourUserProgress::query()->firstOrNew([
            'launch_id' => $launchId,
            'user_id' => $user->id,
        ]);

        if ((int) $progress->views >= self::MAX_VIEWS) {
            return $this->forUser($user);
        }

        $progress->views = (int) $progress->views + 1;
        $progress->last_shown_at = now();
        $progress->save();

        return $this->forUser($user);
    }

    public function recordDismissed(User $user, string $launchId): array
    {
        $progress = NextActionTourUserProgress::query()->firstOrNew([
            'launch_id' => $launchId,
            'user_id' => $user->id,
        ]);
        $progress->dismissed_at = now();
        if (! $progress->exists) {
            $progress->views = max(1, (int) $progress->views);
            $progress->last_shown_at = $progress->last_shown_at ?: now();
        }
        $progress->save();

        return $this->forUser($user);
    }

    public function recordCompleted(User $user, string $launchId): array
    {
        $progress = NextActionTourUserProgress::query()->firstOrNew([
            'launch_id' => $launchId,
            'user_id' => $user->id,
        ]);
        $progress->completed_at = now();
        if (! $progress->exists) {
            $progress->views = max(1, (int) $progress->views);
            $progress->last_shown_at = $progress->last_shown_at ?: now();
        }
        $progress->save();

        return $this->forUser($user);
    }

    private function latest(): ?NextActionTourCampaign
    {
        return NextActionTourCampaign::query()->latest('id')->first();
    }

    private function active(): ?NextActionTourCampaign
    {
        return NextActionTourCampaign::query()
            ->where('is_active', true)
            ->latest('id')
            ->first();
    }

    private function progress(string $launchId, int $userId): ?NextActionTourUserProgress
    {
        return NextActionTourUserProgress::query()
            ->where('launch_id', $launchId)
            ->where('user_id', $userId)
            ->first();
    }

    private function audienceQuery()
    {
        return User::query()->where('status', 'active');
    }

    private function fanOut(string $launchId, bool $active, string $action): void
    {
        $this->audienceQuery()
            ->select(['id'])
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($launchId, $active, $action) {
                foreach ($users as $user) {
                    broadcast(new NextActionTourCampaignUpdated(
                        (int) $user->id,
                        $active,
                        $launchId,
                        $action
                    ));
                }
            });
    }
}
