<?php

/**
 * Host-based white-label brand configuration.
 *
 * OIA remains the default brand. Luxvant activates only when the request host
 * matches a Luxvant domain. Colors for OIA mirror resources/css/crm-theme.css.
 *
 * Luxvant primary is official (#003c38). Secondary / accent placeholders are
 * marked pending until official secondary values are provided.
 */

return [

    'default' => env('BRAND_DEFAULT', 'oia'),

    /*
    | Reject hosts that are not mapped to any brand when true (production).
    | Local / MAMP hosts should stay in the map or leave this false in .env.
    */
    'reject_unknown_hosts' => (bool) env('BRAND_REJECT_UNKNOWN_HOSTS', false),

    'brands' => [

        'oia' => [
            'key' => 'oia',
            'company_name' => 'OIA Properties',
            'company_name_short' => 'OIA',
            'company_name_display' => 'OIA PROPERTIES',
            'product_name' => 'Alt CRM',
            'product_mark' => 'A',
            'document_title' => 'Alt CRM',
            'email_domain' => 'oiaproperties.com',
            'hosts' => [
                'oiaproperties.altcrm.com',
                'oiaproperties.com',
                'www.oiaproperties.com',
                'listings.oiaproperties.com',
                'localhost',
                '127.0.0.1',
                'crm-p.test',
                'crm-p.local',
            ],
            'access' => [
                // Empty = all authenticated users (existing OIA behavior).
                'mode' => 'open',
                'roles' => [],
                'user_ids' => [],
                'emails' => [],
            ],
            'logos' => [
                'primary' => '/assets/images/auth/oia-properties-logo.svg',
                'primary_png' => '/assets/images/auth/oia-properties-logo.png',
                'on_dark' => '/assets/images/LogoWhite.png',
                'product' => '/assets/images/auth/altcrm-logo.png',
                'favicon' => '/assets/images/Fav icon.svg',
                'watermark' => '/oia-properties-logo-watermark.svg',
                'collapsed' => '/assets/images/auth/oia-properties-logo.svg',
            ],
            'colors' => [
                'primary' => '#733E87',
                'primary_hover' => '#5c3270',
                'primary_active' => '#4a285c',
                'primary_rgb' => '115, 62, 135',
                'primary_hover_rgb' => '92, 50, 112',
                'primary_active_rgb' => '74, 40, 92',
                'primary_soft' => '#f7f2f8',
                'primary_border' => '#e7dcec',
                'primary_muted' => '#d4c0de',
                'primary_glow' => '#d4af37',
                'primary_bright' => '#8f5aa3',
                'primary_ink' => '#3f2148',
                'secondary' => '#0B0736',
                'accent' => '#d4af37',
                'background' => '#f3f2f6',
                'background_plain' => '#f6f7f6',
                'surface' => '#ffffff',
                'sidebar' => '#ffffff',
                'text' => '#171717',
                'border' => '#e8e6ee',
                'on_primary' => '#ffffff',
                'crm_bg_tint' => '#f5eef8',
                'gradient_primary' => 'linear-gradient(135deg, rgba(255, 255, 255, 0.16) 0%, rgba(212, 175, 55, 0.38) 100%), rgb(115, 62, 135)',
                'gradient_primary_hover' => 'linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(212, 175, 55, 0.46) 100%), rgb(92, 50, 112)',
                'gradient_primary_active' => 'linear-gradient(135deg, rgba(0, 0, 0, 0.12) 0%, rgba(184, 148, 42, 0.4) 100%), rgb(74, 40, 92)',
            ],
            'copy' => [
                'auth_headline_desktop' => 'A Powerful Digital Workspace for Real Estate Professionals and Growing Businesses',
                'auth_headline_mobile' => "Access OIA'S Exclusive Tools to Boost Your Brokerage",
                'loader_label' => 'Loading Alt CRM',
                'birthday_sub' => 'Warm wishes from OIA Properties',
            ],
        ],

        'luxvant' => [
            'key' => 'luxvant',
            'company_name' => 'Luxvant Properties',
            'company_name_short' => 'LUXVANT',
            'company_name_display' => 'LUXVANT',
            'product_name' => 'Luxvant CRM',
            'product_mark' => 'L',
            'document_title' => 'Luxvant CRM',
            'email_domain' => 'luxvant.com',
            'hosts' => [
                'luxvant.altcrm.com',
                'www.luxvant.altcrm.com',
                // Local white-label testing (add matching /etc/hosts → 127.0.0.1).
                'luxvant.local',
                'luxvant.localhost',
                'luxvant.crm-p.test',
            ],
            'access' => [
                // Platform Super Admin uses the same user record on both domains.
                // Ordinary OIA users are denied unless explicitly allowlisted.
                'mode' => 'allowlist',
                'roles' => ['super_admin'],
                'user_ids' => array_values(array_filter(array_map(
                    'intval',
                    explode(',', (string) env('LUXVANT_ACCESS_USER_IDS', ''))
                ))),
                'emails' => array_values(array_filter(array_map(
                    'strtolower',
                    array_map('trim', explode(',', (string) env('LUXVANT_ACCESS_EMAILS', '')))
                ))),
            ],
            /*
            | CRM data scope on Luxvant hosts only (OIA unchanged).
            | Limits agents / leads / deals to this branch root + descendants.
            | Prefer LUXVANT_CRM_BRANCH_USER_ID in production once known; name is fallback.
            */
            'crm_scope' => [
                'enabled' => (bool) env('LUXVANT_CRM_SCOPE_ENABLED', true),
                'branch_user_id' => (($__luxBranchId = (int) env('LUXVANT_CRM_BRANCH_USER_ID', 0)) > 0)
                    ? $__luxBranchId
                    : null,
                'branch_user_name' => env('LUXVANT_CRM_BRANCH_USER_NAME', 'Admin Luxvant'),
            ],
            'logos' => [
                // Green on light UI chrome; white on dark / auth marketing panels.
                'primary' => '/assets/images/brands/luxvant/logo-green.png',
                'primary_png' => '/assets/images/brands/luxvant/logo-green.png',
                'on_dark' => '/assets/images/brands/luxvant/logo-white.png',
                'product' => '/assets/images/brands/luxvant/logo-white.png',
                'favicon' => '/assets/images/brands/luxvant/favicon.svg',
                'watermark' => '/assets/images/brands/luxvant/logo-green.png',
                'collapsed' => '/assets/images/brands/luxvant/logo-mark.svg',
            ],
            'colors' => [
                // Official primary — Emerald Green
                'primary' => '#003c38',
                'primary_hover' => '#004f49',
                'primary_active' => '#002926',
                'primary_rgb' => '0, 60, 56',
                'primary_hover_rgb' => '0, 79, 73',
                'primary_active_rgb' => '0, 41, 38',
                'primary_soft' => '#e8f2f1',
                'primary_border' => '#c5d9d7',
                'primary_muted' => '#9bbdb9',
                'primary_glow' => '#c9a227', // PLACEHOLDER secondary accent — pending official
                'primary_bright' => '#0a6b63',
                'primary_ink' => '#002421',
                'secondary' => '#0a0a0a', // PLACEHOLDER — pending official secondary
                'accent' => '#c9a227', // PLACEHOLDER — pending official secondary/accent
                'background' => '#f3f5f5',
                'background_plain' => '#f6f7f6',
                'surface' => '#ffffff',
                'sidebar' => '#ffffff',
                'text' => '#171717',
                'border' => '#e3e8e7',
                'on_primary' => '#ffffff',
                'crm_bg_tint' => '#eef5f4',
                'gradient_primary' => 'linear-gradient(135deg, rgba(255, 255, 255, 0.14) 0%, rgba(201, 162, 39, 0.28) 100%), rgb(0, 60, 56)',
                'gradient_primary_hover' => 'linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(201, 162, 39, 0.36) 100%), rgb(0, 79, 73)',
                'gradient_primary_active' => 'linear-gradient(135deg, rgba(0, 0, 0, 0.12) 0%, rgba(160, 128, 30, 0.35) 100%), rgb(0, 41, 38)',
            ],
            'copy' => [
                'auth_headline_desktop' => 'A Powerful Digital Workspace for Real Estate Professionals and Growing Businesses',
                'auth_headline_mobile' => "Access LUXVANT'S Exclusive Tools to Boost Your Brokerage",
                'loader_label' => 'Loading Luxvant CRM',
                'birthday_sub' => 'Warm wishes from Luxvant Properties',
            ],
        ],
    ],
];
