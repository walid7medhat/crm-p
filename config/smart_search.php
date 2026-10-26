<?php

/**
 * Phrases for the read-only global search.
 * Bedroom, area, and property-type maps stay in config/voice_search.php.
 * Values here are matched after SearchText::normalize().
 */
return [

    'offer_phrases' => [
        'create offer',
        'create an offer',
        'generate offer',
        'make an offer',
        'make offer',
        'انشئ عرض',
        'اعمل عرض',
        'سوي عرض',
    ],

    'lead_words' => [
        'lead',
        'leads',
        'ليد',
        'ليدات',
        'الليد',
        'الليدات',
    ],

    'listing_words' => [
        'listing',
        'listings',
        'property',
        'properties',
        'apartment',
        'villa',
        'عقار',
        'عقارات',
        'العقارات',
        'متاح',
        'المتاحة',
        'متاحه',
    ],

    'created_words' => [
        'created',
        'جت',
        'جاء',
        'جاي',
    ],

    'today_words' => [
        'today',
        'النهارده',
    ],

    'yesterday_words' => [
        'yesterday',
        'امبارح',
        'امس',
    ],

    'requirement_words' => [
        'requirement',
        'requirements',
        'طالبين',
        'متطلبات',
    ],

    /**
     * A query that is only one of these words is not run.
     * The popup asks the user to pick a meaning.
     */
    'bare_words' => [
        'lost',
        'dubai',
        'today',
        'offer',
        'النهارده',
        'دبي',
    ],

];
