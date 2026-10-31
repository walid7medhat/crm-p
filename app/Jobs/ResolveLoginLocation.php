<?php

namespace App\Jobs;

use App\Helpers\LocationHelper;
use App\Models\User;
use App\Models\UserLocationHistory;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Reverse-geocode a device fix into a street label after a location write.
 * Coordinates are written by the request itself. This job must not
 * replace them, and it must not invent a position from the request IP.
 * The $ip argument remains so jobs already queued with the old constructor
 * can still be unserialized. Optional $historyId fills the matching history row.
 */
class ResolveLoginLocation
{
    use Dispatchable;

    public function __construct(
        public int $userId,
        public ?float $lat = null,
        public ?float $lng = null,
        public ?string $ip = null,
        public ?int $historyId = null,
    ) {}

    public function handle(): void
    {
        if ($this->lat === null || $this->lng === null) {
            return;
        }

        $user = User::find($this->userId);
        $history = null;
        if ($this->historyId) {
            $history = UserLocationHistory::query()
                ->where('id', $this->historyId)
                ->where('user_id', $this->userId)
                ->first();
        }

        $updateUser = $user && $this->matchesStoredPoint($user);
        $updateHistory = $history && $this->matchesHistoryPoint($history);

        // Skip the network call entirely when nothing still matches these coords.
        if (! $updateUser && ! $updateHistory) {
            return;
        }

        try {
            $location = LocationHelper::fromCoords($this->lat, $this->lng);
            $address = LocationHelper::toAddress($location);
            if ($address === null || $address === '') {
                return;
            }

            if ($updateUser) {
                $user->update([
                    'last_login_location' => $address,
                ]);
            }

            if ($updateHistory) {
                $history->update(['address' => $address]);
            }
        } catch (\Throwable $e) {
            \Log::warning('ResolveLoginLocation failed: '.$e->getMessage());
        }
    }

    private function matchesStoredPoint(User $user): bool
    {
        if (! is_numeric($user->last_login_lat) || ! is_numeric($user->last_login_lng)) {
            return false;
        }

        return abs((float) $user->last_login_lat - $this->lat) < 0.000001
            && abs((float) $user->last_login_lng - $this->lng) < 0.000001;
    }

    private function matchesHistoryPoint(UserLocationHistory $history): bool
    {
        if (! is_numeric($history->latitude) || ! is_numeric($history->longitude)) {
            return false;
        }

        return abs((float) $history->latitude - $this->lat) < 0.000001
            && abs((float) $history->longitude - $this->lng) < 0.000001;
    }
}
