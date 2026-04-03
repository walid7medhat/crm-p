<?php

namespace App\Services;

use App\Models\Listing;

/**
 * Resolves map pin coordinates for a listing row loaded with
 * listing_area_*, listing_parent_area_*, listing_grandparent_area_* and project_area_* join columns.
 *
 * Priority: listing area DB coords → parent area DB coords → grandparent area DB coords
 * → config mapping (child, then parent, then grandparent name) → project area DB coords
 * → mapping by project area name → cached geocode → config default centroid.
 *
 * Parent/grandparent steps ensure child communities without their own lat/lng still pin to the parent region.
 */
final class ListingMapCoordinateResolver
{
    private int $geocodeFallbackCalls = 0;

    public function __construct(
        private GeocodingService $geocoding
    ) {}

    /**
     * @return array{
     *   latitude: float,
     *   longitude: float,
     *   map_area_id: int|null,
     *   area_join_name: string,
     *   coordinate_source: 'listing_area'|'listing_parent_area'|'listing_grandparent_area'|'project_area'|'area_mapping'|'geocode'|'default'
     * }
     */
    public function resolve(Listing $listing, int $maxGeocodeFallbacksPerRequest): array
    {
        $direct = $this->tryDbAreaRow(
            $this->nullableFloat($listing->listing_area_latitude ?? null),
            $this->nullableFloat($listing->listing_area_longitude ?? null),
            isset($listing->listing_area_id) ? (int) $listing->listing_area_id : null,
            (string) ($listing->listing_area_name ?? ''),
            'listing_area'
        );
        if ($direct !== null) {
            return $direct;
        }

        $parent = $this->tryDbAreaRow(
            $this->nullableFloat($listing->listing_parent_area_latitude ?? null),
            $this->nullableFloat($listing->listing_parent_area_longitude ?? null),
            isset($listing->listing_parent_area_id) ? (int) $listing->listing_parent_area_id : null,
            (string) ($listing->listing_parent_area_name ?? ''),
            'listing_parent_area'
        );
        if ($parent !== null) {
            return $parent;
        }

        $grandparent = $this->tryDbAreaRow(
            $this->nullableFloat($listing->listing_grandparent_area_latitude ?? null),
            $this->nullableFloat($listing->listing_grandparent_area_longitude ?? null),
            isset($listing->listing_grandparent_area_id) ? (int) $listing->listing_grandparent_area_id : null,
            (string) ($listing->listing_grandparent_area_name ?? ''),
            'listing_grandparent_area'
        );
        if ($grandparent !== null) {
            return $grandparent;
        }

        $mappedListing = $this->coordsFromAreaNameMapping($listing->listing_area_name ?? null);
        if ($mappedListing !== null) {
            return [
                'latitude' => $mappedListing['latitude'],
                'longitude' => $mappedListing['longitude'],
                'map_area_id' => isset($listing->listing_area_id) ? (int) $listing->listing_area_id : null,
                'area_join_name' => (string) ($listing->listing_area_name ?? ''),
                'coordinate_source' => 'area_mapping',
            ];
        }

        $mappedParent = $this->coordsFromAreaNameMapping($listing->listing_parent_area_name ?? null);
        if ($mappedParent !== null) {
            return [
                'latitude' => $mappedParent['latitude'],
                'longitude' => $mappedParent['longitude'],
                'map_area_id' => isset($listing->listing_parent_area_id) ? (int) $listing->listing_parent_area_id : null,
                'area_join_name' => (string) ($listing->listing_parent_area_name ?? ''),
                'coordinate_source' => 'area_mapping',
            ];
        }

        $mappedGp = $this->coordsFromAreaNameMapping($listing->listing_grandparent_area_name ?? null);
        if ($mappedGp !== null) {
            return [
                'latitude' => $mappedGp['latitude'],
                'longitude' => $mappedGp['longitude'],
                'map_area_id' => isset($listing->listing_grandparent_area_id) ? (int) $listing->listing_grandparent_area_id : null,
                'area_join_name' => (string) ($listing->listing_grandparent_area_name ?? ''),
                'coordinate_source' => 'area_mapping',
            ];
        }

        $paLat = $this->nullableFloat($listing->project_area_latitude ?? null);
        $paLng = $this->nullableFloat($listing->project_area_longitude ?? null);
        if ($paLat !== null && $paLng !== null) {
            return [
                'latitude' => $paLat,
                'longitude' => $paLng,
                'map_area_id' => isset($listing->project_area_id) ? (int) $listing->project_area_id : null,
                'area_join_name' => (string) ($listing->project_area_name ?? ''),
                'coordinate_source' => 'project_area',
            ];
        }

        $mappedProject = $this->coordsFromAreaNameMapping($listing->project_area_name ?? null);
        if ($mappedProject !== null) {
            return [
                'latitude' => $mappedProject['latitude'],
                'longitude' => $mappedProject['longitude'],
                'map_area_id' => isset($listing->project_area_id) ? (int) $listing->project_area_id : null,
                'area_join_name' => (string) ($listing->project_area_name ?? ''),
                'coordinate_source' => 'area_mapping',
            ];
        }

        $label = trim((string) ($listing->project_join_title ?? ''));
        if ($label === '') {
            $label = trim((string) ($listing->title ?? ''));
        }

        $query = $label === '' ? '' : $label.', UAE';

        // Never call Nominatim during this HTTP request (causes 30s+ timeouts and 500s). Cache-only.
        if ($query !== '' && $this->geocodeFallbackCalls < $maxGeocodeFallbacksPerRequest) {
            $this->geocodeFallbackCalls++;
            $geo = $this->geocoding->geocodeFromCacheOnly($query, 2);
            if ($geo !== null) {
                return [
                    'latitude' => $geo['latitude'],
                    'longitude' => $geo['longitude'],
                    'map_area_id' => null,
                    'area_join_name' => $label,
                    'coordinate_source' => 'geocode',
                ];
            }
        }

        $default = config('area_coordinates.default', []);

        return [
            'latitude' => (float) ($default['latitude'] ?? 24.4539),
            'longitude' => (float) ($default['longitude'] ?? 54.3773),
            'map_area_id' => null,
            'area_join_name' => $label,
            'coordinate_source' => 'default',
        ];
    }

    /**
     * @return array{
     *   latitude: float,
     *   longitude: float,
     *   map_area_id: int|null,
     *   area_join_name: string,
     *   coordinate_source: 'listing_area'|'listing_parent_area'|'listing_grandparent_area'
     * }|null
     */
    private function tryDbAreaRow(
        ?float $lat,
        ?float $lng,
        ?int $areaId,
        string $areaName,
        string $coordinateSource
    ): ?array {
        if ($lat === null || $lng === null) {
            return null;
        }

        return [
            'latitude' => $lat,
            'longitude' => $lng,
            'map_area_id' => $areaId,
            'area_join_name' => $areaName,
            'coordinate_source' => $coordinateSource,
        ];
    }

    private function nullableFloat(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }

        return is_numeric($v) ? (float) $v : null;
    }

    /**
     * @return array{latitude: float, longitude: float}|null
     */
    private function coordsFromAreaNameMapping(?string $areaName): ?array
    {
        $normalized = AreaCoordinateResolver::normalizeAreaName($areaName);
        if ($normalized === '') {
            return null;
        }

        $mapping = config('area_coordinates.mapping', []);

        $tryKeys = [$normalized];
        if (str_ends_with($normalized, ' island')) {
            $tryKeys[] = substr($normalized, 0, -strlen(' island'));
        }
        // Common spelling variant
        if ($normalized === 'zaid city') {
            $tryKeys[] = 'zayed city';
        }

        foreach (array_unique(array_filter($tryKeys)) as $key) {
            if ($key === '') {
                continue;
            }
            if (!isset($mapping[$key])) {
                continue;
            }
            $row = $mapping[$key];

            return [
                'latitude' => (float) $row['latitude'],
                'longitude' => (float) $row['longitude'],
            ];
        }

        return null;
    }
}
