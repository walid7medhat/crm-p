<?php

namespace App\Services\Auth;

use App\Jobs\ResolveLoginLocation;
use App\Models\User;
use App\Models\UserLocationHistory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LocationTrackingService
{
    public function __construct(
        private LoginLocationUpdate $recorder,
    ) {}

    /**
     * Persist the caller's latest device fix and optionally append history.
     *
     * @return array{location_updated: bool, history_recorded: bool}
     */
    public function record(User $user, Request $request): array
    {
        $cooldown = max(0, (int) config('location_tracking.write_cooldown_seconds', 30));
        $cacheKey = 'user-location-write:'.$user->id;
        if ($cooldown > 0 && Cache::has($cacheKey)) {
            return ['location_updated' => false, 'history_recorded' => false];
        }

        $decision = $this->recorder->decision(
            $user,
            $request->input('latitude'),
            $request->input('longitude'),
            $request->input('accuracy'),
            $request->input('captured_at'),
        );
        $attributes = $this->recorder->attributes($decision);

        $loginData = [
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ];

        $locationUpdated = false;
        $historyRecorded = false;

        if ($attributes !== []) {
            if ($this->recorder->moved(
                $user->last_login_lat,
                $user->last_login_lng,
                $attributes['last_login_lat'],
                $attributes['last_login_lng'],
            )) {
                $attributes['last_login_location'] = null;
            }

            $loginData = array_merge($loginData, $attributes);
            $locationUpdated = true;
        }

        $user->update($loginData);

        if ($locationUpdated) {
            $forceSessionStart = filter_var($request->input('session_start'), FILTER_VALIDATE_BOOLEAN);
            $history = $this->maybeAppendHistory($user->id, $attributes, $forceSessionStart);
            $historyRecorded = $history !== null;

            ResolveLoginLocation::dispatch(
                $user->id,
                (float) $attributes['last_login_lat'],
                (float) $attributes['last_login_lng'],
                $request->ip(),
                $history?->id,
            )->afterResponse();
        }

        if ($cooldown > 0 && ($locationUpdated || $historyRecorded)) {
            Cache::put($cacheKey, true, now()->addSeconds($cooldown));
        }

        return [
            'location_updated' => $locationUpdated,
            'history_recorded' => $historyRecorded,
        ];
    }

    /**
     * Append when this is a new online session, the user moved enough, or there
     * is no prior history. Online session boundaries use the same window as CRM
     * presence (last_login_at / JwtAuthMiddleware), not a fresh login.
     *
     * @param  array<string, float|int|Carbon|null>  $attributes
     */
    private function maybeAppendHistory(int $userId, array $attributes, bool $forceSessionStart = false): ?UserLocationHistory
    {
        $lat = $attributes['last_login_lat'] ?? null;
        $lng = $attributes['last_login_lng'] ?? null;
        if ($lat === null || $lng === null) {
            return null;
        }

        $minMeters = (float) config('location_tracking.min_move_meters', 75);
        $onlineWindow = max(1, (int) config('location_tracking.online_window_minutes', 15));
        $last = UserLocationHistory::query()
            ->where('user_id', $userId)
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->first();

        $newOnlineSession = $forceSessionStart || $this->isNewOnlineSession($last, $onlineWindow);
        $movedEnough = ! $last || $this->recorder->movedAtLeast(
            $last->latitude,
            $last->longitude,
            $lat,
            $lng,
            $minMeters,
        );

        if ($last && ! $newOnlineSession && ! $movedEnough) {
            return null;
        }

        $capturedAt = $attributes['last_login_location_at'] ?? now();
        if (! $capturedAt instanceof Carbon) {
            $capturedAt = Carbon::parse($capturedAt);
        }

        return UserLocationHistory::create([
            'user_id' => $userId,
            'latitude' => $lat,
            'longitude' => $lng,
            'accuracy' => $attributes['last_login_accuracy'] ?? null,
            'captured_at' => $capturedAt,
            'recorded_at' => now(),
            'source' => 'device',
            'address' => null,
            'session_started_at' => $newOnlineSession ? now() : null,
        ]);
    }

    private function isNewOnlineSession(?UserLocationHistory $last, int $onlineWindowMinutes): bool
    {
        if (! $last || ! $last->captured_at instanceof Carbon) {
            return true;
        }

        // Same rule as UI/API "online": activity older than the window means they
        // were offline. The next accepted fix is the start of a new online session.
        return $last->captured_at->lte(now()->subMinutes($onlineWindowMinutes));
    }
}
