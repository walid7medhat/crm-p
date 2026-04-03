<?php

namespace App\Services;

use App\Models\GeocodingCache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    /**
     * Nominatim policy-friendly request spacing (1 req/sec).
     */
    private const REQUEST_DELAY_MICROSECONDS = 1000000;

    public function geocode(string $locationText): ?array
    {
        $queries = $this->buildCandidateQueries($locationText);
        if (empty($queries)) {
            return null;
        }

        $userAgent = config('services.nominatim.user_agent', 'crm-property-map/1.0');
        $maxRetries = (int) config('services.nominatim.retries', 3);
        $baseUrl = config('services.nominatim.base_url', 'https://nominatim.openstreetmap.org/search');

        foreach ($queries as $query) {
            $cached = GeocodingCache::query()->where('query', $query)->first();
            if ($cached && $cached->is_success && $cached->latitude && $cached->longitude) {
                return [
                    'latitude' => (float) $cached->latitude,
                    'longitude' => (float) $cached->longitude,
                ];
            }

            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                try {
                    usleep(self::REQUEST_DELAY_MICROSECONDS);

                    $params = [
                        'q' => $query,
                        'format' => 'jsonv2',
                        'limit' => 1,
                        'addressdetails' => 1,
                    ];
                    $cc = trim((string) config('services.nominatim.countrycodes', 'ae'));
                    if ($cc !== '') {
                        $params['countrycodes'] = $cc;
                    }

                    $response = Http::timeout(15)
                        ->withHeaders([
                            'User-Agent' => $userAgent,
                            'Accept-Language' => 'en',
                        ])
                        ->get($baseUrl, $params);

                    if (!$response->successful()) {
                        $this->upsertFailedCache($query, $attempt, 'HTTP ' . $response->status(), null);
                        continue;
                    }

                    $data = $response->json();
                    if (!is_array($data) || empty($data[0]['lat']) || empty($data[0]['lon'])) {
                        $this->upsertFailedCache($query, $attempt, 'No coordinates returned', $data);
                        continue;
                    }

                    $lat = (float) $data[0]['lat'];
                    $lng = (float) $data[0]['lon'];

                    GeocodingCache::query()->updateOrCreate(
                        ['query' => $query],
                        [
                            'provider' => 'nominatim',
                            'latitude' => $lat,
                            'longitude' => $lng,
                            'is_success' => true,
                            'attempts' => $attempt,
                            'last_error' => null,
                            'raw_response' => $data,
                        ]
                    );

                    return ['latitude' => $lat, 'longitude' => $lng];
                } catch (\Throwable $e) {
                    $this->upsertFailedCache($query, $attempt, $e->getMessage(), null);
                    Log::warning('Geocoding attempt failed', [
                        'query' => $query,
                        'attempt' => $attempt,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }

        return null;
    }

    public function buildQueryFromListingData(?string $projectName, ?string $areaName, ?string $address): string
    {
        $parts = array_filter([
            $projectName ? trim($projectName) : null,
            $areaName ? trim($areaName) : null,
            $address ? trim($address) : null,
        ]);

        return trim(implode(', ', $parts));
    }

    /**
     * Geocode a string like "Area Name, Dubai, UAE".
     * Cached in geocoding_caches by normalized query (see geocode()).
     */
    public function geocodeAreaFallback(string $query): ?array
    {
        return $this->geocode($query);
    }

    /**
     * Map/list endpoints: only return coords already in geocoding_caches — no HTTP, no 1s delays.
     *
     * @param  int  $maxCandidates  Limit DB lookups per listing (map can load thousands of rows).
     */
    public function geocodeFromCacheOnly(string $query, int $maxCandidates = 5): ?array
    {
        $queries = $this->buildCandidateQueries($query);
        $queries = array_slice(array_values(array_filter($queries)), 0, max(1, $maxCandidates));
        foreach ($queries as $candidate) {
            if ($candidate === '') {
                continue;
            }
            $cached = GeocodingCache::query()->where('query', $candidate)->first();
            if ($cached && $cached->is_success && $cached->latitude && $cached->longitude) {
                return [
                    'latitude' => (float) $cached->latitude,
                    'longitude' => (float) $cached->longitude,
                ];
            }
        }

        return null;
    }

    private function buildCandidateQueries(string $query): array
    {
        $base = trim(preg_replace('/\s+/', ' ', $query));
        if ($base === '') {
            return [];
        }

        $lower = strtolower($base);
        $isAbuDhabiLikely = str_contains($lower, 'yas')
            || str_contains($lower, 'saadiyat')
            || str_contains($lower, 'abu dhabi')
            || str_contains($lower, 'masdar')
            || str_contains($lower, 'reem')
            || str_contains($lower, 'hudayriat')
            || str_contains($lower, 'fahid')
            || str_contains($lower, 'raha')
            || str_contains($lower, 'al reef')
            || str_contains($lower, 'shamkha')
            || str_contains($lower, 'samha')
            || str_contains($lower, 'ghadeer')
            || str_contains($lower, 'zayed city');

        $candidates = [];
        $candidates[] = $this->normalizeQuery($base);
        $candidates[] = $this->normalizeQuery($base . ', ' . ($isAbuDhabiLikely ? 'Abu Dhabi' : 'Dubai') . ', UAE');
        $candidates[] = $this->normalizeQuery($base . ', UAE');
        $candidates[] = $this->normalizeQuery($base . ', Abu Dhabi, UAE');
        $candidates[] = $this->normalizeQuery($base . ', Dubai, UAE');

        return array_values(array_unique(array_filter($candidates)));
    }

    private function normalizeQuery(string $query): string
    {
        $query = trim(preg_replace('/\s+/', ' ', $query));

        if ($query === '') {
            return '';
        }

        // Ensure country is present to improve hit quality.
        if (!str_contains(strtolower($query), 'uae') && !str_contains(strtolower($query), 'united arab emirates')) {
            $query .= ', UAE';
        }

        return $query;
    }

    private function upsertFailedCache(string $query, int $attempt, string $error, $rawResponse): void
    {
        GeocodingCache::query()->updateOrCreate(
            ['query' => $query],
            [
                'provider' => 'nominatim',
                'is_success' => false,
                'attempts' => $attempt,
                'last_error' => $error,
                'raw_response' => $rawResponse,
            ]
        );
    }
}

