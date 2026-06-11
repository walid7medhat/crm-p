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
                $ip = self::publicIp();

                if (!$ip) {
                    return [
                        'ip' => '127.0.0.1',
                        'country' => 'Local',
                        'city' => 'Local',
                    ];
                }
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
     * Resolve a full, street-level location from exact GPS coordinates (sent by
     * the browser's geolocation API) using the free OpenStreetMap Nominatim
     * reverse-geocoder. Unlike IP lookup, this gives the actual street/road.
     * Always returns at least the coordinates, even when geocoding fails.
     */
    public static function fromCoords($lat, $lng): ?array
    {
        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        $base = ['lat' => (float) $lat, 'lon' => (float) $lng];

        try {
            // Nominatim requires a descriptive User-Agent and max ~1 req/sec.
            $response = Http::withHeaders(['User-Agent' => config('app.name', 'CRM') . ' Login Location'])
                ->timeout(6)
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'lat'            => $lat,
                    'lon'            => $lng,
                    'format'         => 'json',
                    'addressdetails' => 1,
                    'zoom'           => 18,
                ]);

            if (! $response->ok()) {
                return $base;
            }

            $data = $response->json();
            $a = $data['address'] ?? [];

            return array_merge($base, [
                'display_name' => $data['display_name'] ?? null,
                'building'     => $a['building'] ?? $a['amenity'] ?? $a['shop'] ?? $a['office'] ?? null,
                'house_number' => $a['house_number'] ?? null,
                'road'         => $a['road'] ?? null,
                'district'     => $a['suburb'] ?? $a['neighbourhood'] ?? $a['city_district'] ?? null,
                'city'         => $a['city'] ?? $a['town'] ?? $a['village'] ?? $a['county'] ?? null,
                'region'       => $a['state'] ?? null,
                'zip'          => $a['postcode'] ?? null,
                'country'      => $a['country'] ?? null,
                'country_code' => isset($a['country_code']) ? strtoupper($a['country_code']) : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Reverse geocode failed: ' . $e->getMessage());

            return $base;
        }
    }

    /**
     * Build a single human-readable address line from a location array. When a
     * full reverse-geocoded street address (display_name) is present it is used
     * as-is; otherwise the broader IP-level parts are joined together.
     */
    public static function toAddress(?array $location): ?string
    {
        if (empty($location)) {
            return null;
        }

        if (! empty($location['display_name'])) {
            return $location['display_name'];
        }

        $parts = array_filter([
            $location['building'] ?? null,
            $location['house_number'] ?? null,
            $location['road'] ?? null,
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
