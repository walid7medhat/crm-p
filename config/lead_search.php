<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ngram FULLTEXT for identity contains-search
    |--------------------------------------------------------------------------
    |
    | When true AND the leads_identity_ngram_ft index exists, LeadTextSearch uses
    | MATCH ... AGAINST (... IN BOOLEAN MODE) for name/email/source/lead_number
    | instead of leading-wildcard LIKE on those columns.
    |
    | Phone-shaped terms still use work_phone_digits (+ legacy secondary columns).
    | Set LEAD_SEARCH_USE_FULLTEXT=false to force the previous LIKE identity path.
    |
    */
    'use_fulltext' => filter_var(env('LEAD_SEARCH_USE_FULLTEXT', true), FILTER_VALIDATE_BOOLEAN),

    /*
    | Index names created by the Phase 2 migration.
    | Split because lead_name uses utf8mb4_general_ci while the others use
    | utf8mb4_unicode_ci (MySQL forbids mixed collations in one FULLTEXT index).
    */
    'fulltext_indexes' => [
        'leads_lead_name_ngram_ft',
        'leads_identity_ngram_ft',
    ],

    /*
    | MATCH groups must list exactly the columns of each FULLTEXT index.
    */
    'fulltext_match_groups' => [
        ['lead_name'],
        ['first_name', 'last_name', 'email', 'lead_source', 'lead_number'],
    ],
];
