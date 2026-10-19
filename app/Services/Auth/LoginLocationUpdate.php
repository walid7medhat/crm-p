<?php

namespace App\Services\Auth;

use App\Models\User;
use Carbon\Carbon;

class LoginLocationUpdate
{
    /** Device-reported accuracy at or below this is shown as a precise fix. */
    public const PRECISE_ACCURACY_METERS = 100;

    /** Readings at or above this must not replace a recent precise device fix. */
    public const COARSE_ACCURACY_METERS = 1000;

    public const RECENT_PRECISE_MINUTES = 15;

    /**
     * Decide whether an incoming device reading should replace the stored one.
     * Missing coordinates, an older capture, and a coarse reading that would
     * wipe a precise fix from the last few minutes are rejected. Nothing here
     * invents a position.
     *
     * @return array{apply: bool, latitude?: float, longitude?: float, accuracy: ?int, captured_at?: Carbon}
     */
    public function decision(User $user, mixed $lat, mixed $lng, mixed $accuracy = null, mixed $capturedAt = null): array
    {
        $latitude = $this->coordinate($lat);
        $longitude = $this->coordinate($lng);

        if ($latitude === null || $longitude === null) {
            return ['apply' => false];
        }

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return ['apply' => false];
        }

        $meters = $this->accuracy($accuracy);
        $when = $this->capturedAt($capturedAt);
        $existingWhen = $user->last_login_location_at;

        if ($existingWhen instanceof Carbon && $when->lt($existingWhen)) {
            return ['apply' => false];
        }

        if ($this->wouldReplaceRecentPreciseFix($user, $meters, $existingWhen)) {
            return ['apply' => false];
        }

        return [
            'apply' => true,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'accuracy' => $meters,
            'captured_at' => $when,
        ];
    }

    /**
     * @param  array{apply: bool, latitude?: float, longitude?: float, accuracy: ?int, captured_at?: Carbon}  $decision
     * @return array<string, float|int|Carbon|null>
     */
    public function attributes(array $decision): array
    {
        if (! ($decision['apply'] ?? false)) {
            return [];
        }

        return [
            'last_login_lat' => $decision['latitude'],
            'last_login_lng' => $decision['longitude'],
            'last_login_accuracy' => $decision['accuracy'],
            'last_login_location_at' => $decision['captured_at'],
        ];
    }

    /**
     * True when the new point is far enough from the stored one that the old
     * street label would describe a different place. About 20 metres.
     */
    public function moved(mixed $fromLat, mixed $fromLng, mixed $toLat, mixed $toLng): bool
    {
        $fromLatitude = $this->coordinate($fromLat);
        $fromLongitude = $this->coordinate($fromLng);
        $toLatitude = $this->coordinate($toLat);
        $toLongitude = $this->coordinate($toLng);

        if ($fromLatitude === null || $fromLongitude === null || $toLatitude === null || $toLongitude === null) {
            return true;
        }

        return abs($fromLatitude - $toLatitude) > 0.0002
            || abs($fromLongitude - $toLongitude) > 0.0002;
    }

    public static function source(User $user): ?string
    {
        $label = (string) ($user->last_login_location ?? '');
        if ($user->last_login_accuracy === null && str_starts_with($label, '(approx. by IP)')) {
            return 'ip';
        }

        if ($user->last_login_lat !== null && $user->last_login_lng !== null) {
            return 'device';
        }

        return null;
    }

    public static function isPrecise(mixed $accuracy): bool
    {
        return is_numeric($accuracy)
            && (float) $accuracy >= 0
            && (float) $accuracy <= self::PRECISE_ACCURACY_METERS;
    }

    private function wouldReplaceRecentPreciseFix(User $user, ?int $incomingAccuracy, mixed $existingWhen): bool
    {
        if (! $existingWhen instanceof Carbon) {
            return false;
        }

        if ($user->last_login_lat === null || $user->last_login_lng === null) {
            return false;
        }

        if (! self::isPrecise($user->last_login_accuracy)) {
            return false;
        }

        if ($existingWhen->lte(now()->subMinutes(self::RECENT_PRECISE_MINUTES))) {
            return false;
        }

        return $incomingAccuracy === null || $incomingAccuracy >= self::COARSE_ACCURACY_METERS;
    }

    private function coordinate(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        if (! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return is_finite($number) ? $number : null;
    }

    private function accuracy(mixed $value): ?int
    {
        $number = $this->coordinate($value);
        if ($number === null || $number < 0 || $number > 1000000) {
            return null;
        }

        return (int) round($number);
    }

    private function capturedAt(mixed $value): Carbon
    {
        if ($value === null || $value === '') {
            return now();
        }

        try {
            $parsed = Carbon::parse($value);
        } catch (\Throwable) {
            return now();
        }

        if ($parsed->greaterThan(now()->addMinutes(5))) {
            return now();
        }

        return $parsed;
    }
}
