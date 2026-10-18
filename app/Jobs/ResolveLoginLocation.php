<?php

namespace App\Jobs;

use App\Helpers\LocationHelper;
use App\Models\User;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Reverse-geocode a device fix into a street label after sign-in.
 * Coordinates are written by the login request itself. This job must not
 * replace them, and it must not invent a position from the request IP.
 * The $ip argument remains so jobs already queued with the old constructor
 * can still be unserialized.
 */
class ResolveLoginLocation
{
    use Dispatchable;

    public function __construct(
        public int $userId,
        public ?float $lat = null,
        public ?float $lng = null,
        public ?string $ip = null,
    ) {}

    public function handle(): void
    {
        if ($this->lat === null || $this->lng === null) {
            return;
        }

        $user = User::find($this->userId);
        if (! $user || ! $this->matchesStoredPoint($user)) {
            return;
        }

        try {
            $location = LocationHelper::fromCoords($this->lat, $this->lng);
            $address = LocationHelper::toAddress($location);
            if ($address === null || $address === '') {
                return;
            }

            $user->update([
                'last_login_location' => $address,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('ResolveLoginLocation failed: ' . $e->getMessage());
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
}
