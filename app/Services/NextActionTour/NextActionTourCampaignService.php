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

    public const FEATURE_NEXT_ACTION = 'next_action';

    public const FEATURE_PROPERTY_COMPARE = 'property_compare';

    public function status(?string $feature = null): array
    {
        $feature = $this->normalizeFeature($feature);
        $campaign = $this->latest($feature);

        return [
            'feature' => $feature,
            'is_active' => (bool) ($campaign?->is_active),
            'launch_id' => $campaign?->launch_id,
            'started_at' => $campaign?->started_at?->toIso8601String(),
            'stopped_at' => $campaign?->stopped_at?->toIso8601String(),
            'started_by' => $campaign?->started_by,
            'max_views' => $this->maxViews($feature),
            'audience_count' => $this->audienceQuery()->count(),
        ];
    }

    public function start(User $actor, ?string $feature = null): array
    {
        $feature = $this->normalizeFeature($feature);

        NextActionTourCampaign::query()
            ->where('feature', $feature)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'stopped_at' => now(),
            ]);

        $campaign = NextActionTourCampaign::query()->create([
            'launch_id' => (string) Str::uuid(),
            'feature' => $feature,
            'is_active' => true,
            'started_by' => $actor->id,
            'started_at' => now(),
            'stopped_at' => null,
        ]);

        $this->fanOut($campaign->launch_id, true, 'start', $feature);

        return $this->status($feature);
    }

    public function stop(User $actor, ?string $feature = null): array
    {
        $feature = $this->normalizeFeature($feature);
        $campaign = $this->active($feature);
        if ($campaign) {
            $campaign->forceFill([
                'is_active' => false,
                'stopped_at' => now(),
            ])->save();
            $this->fanOut($campaign->launch_id, false, 'stop', $feature);
        }

        return $this->status($feature);
    }

    public function forUser(User $user, ?string $feature = null): array
    {
        $feature = $this->normalizeFeature($feature);
        $campaign = $this->active($feature);
        $max = $this->maxViews($feature);
        if (! $campaign) {
            return [
                'feature' => $feature,
                'should_show' => false,
                'is_active' => false,
                'launch_id' => null,
                'views' => 0,
                'max_views' => $max,
            ];
        }

        $progress = $this->progress($campaign->launch_id, $user->id);
        $views = (int) ($progress?->views ?? 0);

        return [
            'feature' => $feature,
            'should_show' => $views < $max,
            'is_active' => true,
            'launch_id' => $campaign->launch_id,
            'views' => $views,
            'max_views' => $max,
        ];
    }

    public function recordShown(User $user, string $launchId): array
    {
        $campaign = $this->campaignByLaunch($launchId);
        $feature = $campaign?->feature ?: self::FEATURE_NEXT_ACTION;
        if (! $campaign || ! $campaign->is_active) {
            return $this->forUser($user, $feature);
        }

        $max = $this->maxViews($feature);
        $progress = NextActionTourUserProgress::query()->firstOrNew([
            'launch_id' => $launchId,
            'user_id' => $user->id,
        ]);

        if ((int) $progress->views >= $max) {
            return $this->forUser($user, $feature);
        }

        $progress->views = (int) $progress->views + 1;
        $progress->last_shown_at = now();
        $progress->save();

        return $this->forUser($user, $feature);
    }

    public function recordDismissed(User $user, string $launchId): array
    {
        $campaign = $this->campaignByLaunch($launchId);
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

        return $this->forUser($user, $campaign?->feature);
    }

    public function recordCompleted(User $user, string $launchId): array
    {
        $campaign = $this->campaignByLaunch($launchId);
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

        return $this->forUser($user, $campaign?->feature);
    }

    private function normalizeFeature(?string $feature): string
    {
        return $feature === self::FEATURE_PROPERTY_COMPARE
            ? self::FEATURE_PROPERTY_COMPARE
            : self::FEATURE_NEXT_ACTION;
    }

    private function maxViews(string $feature): int
    {
        return $feature === self::FEATURE_PROPERTY_COMPARE ? 1 : self::MAX_VIEWS;
    }

    private function latest(string $feature): ?NextActionTourCampaign
    {
        return NextActionTourCampaign::query()
            ->where('feature', $feature)
            ->latest('id')
            ->first();
    }

    private function active(string $feature): ?NextActionTourCampaign
    {
        return NextActionTourCampaign::query()
            ->where('feature', $feature)
            ->where('is_active', true)
            ->latest('id')
            ->first();
    }

    private function campaignByLaunch(string $launchId): ?NextActionTourCampaign
    {
        return NextActionTourCampaign::query()->where('launch_id', $launchId)->first();
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

    private function fanOut(string $launchId, bool $active, string $action, string $feature): void
    {
        broadcast(new NextActionTourCampaignUpdated(
            0,
            $active,
            $launchId,
            $action,
            $feature
        ));
    }
}
