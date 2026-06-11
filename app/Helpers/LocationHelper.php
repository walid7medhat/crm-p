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
    public static function fromIp(?string $ip): ?array
    {
        $isLocal = empty($ip) || in_array($ip, ['127.0.0.1', '::1'], true);

        if ($isLocal) {
            return [
                'ip' => $ip,
                'country' => 'Local',
                'city' => 'Local',
                'lat' => null,
                'lon' => null,
            ];
        }

        try {
            $response = Http::timeout(5)->get("http://ip-api.com/json/{$ip}", [
                'fields' => 'status,message,country,countryCode,region,regionName,city,district,zip,lat,lon,timezone,isp,org',
            ]);

            if (! $response->ok()) {
                return null;
            }

            $data = $response->json();

            if (($data['status'] ?? '') !== 'success') {
                return null;
            }

            return [
                'ip' => $ip,
                'country' => $data['country'] ?? null,
                'country_code' => $data['countryCode'] ?? null,
                'region' => $data['regionName'] ?? null,
                'city' => $data['city'] ?? null,
                'district' => $data['district'] ?? null,
                'zip' => $data['zip'] ?? null,
                'lat' => $data['lat'] ?? null,
                'lon' => $data['lon'] ?? null,
                'timezone' => $data['timezone'] ?? null,
                'isp' => $data['isp'] ?? null,
                'org' => $data['org'] ?? null,
            ];

        } catch (\Throwable $e) {
            Log::warning('Location lookup failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Build a single human-readable address line from a location array returned
     * by fromIp() (district, city, region, zip, country). Returns null when none
     * of the parts are present.
     */
    public static function toAddress(?array $location): ?string
    {
        if (empty($location)) {
            return null;
        }

        $parts = array_filter([
            $location['district'] ?? null,
            $location['city'] ?? null,
            $location['region'] ?? null,
            $location['zip'] ?? null,
            $location['country'] ?? null,
        ]);

        return $parts ? implode(', ', $parts) : null;
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
