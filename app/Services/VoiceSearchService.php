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

        $matchedAreas = [];
        if (! empty($mergedDisplay['area'])) {
            $resolved = $this->resolveAreaMatch((string) $mergedDisplay['area']);
            if ($resolved) {
                // Prefer the REAL DB name so the SearchBar can display/match it.
                $mergedDisplay['area'] = $resolved['name'];
                $matchedAreas[] = $resolved;
                \Log::info('voice_search.area_matched', [
                    'transcript' => $raw,
                    'normalized' => $normalized,
                    'detected_area' => $areaName,
                    'matched_area' => $resolved['name'],
                    'matched_id' => $resolved['id'],
                ]);
            } else {
                \Log::info('voice_search.area_unmatched', [
                    'transcript' => $raw,
                    'normalized' => $normalized,
                    'detected_area' => $areaName,
                ]);
            }
        }

        $queryParams = $this->toListingQueryParams($mergedDisplay, $matchedAreas);

        return [
            'language' => $language,
            'transcript' => $raw,
            'normalized_transcript' => $normalized,
            'filters' => $mergedDisplay,
            'display' => $mergedDisplay,
            'query_params' => $queryParams,
            // Real Area objects for SearchBar selectedArea (same shape as manual pick).
            'matched_areas' => $matchedAreas,
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
     * @param  list<array{id:int,name:string}>  $matchedAreas
     * @return array<string, mixed>
     */
    public function toListingQueryParams(array $filters, array $matchedAreas = []): array
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

        if (! empty($matchedAreas)) {
            $ids = array_values(array_unique(array_map(fn ($a) => (int) $a['id'], $matchedAreas)));
            $params['area_ids'] = $ids;
            $params['area_id'] = $ids[0];
        } elseif (! empty($filters['area'])) {
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

        // Speech / ASR fallbacks (spaces dropped, "sell", Arabic).
        $compact = preg_replace('/\s+/u', '', $normalized) ?? $normalized;
        if (preg_match('/\b(for\s*sale|forsale|to\s*buy|to\s*sell|buying|selling)\b/u', $normalized)
            || str_contains($compact, 'forsale')
            || str_contains($compact, 'tosell')
            || preg_match('/(للبيع|للشراء)/u', $normalized)) {
            return 'sale';
        }
        if (preg_match('/\b(for\s*rent|forrent|to\s*rent|rental|leasing)\b/u', $normalized)
            || str_contains($compact, 'forrent')
            || preg_match('/(للايجار|للإيجار|للايجار)/u', $normalized)) {
            return 'rent';
        }
        // Trailing bare "sale" / "rent" (e.g. "villa sale").
        if (preg_match('/\b(sale|sell|buy)\s*$/u', $normalized)) {
            return 'sale';
        }
        if (preg_match('/\b(rent|rental)\s*$/u', $normalized)) {
            return 'rent';
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

        // Dynamic DB match for known area names (exact phrase in transcript).
        $areas = $this->cachedAreaNames();
        usort($areas, fn ($a, $b) => mb_strlen((string) $b) <=> mb_strlen((string) $a));
        foreach ($areas as $name) {
            $n = $this->normalizeText($name);
            if ($n !== '' && $this->containsPhrase($normalized, $n)) {
                return $name;
            }
        }

        // Fuzzy: after "in" / "في" / "بـ" match spoken fragment to DB via areaMatchKey.
        if (preg_match('/(?:\bin|في|ب)\s+(.+)$/u', $normalized, $m)) {
            $tail = trim((string) $m[1]);
            // Drop trailing price/bed noise.
            $tail = preg_replace('/\b(under|below|less than|max|above|over|with|for)\b.*$/u', '', $tail) ?? $tail;
            $tail = preg_replace('/(تحت|اقل من|أقل من|بسعر|غرف|غرفة|bedroom|bath).*$/u', '', $tail) ?? $tail;
            $tail = trim($tail);
            if ($tail !== '') {
                $resolved = $this->resolveAreaMatch($tail);
                if ($resolved) {
                    return $resolved['name'];
                }
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

    /**
     * Resolve spoken/canonical area text to a REAL Area row.
     *
     * @return array{id:int,name:string,type:?string,subtitle:?string}|null
     */
    public function resolveAreaMatch(string $canonicalName): ?array
    {
        $areaId = $this->resolveAreaId($canonicalName);
        if (! $areaId) {
            return null;
        }

        try {
            $area = Area::query()->select('id', 'name', 'type', 'parent_id')->find($areaId);
        } catch (\Throwable $e) {
            return null;
        }

        if (! $area) {
            return null;
        }

        $subtitle = null;
        try {
            $subtitle = $area->area_title ?? $area->title ?? null;
        } catch (\Throwable $e) {
            $subtitle = null;
        }

        return [
            'id' => (int) $area->id,
            'name' => (string) $area->name,
            'type' => $area->type ? (string) $area->type : null,
            'subtitle' => $subtitle ? (string) $subtitle : null,
        ];
    }

    protected function resolveAreaId(string $canonicalName): ?int
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('areas')) {
            return null;
        }

        try {
            $areas = Cache::remember('voice_search_areas_v2', 300, function () {
                return Area::query()
                    ->select('id', 'name', 'type')
                    ->get()
                    ->map(fn ($a) => [
                        'id' => (int) $a->id,
                        'name' => (string) $a->name,
                        'type' => (string) ($a->type ?? ''),
                    ])
                    ->all();
            });
        } catch (\Throwable $e) {
            return null;
        }

        $needle = $this->normalizeText($canonicalName);
        $needleKey = $this->areaMatchKey($needle);
        if ($needleKey === '') {
            return null;
        }

        $bestId = null;
        $bestScore = 0;

        foreach ($areas as $area) {
            $name = $this->normalizeText($area['name']);
            $nameKey = $this->areaMatchKey($name);
            if ($nameKey === '') {
                continue;
            }

            $score = 0;
            if ($name === $needle || $nameKey === $needleKey) {
                $score = 100;
            } elseif (str_contains($nameKey, $needleKey) || str_contains($needleKey, $nameKey)) {
                // Prefer shorter containment distance (Reem → Al Reem Island over tiny substrings).
                $score = 70 + (int) max(0, 20 - abs(mb_strlen($nameKey) - mb_strlen($needleKey)));
            } else {
                continue;
            }

            // Prefer higher-level places (city/area) over tiny sub_communities when scores tie.
            $typeBoost = match ($area['type'] ?? '') {
                'city' => 3,
                'area' => 2,
                'community' => 1,
                default => 0,
            };
            $score += $typeBoost;

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestId = $area['id'];
            }
        }

        return $bestId;
    }

    /**
     * Strip common prefixes/suffixes so "Reem" ≈ "Al Reem Island" ≈ "الريم".
     */
    protected function areaMatchKey(string $normalized): string
    {
        $text = $normalized;
        // Remove Arabic definite article.
        $text = preg_replace('/^ال/u', '', $text) ?? $text;
        // Remove english "al " / "al-" prefix.
        $text = preg_replace('/^al[\s\-]+/u', '', $text) ?? $text;
        // Remove island / jazira noise.
        $text = preg_replace('/\b(island|isle)\b/u', '', $text) ?? $text;
        $text = preg_replace('/\b(جزيره|جزيرة)\b/u', '', $text) ?? $text;

        // Hudayriyat speech variants: حضريات (Haa+Dad) ≈ حدريات ≈ هدريات — same place.
        $text = preg_replace('/[حهخ]ض?ري[اا]?ت/u', 'حدريات', $text) ?? $text;
        $text = preg_replace('/[هه]دري[اا]?ت/u', 'حدريات', $text) ?? $text;
        $text = str_replace(
            ['حضريات', 'هدريات', 'حدريات', 'hadariyat', 'hedriyat', 'hudriyat'],
            ['حدريات', 'حدريات', 'حدريات', 'hudayriat', 'hudayriat', 'hudayriat'],
            $text
        );

        // Collapse spaces / hyphens.
        $text = preg_replace('/[\s\-]+/u', '', $text) ?? $text;

        // Common transliteration variants for Hudayriyat (DB: Hudayriat).
        $text = str_replace(['hudayriyat', 'hadariyat', 'hedriyat', 'hudriyat'], 'hudayriat', $text);

        return trim($text);
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
            return Cache::remember('voice_search_area_names_v2', 300, function () {
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
