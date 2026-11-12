<?php

namespace App\Services\LiveWatch;

use App\Events\LiveWatchPresenceChanged;
use App\Models\LiveWatchSession;
use App\Models\LiveWatchSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LiveWatchPresenceService
{
    public function settings(): LiveWatchSetting
    {
        return LiveWatchSetting::current();
    }

    public function heartbeat(User $user, string $sessionId, ?string $deviceLabel = null): array
    {
        $settings = $this->settings();
        $monitoredId = (int) ($settings->monitored_user_id ?? 0);

        if ($monitoredId === 0 || (int) $user->id !== $monitoredId) {
            return [
                'tracked' => false,
                'is_online' => false,
                'transition_id' => null,
                'active_sessions' => 0,
            ];
        }

        $now = now();
        $timeout = max(30, (int) $settings->offline_timeout_seconds);

        LiveWatchSession::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'session_id' => $sessionId,
            ],
            [
                'device_label' => $deviceLabel ? Str::limit($deviceLabel, 64, '') : null,
                'last_seen_at' => $now,
            ]
        );

        $this->pruneExpiredSessions($monitoredId, $timeout);

        $active = $this->activeSessionCount($monitoredId, $timeout);
        $wasOnline = (bool) $settings->is_online;
        $transitionId = $settings->current_transition_id;
        $onlineSince = $settings->online_since;

        if ($active > 0 && ! $wasOnline) {
            $transitionId = (string) Str::uuid();
            $onlineSince = $now;
            $settings->forceFill([
                'is_online' => true,
                'current_transition_id' => $transitionId,
                'online_since' => $onlineSince,
            ])->save();

            $this->broadcastPresence($settings->fresh(), 'online');
        } elseif ($active === 0 && $wasOnline) {
            $settings->forceFill([
                'is_online' => false,
                'online_since' => null,
                // Keep last transition_id so reconnect uses a new one next time.
            ])->save();

            $this->broadcastPresence($settings->fresh(), 'offline');
            $transitionId = $settings->current_transition_id;
            $onlineSince = null;
        }

        return [
            'tracked' => true,
            'is_online' => $active > 0,
            'transition_id' => $active > 0 ? $transitionId : null,
            'online_since' => $onlineSince?->toIso8601String(),
            'active_sessions' => $active,
            'heartbeat_interval_seconds' => (int) $settings->heartbeat_interval_seconds,
            'offline_timeout_seconds' => $timeout,
        ];
    }

    public function leave(User $user, string $sessionId): array
    {
        $settings = $this->settings();
        $monitoredId = (int) ($settings->monitored_user_id ?? 0);

        LiveWatchSession::query()
            ->where('user_id', $user->id)
            ->where('session_id', $sessionId)
            ->delete();

        if ($monitoredId === 0 || (int) $user->id !== $monitoredId) {
            return ['tracked' => false, 'is_online' => false];
        }

        return $this->reconcilePresence($settings);
    }

    /**
     * Expire stale sessions and emit offline if needed. Safe to run on a schedule.
     */
    public function reconcilePresence(?LiveWatchSetting $settings = null): array
    {
        $settings = $settings ?: $this->settings();
        $monitoredId = (int) ($settings->monitored_user_id ?? 0);
        if ($monitoredId === 0) {
            if ($settings->is_online) {
                $settings->forceFill(['is_online' => false, 'online_since' => null])->save();
                $this->broadcastPresence($settings->fresh(), 'offline');
            }

            return ['tracked' => false, 'is_online' => false, 'active_sessions' => 0];
        }

        $timeout = max(30, (int) $settings->offline_timeout_seconds);
        $this->pruneExpiredSessions($monitoredId, $timeout);
        $active = $this->activeSessionCount($monitoredId, $timeout);
        $wasOnline = (bool) $settings->is_online;

        if ($active > 0 && ! $wasOnline) {
            $transitionId = (string) Str::uuid();
            $settings->forceFill([
                'is_online' => true,
                'current_transition_id' => $transitionId,
                'online_since' => now(),
            ])->save();
            $this->broadcastPresence($settings->fresh(), 'online');
        } elseif ($active === 0 && $wasOnline) {
            $settings->forceFill([
                'is_online' => false,
                'online_since' => null,
            ])->save();
            $this->broadcastPresence($settings->fresh(), 'offline');
        }

        return [
            'tracked' => true,
            'is_online' => $active > 0,
            'transition_id' => $active > 0 ? $settings->fresh()->current_transition_id : null,
            'online_since' => $settings->fresh()->online_since?->toIso8601String(),
            'active_sessions' => $active,
        ];
    }

    public function setMonitoredUser(?int $userId, ?User $actor = null, ?int $heartbeat = null, ?int $timeout = null): LiveWatchSetting
    {
        return DB::transaction(function () use ($userId, $actor, $heartbeat, $timeout) {
            $settings = $this->settings();
            $previousId = (int) ($settings->monitored_user_id ?? 0);

            $payload = [
                'monitored_user_id' => $userId,
                'updated_by' => $actor?->id,
            ];
            if ($heartbeat !== null) {
                $payload['heartbeat_interval_seconds'] = max(15, min(120, $heartbeat));
            }
            if ($timeout !== null) {
                $payload['offline_timeout_seconds'] = max(45, min(600, $timeout));
            }

            // Changing target clears presence so siren only fires on a real new online.
            if ($previousId !== (int) ($userId ?? 0)) {
                if ($previousId > 0) {
                    LiveWatchSession::query()->where('user_id', $previousId)->delete();
                }
                $payload['is_online'] = false;
                $payload['online_since'] = null;
                $payload['current_transition_id'] = null;
            }

            $settings->forceFill($payload)->save();
            $fresh = $settings->fresh(['monitoredUser']);

            if ($previousId !== (int) ($userId ?? 0)) {
                broadcast(new LiveWatchPresenceChanged(
                    status: 'offline',
                    userId: $previousId > 0 ? $previousId : (int) ($userId ?? 0),
                    transitionId: null,
                    onlineSince: null,
                    monitoredUserId: $userId,
                    userName: $fresh->monitoredUser?->name,
                ));
            }

            return $fresh;
        });
    }

    public function presenceSnapshot(): array
    {
        $settings = $this->reconcilePresence();
        $row = $this->settings()->load('monitoredUser:id,name,email,status');

        return [
            'monitored_user_id' => $row->monitored_user_id,
            'monitored_user' => $row->monitoredUser ? [
                'id' => $row->monitoredUser->id,
                'name' => $row->monitoredUser->name,
                'email' => $row->monitoredUser->email,
                'status' => $row->monitoredUser->status,
            ] : null,
            'is_online' => (bool) $row->is_online,
            'transition_id' => $row->is_online ? $row->current_transition_id : null,
            'online_since' => $row->online_since?->toIso8601String(),
            'heartbeat_interval_seconds' => (int) $row->heartbeat_interval_seconds,
            'offline_timeout_seconds' => (int) $row->offline_timeout_seconds,
            'active_sessions' => $settings['active_sessions'] ?? 0,
        ];
    }

    public function clientConfig(User $user): array
    {
        $settings = $this->settings();
        $monitoredId = (int) ($settings->monitored_user_id ?? 0);
        $isMonitored = $monitoredId > 0 && (int) $user->id === $monitoredId;
        $isSuperAdmin = $user->hasRole('super_admin');

        return [
            'heartbeat_enabled' => $isMonitored,
            'subscribe_enabled' => $isSuperAdmin,
            'heartbeat_interval_seconds' => (int) $settings->heartbeat_interval_seconds,
            'offline_timeout_seconds' => (int) $settings->offline_timeout_seconds,
            'monitored_user_id' => $isSuperAdmin ? $monitoredId : null,
        ];
    }

    private function pruneExpiredSessions(int $userId, int $timeoutSeconds): void
    {
        LiveWatchSession::query()
            ->where('user_id', $userId)
            ->where('last_seen_at', '<', now()->subSeconds($timeoutSeconds))
            ->delete();
    }

    private function activeSessionCount(int $userId, int $timeoutSeconds): int
    {
        return LiveWatchSession::query()
            ->where('user_id', $userId)
            ->where('last_seen_at', '>=', now()->subSeconds($timeoutSeconds))
            ->count();
    }

    private function broadcastPresence(LiveWatchSetting $settings, string $status): void
    {
        if (! $settings->relationLoaded('monitoredUser')) {
            $settings->load('monitoredUser:id,name');
        }

        broadcast(new LiveWatchPresenceChanged(
            status: $status,
            userId: (int) $settings->monitored_user_id,
            transitionId: $status === 'online' ? $settings->current_transition_id : null,
            onlineSince: $status === 'online' ? $settings->online_since?->toIso8601String() : null,
            monitoredUserId: (int) $settings->monitored_user_id,
            userName: $settings->monitoredUser?->name,
        ));
    }
}
