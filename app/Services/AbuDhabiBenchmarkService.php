<?php

namespace App\Services;

use App\Models\AbuDhabiMarketBenchmark;

class AbuDhabiBenchmarkService
{
    public const ALLOWED_AREAS = ['Saadiyat Island', 'Al Reem Island', 'Yas Island', 'Khalifa City', 'Al Raha'];
    public const ALLOWED_PROPERTY_TYPES = ['Apartment', 'Villa', 'Townhouse'];

    public function dataset(): array
    {
        $rows = AbuDhabiMarketBenchmark::query()->orderBy('benchmark_type')->orderBy('benchmark_key')->get();
        return [
            'city' => $rows->where('benchmark_type', 'city')->values(),
            'areas' => $rows->where('benchmark_type', 'area')->values(),
            'property_types' => $rows->where('benchmark_type', 'property_type')->values(),
        ];
    }

    public function evaluate(float $roiPercent, ?string $area, ?string $propertyType): array
    {
        $city = AbuDhabiMarketBenchmark::query()->where('benchmark_type', 'city')->where('benchmark_key', 'Abu Dhabi')->first();
        $normalizedArea = in_array((string)$area, self::ALLOWED_AREAS, true) ? $area : self::ALLOWED_AREAS[0];
        $normalizedType = in_array((string)$propertyType, self::ALLOWED_PROPERTY_TYPES, true) ? $propertyType : self::ALLOWED_PROPERTY_TYPES[0];
        $areaRow = AbuDhabiMarketBenchmark::query()->where('benchmark_type', 'area')->where('benchmark_key', $normalizedArea)->first();
        $typeRow = AbuDhabiMarketBenchmark::query()->where('benchmark_type', 'property_type')->where('benchmark_key', $normalizedType)->first();

        $cityStatus = $this->classify($roiPercent, $city?->avg_roi_percent);
        $areaStatus = $this->classify($roiPercent, $areaRow?->avg_roi_percent);
        $typeStatus = $this->classify($roiPercent, $typeRow?->avg_roi_percent);

        return [
            'city' => $cityStatus,
            'area' => $areaStatus,
            'property_type' => $typeStatus,
            // Canonical benchmark status for this product is area-based.
            'overall' => $areaStatus,
            'area_key' => $normalizedArea,
            'property_type_key' => $normalizedType,
        ];
    }

    private function classify(float $actual, ?float $benchmark): array
    {
        if ($benchmark === null) {
            return ['label' => 'Market Average', 'emoji' => '⚖️', 'benchmark' => null];
        }
        $relativeDiff = $benchmark != 0.0 ? (($actual - $benchmark) / $benchmark) : 0.0;
        if ($relativeDiff >= 0.10) {
            return ['label' => 'Above Market', 'emoji' => '🔥', 'benchmark' => $benchmark];
        }
        if ($relativeDiff <= -0.10) {
            return ['label' => 'Below Market', 'emoji' => '⚠️', 'benchmark' => $benchmark];
        }
        return ['label' => 'Market Average', 'emoji' => '⚖️', 'benchmark' => $benchmark];
    }
}
