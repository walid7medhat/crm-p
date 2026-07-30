<?php

namespace App\Services;

use App\Models\Area;
use App\Models\PropertyType;
use Illuminate\Support\Facades\Cache;

/**
 * Phase 1: convert voice transcripts into Listing Search filters.
 * Does not run listing queries — callers reuse ListingController::getListingsData().
 */
class VoiceSearchService
{
    /**
     * Parse a natural-language transcript into structured listing filters.
     *
     * @return array{
     *   language: string,
     *   transcript: string,
     *   normalized_transcript: string,
     *   filters: array<string, mixed>,
     *   display: array<string, mixed>,
     *   query_params: array<string, mixed>
     * }
     */
    public function parse(string $transcript, array $existingFilters = []): array
    {
        $raw = trim($transcript);
        $normalized = $this->normalizeText($raw);
        $language = $this->detectLanguage($raw);

        $extracted = [
            'area' => null,
            'property_type' => null,
            'number_of_bedrooms' => null,
            'number_of_bathrooms' => null,
            'min_price' => null,
            'max_price' => null,
            'listing_status' => null,
            'project' => null,
            'developer' => null,
        ];

        $extracted['property_type'] = $this->extractPropertyType($normalized);
        $extracted['number_of_bedrooms'] = $this->extractBedrooms($normalized);
        $extracted['number_of_bathrooms'] = $this->extractBathrooms($normalized);
        $extracted['listing_status'] = $this->extractPurpose($normalized);

        $price = $this->extractPriceRange($normalized, $raw);
        $extracted['min_price'] = $price['min_price'];
        $extracted['max_price'] = $price['max_price'];

        $areaName = $this->extractAreaName($normalized, $raw);
        $extracted['area'] = $areaName;

        // Conversational merge: new utterance overlays existing filters.
        $mergedDisplay = array_merge(
            array_filter($existingFilters, fn ($v) => $v !== null && $v !== ''),
            array_filter($extracted, fn ($v) => $v !== null && $v !== '')
        );

        $queryParams = $this->toListingQueryParams($mergedDisplay);

        return [
            'language' => $language,
            'transcript' => $raw,
            'normalized_transcript' => $normalized,
            'filters' => $mergedDisplay,
            'display' => $mergedDisplay,
            'query_params' => $queryParams,
        ];
    }

    /**
     * Detect dominant language of the transcript.
     */
    public function detectLanguage(string $text): string
    {
        $hasArabic = (bool) preg_match('/\p{Arabic}/u', $text);
        $hasLatin = (bool) preg_match('/[A-Za-z]/', $text);

        if ($hasArabic && $hasLatin) {
            return 'mixed';
        }
        if ($hasArabic) {
            return 'ar';
        }
        if ($hasLatin) {
            return 'en';
        }

        return 'unknown';
    }

    /**
     * Normalize Arabic/English text for dictionary matching.
     */
    public function normalizeText(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');

        // Normalize Arabic alef / yaa / taa marbuta variants.
        $text = str_replace(
            ['أ', 'إ', 'آ', 'ى', 'ة', 'ؤ', 'ئ'],
            ['ا', 'ا', 'ا', 'ي', 'ه', 'و', 'ي'],
            $text
        );

        // Collapse punctuation / extra spaces.
        $text = preg_replace('/[^\p{L}\p{N}\s\.\+]/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * Map display filters to existing ListingController::getListingsData() query keys.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function toListingQueryParams(array $filters): array
    {
        $params = [];

        if (! empty($filters['listing_status'])) {
            $params['listing_status'] = $filters['listing_status'];
        }

        if (isset($filters['number_of_bedrooms']) && $filters['number_of_bedrooms'] !== null && $filters['number_of_bedrooms'] !== '') {
            $beds = $filters['number_of_bedrooms'];
            if ($beds === 0 || $beds === '0' || strcasecmp((string) $beds, 'Studio') === 0) {
                $params['number_of_bedrooms'] = 0;
                $params['number_of_bedrooms_in'] = ['Studio'];
            } else {
                $params['number_of_bedrooms'] = (int) $beds;
                $params['number_of_bedrooms_in'] = [(string) (int) $beds];
            }
        }

        if (isset($filters['number_of_bathrooms']) && $filters['number_of_bathrooms'] !== null && $filters['number_of_bathrooms'] !== '') {
            $baths = (int) $filters['number_of_bathrooms'];
            $params['number_of_bathrooms'] = $baths;
            $params['number_of_bathrooms_in'] = [(string) $baths];
        }

        if (isset($filters['min_price']) && is_numeric($filters['min_price'])) {
            $params['min_price'] = (float) $filters['min_price'];
        }

        if (isset($filters['max_price']) && is_numeric($filters['max_price'])) {
            $params['max_price'] = (float) $filters['max_price'];
        }

        if (! empty($filters['property_type'])) {
            $typeId = $this->resolvePropertyTypeId((string) $filters['property_type']);
            if ($typeId) {
                $params['property_type_id'] = $typeId;
                $params['property_type_ids'] = [$typeId];
            }
        }

        if (! empty($filters['area'])) {
            $areaId = $this->resolveAreaId((string) $filters['area']);
            if ($areaId) {
                $params['area_ids'] = [$areaId];
                $params['area_id'] = $areaId;
            } else {
                // Fallback: free-text search if area not resolved to an ID.
                $params['search'] = (string) $filters['area'];
            }
        }

        if (! empty($filters['project_id'])) {
            $params['project_id'] = (int) $filters['project_id'];
        }

        if (! empty($filters['developer_id'])) {
            $params['developer_id'] = (int) $filters['developer_id'];
        }

        return $params;
    }

    protected function extractPropertyType(string $normalized): ?string
    {
        $map = config('voice_search.property_types', []);
        // Longer keys first for multi-word phrases.
        uksort($map, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        foreach ($map as $term => $canonical) {
            if ($this->containsPhrase($normalized, $this->normalizeText((string) $term))) {
                return $canonical;
            }
        }

        return null;
    }

    protected function extractBedrooms(string $normalized): int|string|null
    {
        $map = config('voice_search.bedrooms', []);
        uksort($map, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        foreach ($map as $term => $value) {
            if ($this->containsPhrase($normalized, $this->normalizeText((string) $term))) {
                return $value === 0 ? 0 : (int) $value;
            }
        }

        // Pattern: "3 bedrooms" / "غرف 2"
        if (preg_match('/(\d+)\s*(?:br|bed|beds|bedroom|bedrooms|غرف|غرفه|غرفة)/u', $normalized, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/(?:br|bed|beds|bedroom|bedrooms|غرف|غرفه|غرفة)\s*(\d+)/u', $normalized, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    protected function extractBathrooms(string $normalized): ?int
    {
        $map = config('voice_search.bathrooms', []);
        uksort($map, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        foreach ($map as $term => $value) {
            if ($this->containsPhrase($normalized, $this->normalizeText((string) $term))) {
                return (int) $value;
            }
        }

        if (preg_match('/(\d+)\s*(?:bath|baths|bathroom|bathrooms|حمام|حمامات)/u', $normalized, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    protected function extractPurpose(string $normalized): ?string
    {
        $map = config('voice_search.purpose', []);
        uksort($map, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        foreach ($map as $term => $value) {
            if ($this->containsPhrase($normalized, $this->normalizeText((string) $term))) {
                return $value;
            }
        }

        return null;
    }

    protected function extractAreaName(string $normalized, string $raw): ?string
    {
        $map = config('voice_search.areas', []);
        uksort($map, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        foreach ($map as $term => $canonical) {
            if ($this->containsPhrase($normalized, $this->normalizeText((string) $term))) {
                return $canonical;
            }
        }

        // Dynamic DB match for known area names (cached briefly).
        $areas = $this->cachedAreaNames();
        foreach ($areas as $name) {
            $n = $this->normalizeText($name);
            if ($n !== '' && $this->containsPhrase($normalized, $n)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Extract min/max price from Arabic and English phrases.
     *
     * @return array{min_price: ?float, max_price: ?float}
     */
    protected function extractPriceRange(string $normalized, string $raw): array
    {
        $min = null;
        $max = null;

        // "under / below / less than / بسعر / اقل من / تحت"
        $isCeiling = (bool) preg_match('/\b(under|below|less than|max|upto|up to)\b/u', $normalized)
            || (bool) preg_match('/(اقل من|أقل من|تحت|بحد اقصى|بحد أقصى|بسعر)/u', $normalized);

        $isFloor = (bool) preg_match('/\b(above|over|more than|min|from|at least)\b/u', $normalized)
            || (bool) preg_match('/(اكثر من|أكثر من|فوق|من سعر)/u', $normalized);

        // "1.5 million" / "1.5m" / "مليون ونص" / "مليون ونصف"
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(?:million|m)\b/u', $normalized, $m)) {
            $amount = ((float) str_replace(',', '.', $m[1])) * 1_000_000;
        } elseif (preg_match('/(\d+(?:[.,]\d+)?)\s*(?:k|thousand)\b/u', $normalized, $m)) {
            $amount = ((float) str_replace(',', '.', $m[1])) * 1_000;
        } elseif (preg_match('/مليون\s*(ونص|ونصف|ونصّ|half)/u', $normalized)) {
            $amount = 1_500_000.0;
        } elseif (preg_match('/(\d+(?:[.,]\d+)?)\s*مليون/u', $normalized, $m)) {
            $amount = ((float) str_replace(',', '.', $m[1])) * 1_000_000;
            if (preg_match('/مليون\s*(ونص|ونصف)/u', $normalized)) {
                $amount += 500_000;
            }
        } elseif (preg_match('/مليون/u', $normalized) && preg_match('/(ونص|ونصف)/u', $normalized)) {
            $amount = 1_500_000.0;
        } elseif (preg_match('/مليون/u', $normalized)) {
            $amount = 1_000_000.0;
        } elseif (preg_match('/(\d{1,3}(?:,\d{3})+|\d{5,})/u', $normalized, $m)) {
            $amount = (float) str_replace(',', '', $m[1]);
        } else {
            $amount = null;
        }

        // Bare "ونص مليون" already handled; also "مليون ونص"
        if ($amount === null && preg_match('/مليون\s*و?\s*(نص|نصف)/u', $normalized)) {
            $amount = 1_500_000.0;
        }

        if ($amount !== null) {
            if ($isFloor && ! $isCeiling) {
                $min = $amount;
            } else {
                // Default for real-estate voice: treat stated price as max budget.
                $max = $amount;
            }
        }

        return [
            'min_price' => $min,
            'max_price' => $max,
        ];
    }

    protected function resolvePropertyTypeId(string $canonicalName): ?int
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('property_types')) {
            return null;
        }

        try {
            $types = Cache::remember('voice_search_property_types_v1', 300, function () {
                return PropertyType::query()
                    ->select('id', 'name')
                    ->get()
                    ->map(fn ($t) => ['id' => (int) $t->id, 'name' => (string) $t->name])
                    ->all();
            });
        } catch (\Throwable $e) {
            return null;
        }

        $needle = $this->normalizeText($canonicalName);
        foreach ($types as $type) {
            if ($this->normalizeText($type['name']) === $needle) {
                return $type['id'];
            }
        }
        foreach ($types as $type) {
            $name = $this->normalizeText($type['name']);
            if ($name !== '' && (str_contains($name, $needle) || str_contains($needle, $name))) {
                return $type['id'];
            }
        }

        return null;
    }

    protected function resolveAreaId(string $canonicalName): ?int
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('areas')) {
            return null;
        }

        try {
            $areas = Cache::remember('voice_search_areas_v1', 300, function () {
                return Area::query()
                    ->select('id', 'name')
                    ->get()
                    ->map(fn ($a) => ['id' => (int) $a->id, 'name' => (string) $a->name])
                    ->all();
            });
        } catch (\Throwable $e) {
            return null;
        }

        $needle = $this->normalizeText($canonicalName);
        foreach ($areas as $area) {
            if ($this->normalizeText($area['name']) === $needle) {
                return $area['id'];
            }
        }
        foreach ($areas as $area) {
            $name = $this->normalizeText($area['name']);
            if ($name !== '' && (str_contains($name, $needle) || str_contains($needle, $name))) {
                return $area['id'];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    protected function cachedAreaNames(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('areas')) {
            return [];
        }

        try {
            return Cache::remember('voice_search_area_names_v1', 300, function () {
                return Area::query()->pluck('name')->filter()->values()->all();
            });
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function containsPhrase(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return false;
        }

        // Word-boundary-ish match that works for Arabic (no \b).
        return (bool) preg_match('/(?:^|\s)'.preg_quote($needle, '/').'(?:\s|$)/u', $haystack)
            || str_contains($haystack, $needle);
    }
}
