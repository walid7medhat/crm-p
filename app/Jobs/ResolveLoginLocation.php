<?php

namespace App\Jobs;

use App\Helpers\LocationHelper;
use App\Models\User;
use Illuminate\Foundation\Bus\Dispatchable;

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
        $user = User::find($this->userId);
        if (!$user) return;

        try {
            if ($this->lat !== null && $this->lng !== null) {
                $location = LocationHelper::fromCoords($this->lat, $this->lng);
                $user->update([
                    'last_login_location' => LocationHelper::toAddress($location) ?? $user->last_login_location,
                    'last_login_lat' => $this->lat,   // keep the real GPS point
                    'last_login_lng' => $this->lng,
                ]);
                return;
            } else {
                $location = LocationHelper::fromIp($this->ip);
                  if (empty($location['lat']) || empty($location['lon'])) return;

                $data = [
                    'last_login_location' => '(approx. by IP) ' . (LocationHelper::toAddress($location) ?? ''),
                    'last_login_lat' => $location['lat'],
                    'last_login_lng' => $location['lon'],
                ];
            }
            $user->update($data);
        } catch (\Throwable $e) {
            \Log::warning('ResolveLoginLocation failed: ' . $e->getMessage());
        }
    }
}