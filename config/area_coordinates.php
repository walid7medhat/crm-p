<?php

/**
 * Predefined area/community centroids (UAE-focused).
 * Keys MUST be normalized: lowercase, trimmed (see AreaCoordinateResolver::normalizeAreaName).
 * Extend this list as you onboard new communities.
 */
return [

    /** When geocoding fails, prefer Abu Dhabi centroid (this CRM is UAE-wide; override via .env if needed). */
    'default' => [
        'latitude' => (float) env('AREA_COORD_DEFAULT_LAT', 24.4539),
        'longitude' => (float) env('AREA_COORD_DEFAULT_LNG', 54.3773),
    ],

    'fallback_city' => env('AREA_COORD_FALLBACK_CITY', 'Dubai'),
    'fallback_country' => env('AREA_COORD_FALLBACK_COUNTRY', 'UAE'),

    /**
     * area_name (normalized) => centroid
     */
    'mapping' => [
        'uae' => ['latitude' => 23.4241, 'longitude' => 53.8478],
        'abu dhabi' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'yas island' => ['latitude' => 24.49, 'longitude' => 54.605],
        'saadiyat island' => ['latitude' => 24.537, 'longitude' => 54.433],
        'saadiyat' => ['latitude' => 24.537, 'longitude' => 54.433],
        'dubai marina' => ['latitude' => 25.080, 'longitude' => 55.140],
        'business bay' => ['latitude' => 25.185, 'longitude' => 55.265],
        'downtown dubai' => ['latitude' => 25.197, 'longitude' => 55.274],
        'palm jumeirah' => ['latitude' => 25.112, 'longitude' => 55.139],
        'jumeirah village circle' => ['latitude' => 25.056, 'longitude' => 55.207],
        'jvc' => ['latitude' => 25.056, 'longitude' => 55.207],
        'al reem island' => ['latitude' => 24.496, 'longitude' => 54.406],
        'masdar city' => ['latitude' => 24.4269, 'longitude' => 54.6168],
        'al hudayriat island' => ['latitude' => 24.417, 'longitude' => 54.667],
        'fahid island' => ['latitude' => 24.53, 'longitude' => 54.67],
        'zayed city' => ['latitude' => 24.4, 'longitude' => 54.52],
        /** Same centroid; common DB typo */
        'zaid city' => ['latitude' => 24.4, 'longitude' => 54.52],
        'al salam street' => ['latitude' => 24.47, 'longitude' => 54.35],
        'raha beach' => ['latitude' => 24.45, 'longitude' => 54.61],
        'al ghadeer' => ['latitude' => 24.92, 'longitude' => 55.02],
        'al reef' => ['latitude' => 24.46, 'longitude' => 54.68],
        'samha' => ['latitude' => 24.82, 'longitude' => 55.02],
        'al shamkha' => ['latitude' => 24.39, 'longitude' => 54.71],
        /** Abu Dhabi city centroid (placeholder until refined) */
        'ramhan island' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'nurai island' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'al qurm' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'nareel island' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'rawdat al reef' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'ghantoot' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'the marina' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'rabdan' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'baniyas' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'al bahya' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'al jurf' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'khalifa city' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        /** Communities / sub-communities (shared Abu Dhabi centroid; IDs 1606–1654 in areas) */
        'marina district' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'cultural district' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'yas bay' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'city of lights' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'marina square' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'markers district' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'shams abu dhabi' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'tamouh' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'najmat abu dhabi' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'al maryah island' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'bashayer' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'fahid beach district' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'al muneera' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'al muneera island' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'al bandar' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'al zeina' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'luluat al raha' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'alreeman 1 plots' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'hydra village' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'al jurf gardens' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'al jurf gardens ph 2' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'jacob & co. living' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'ohana by the sea' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'al raha gardens' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'golf gardens' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'reportage village' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'hayat boulevard' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'al merief' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'sw4' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'al dahre compound' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'se2' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'manzel alreef 2' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'alreeman 2 plots' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        /** Yas communities (IDs 1686–1695): Abu Dhabi centroid */
        'yas acres' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'west yas' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'west yas plots' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'lea' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'yas riva' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'ansam' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'mayan' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'waldorf astoria residence' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        'gardenia bay' => ['latitude' => 24.4539, 'longitude' => 54.3773],
        /** IDs 1696–1705: export coordinates */
        'waters edge' => ['latitude' => 25.2048, 'longitude' => 55.2708],
        'yas living' => ['latitude' => 25.2048, 'longitude' => 55.2708],
        'sama yas' => ['latitude' => 25.2048, 'longitude' => 55.2708],
        'the sustainable city' => ['latitude' => 25.2048, 'longitude' => 55.2708],
        'yas golf collection' => ['latitude' => 25.2048, 'longitude' => 55.2708],
        'noya' => ['latitude' => 25.2048, 'longitude' => 55.2708],
        'noya viva' => ['latitude' => 25.2048, 'longitude' => 55.2708],
        'noya luma' => ['latitude' => 25.2048, 'longitude' => 55.2708],
        'yas park gate' => ['latitude' => 25.2048, 'longitude' => 55.2708],
        'yas park views' => ['latitude' => 25.2048, 'longitude' => 55.2708],
    ],
];
