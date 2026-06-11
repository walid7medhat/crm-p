<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LocationHelper
{
    /**
     * Resolve a full, human-readable location for a given IP address using the
     * free ip-api.com geolocation service. Returns a single address-style string
     * (e.g. "Maadi, Cairo, Cairo Governorate, 11728, Egypt") or null when the
     * lookup fails or the IP is local/private (e.g. on dev machines).
     */
    public static function fromIp(?string $ip): ?string
    {
        $isLocal = empty($ip) || in_array($ip, ['127.0.0.1', '::1'], true);

        if ($isLocal) {
            // In local/dev there is no real public IP on the request. To allow
            // testing, resolve the machine's own public IP and geolocate that.
            if (! app()->environment('local')) {
                return null;
            }

            $ip = self::publicIp();

            if (empty($ip)) {
                return null;
            }
        }

        try {
            $response = Http::timeout(5)->get("http://ip-api.com/json/{$ip}", [
                'fields' => 'status,message,country,regionName,city,district,zip,lat,lon',
            ]);

            if (! $response->ok()) {
                return null;
            }

            $data = $response->json();

            if (($data['status'] ?? '') !== 'success') {
                return null;
            }

            // Build a complete address from the most specific part to the broadest.
            $parts = array_filter([
                $data['district'] ?? null,
                $data['city'] ?? null,
                $data['regionName'] ?? null,
                $data['zip'] ?? null,
                $data['country'] ?? null,
            ]);

            return $parts ? implode(', ', $parts) : null;
        } catch (\Throwable $e) {
            Log::warning('Location lookup failed: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Resolve the current machine's public IP address (used only as a local/dev
     * fallback when the request IP is a loopback/private address).
     */
    protected static function publicIp(): ?string
    {
        try {
            $response = Http::timeout(5)->get('https://api.ipify.org', ['format' => 'json']);

            return $response->ok() ? ($response->json('ip') ?: null) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
