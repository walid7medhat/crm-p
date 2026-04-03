<?php

namespace App\Services;

use App\Models\Area;
use Illuminate\Support\Facades\Log;

class AreaCoordinateResolver
{
    public function __construct(
        private GeocodingService $geocoding
    ) {}

    /**
     * Normalize area/community name for dictionary lookup.
     */
    public static function normalizeAreaName(?string $name): string
    {
        if ($name === null || $name === '') {
            return '';
        }

        $s = mb_strtolower(trim($name), 'UTF-8');
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        return trim($s);
    }

    /**
     * Resolve coordinates for an area name: mapping → geocode (cached) → default UAE center.
     * Always returns latitude/longitude (never null).
     *
     * @param  string|null  $geocodeCity  Parent city (e.g. Abu Dhabi) — avoids appending "Dubai" to AD areas
     * @param  string|null  $areaType     areas.type (country|city|area|…)
     * @return array{latitude: float, longitude: float, source: string}
     */
    public function resolve(?string $areaName, ?string $geocodeCity = null, ?string $areaType = null): array
    {
        $normalized = self::normalizeAreaName($areaName);

        if ($normalized === '') {
            return $this->defaultCoords('empty_area_name');
        }

        $mapping = config('area_coordinates.mapping', []);
        if (isset($mapping[$normalized])) {
            $row = $mapping[$normalized];

            return [
                'latitude' => (float) $row['latitude'],
                'longitude' => (float) $row['longitude'],
                'source' => 'mapping',
            ];
        }

        foreach ($this->aliasKeys($normalized) as $alias) {
            if (isset($mapping[$alias])) {
                $row = $mapping[$alias];

                return [
                    'latitude' => (float) $row['latitude'],
                    'longitude' => (float) $row['longitude'],
                    'source' => 'mapping_alias',
                ];
            }
        }

        $country = (string) config('area_coordinates.fallback_country', 'UAE');
        $fallbackCity = (string) config('area_coordinates.fallback_city', 'Dubai');

        $query = $this->buildGeocodeQuery($areaName, $geocodeCity, $areaType, $country, $fallbackCity);

        $geo = $this->geocoding->geocodeAreaFallback($query);
        if ($geo !== null) {
            return [
                'latitude' => $geo['latitude'],
                'longitude' => $geo['longitude'],
                'source' => 'geocode',
            ];
        }

        Log::warning('Area coordinate resolution fell back to default', [
            'area_name' => $areaName,
            'normalized' => $normalized,
            'geocode_query' => $query,
        ]);

        return $this->defaultCoords('geocode_failed');
    }

    /**
     * Persist coordinates on an area row only when latitude OR longitude is missing.
     * Does not overwrite when both values are already set.
     */
    public function fillAreaCoordinatesIfMissing(Area $area, bool $force = false): bool
    {
        if (!$force && $area->latitude !== null && $area->longitude !== null) {
            return false;
        }

        $area->loadMissing('parent.parent.parent.parent');
        $fallbackCity = (string) config('area_coordinates.fallback_city', 'Dubai');
        $cityContext = $area->parentCityName() ?? $fallbackCity;

        $resolved = $this->resolve($area->name, $cityContext, $area->type);
        $area->latitude = $resolved['latitude'];
        $area->longitude = $resolved['longitude'];
        $area->saveQuietly();

        return true;
    }

    private function buildGeocodeQuery(
        string $areaName,
        ?string $geocodeCity,
        ?string $areaType,
        string $country,
        string $fallbackCity
    ): string {
        if ($areaType === 'country') {
            return 'United Arab Emirates';
        }

        $city = $geocodeCity ?: $fallbackCity;

        if ($areaType === 'city') {
            return $areaName.', '.$country;
        }

        if (self::normalizeAreaName($areaName) === self::normalizeAreaName($city)) {
            return $areaName.', '.$country;
        }

        return $areaName.', '.$city.', '.$country;
    }

    /**
     * @return array<int, string>
     */
    private function aliasKeys(string $normalized): array
    {
        $aliases = [];

        if (str_ends_with($normalized, ' island')) {
            $aliases[] = substr($normalized, 0, -strlen(' island'));
        }

        return array_filter(array_unique($aliases));
    }

    /**
     * @return array{latitude: float, longitude: float, source: string}
     */
    private function defaultCoords(string $reason): array
    {
        $d = config('area_coordinates.default', []);

        return [
            'latitude' => (float) ($d['latitude'] ?? 24.4539),
            'longitude' => (float) ($d['longitude'] ?? 54.3773),
            'source' => 'default:'.$reason,
        ];
    }
}
