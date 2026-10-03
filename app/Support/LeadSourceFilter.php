<?php

namespace App\Support;

/**
 * Lead "Source" search filter — shared by the leads board (StageController) and the
 * leads list / Lead Pool (LeadController) so both return the same leads.
 *
 * `source` is one value or several. Parent groups expand to their children:
 *   website  → Oiaproperties.com, Allproperties.ae
 *   portal   → propertyfinder, bayut
 *   whatsapp → Whatsapp Oia Properties, Whatsapp All Properties
 *   Lead Form (Meta) → Lead Form, Meta Ads - Lead Form
 *
 * Most sources match `lead_source` exactly (indexed, fast).
 *
 * Portals are stored as free-text sentences in lead_source — "Whatsapp from Property
 * Finder", "Call from Bayut", "Email From Bayut", even with double spaces — so they
 * match by pattern on lead_source, and also on more_information (Bitrix portal leads
 * keep the portal link there instead of in lead_source).
 */
class LeadSourceFilter
{
    /**
     * Micro (project) websites — "Micro Websites" in the search UI. Each domain matches by
     * pattern (they show up as a bare domain, a full URL with www / https / a path, or a
     * page URL in more_information). `not` keeps a domain from also matching another one
     * on the list that contains it (ohana-projects.ae ⊂ form.ohana-projects.ae, ...).
     */
    private const MICRO_WEBSITES = [
        'alrahabeachprojects.ae'       => [],
        'projects-dubai.ae'            => [],
        'athlonbyaldar.ae'             => ['not' => '%rise.athlonbyaldar.ae%'],
        'rise.athlonbyaldar.ae'        => [],
        'beyondprojects.ae'            => [],
        'emiratesdevelopments.ae'      => [],
        'fahidislandprojects.ae'       => [],
        'form.ohana-projects.ae'       => [],
        'ohana-projects.ae'            => ['not' => '%form.ohana-projects.ae%'],
        'ghantootprojects.ae'          => [],
        'hudayriyatprojects.ae'        => [],
        'manchesteryasresidences.ae'   => [],
        'saadiyat-culturaldistrict.ae' => [],
        'yasresidencesbyohana.ae'      => [],
        'burtvilleprojects.ae'         => [],
        'hq-by-rove.ae'                => [],
        'masdarcityprojects.ae'        => [],
        'yascanalbyohana.ae'           => [],
    ];

    /** Columns a micro website can show up in. */
    private const MICRO_WEBSITE_COLUMNS = ['lead_source', 'source_information', 'more_information'];

    private const GROUPS = [
        'website'   => ['website', 'Oiaproperties.com', 'Allproperties.ae'],
        'portal'    => ['portal', 'propertyfinder', 'bayut'],
        'whatsapp'  => ['whatsapp', 'Whatsapp Oia Properties', 'Whatsapp All Properties'],
        // "Meta" in the search UI — Meta leads are stored under both spellings.
        'lead form' => ['Lead Form', 'Meta Ads - Lead Form'],
    ];

    /**
     * Portal value → LIKE patterns. `%` between words tolerates spaces / dashes
     * ("Property Finder", "property-finder", "Property  Finder", "propertyfinder").
     */
    private const PORTAL_PATTERNS = [
        'propertyfinder' => ['%property%finder%'],
        'bayut'          => ['%bayut%'],
    ];

    /** @param  mixed  $source  request('source') — string or array */
    public static function apply($query, $source): void
    {
        $values = array_values(array_filter(
            is_array($source) ? $source : [$source],
            fn ($v) => $v !== null && $v !== ''
        ));
        if (! $values) {
            return;
        }

        $exact = [];
        $microSites = [];
        foreach ($values as $value) {
            $key = strtolower((string) $value);
            // "Micro Websites (all)" → every micro website; a single domain → just that one.
            // "Website (all)" covers the micro websites too (they sit under Website).
            if ($key === 'micro_websites' || $key === 'website') {
                $microSites = array_merge($microSites, array_keys(self::MICRO_WEBSITES));
                if ($key === 'micro_websites') {
                    continue;
                }
            }
            if (array_key_exists($key, self::MICRO_WEBSITES)) {
                $microSites[] = $key;
                continue;
            }
            foreach (self::GROUPS[$key] ?? [$value] as $entry) {
                $exact[] = $entry;
            }
        }
        $exact = array_values(array_unique($exact));
        $microSites = array_values(array_unique($microSites));

        $patterns = [];
        foreach ($exact as $entry) {
            foreach (self::PORTAL_PATTERNS[strtolower($entry)] ?? [] as $pattern) {
                $patterns[] = $pattern;
            }
        }
        $patterns = array_values(array_unique($patterns));

        $query->where(function ($q) use ($exact, $patterns, $microSites) {
            if ($exact) {
                $q->whereIn('lead_source', $exact);
            }
            foreach ($patterns as $pattern) {
                $q->orWhere('lead_source', 'like', $pattern)
                    ->orWhere('more_information', 'like', $pattern);
            }
            foreach ($microSites as $site) {
                $like = '%' . $site . '%';
                $not = self::MICRO_WEBSITES[$site]['not'] ?? null;
                foreach (self::MICRO_WEBSITE_COLUMNS as $column) {
                    $q->orWhere(function ($w) use ($column, $like, $not) {
                        $w->where($column, 'like', $like);
                        if ($not) {
                            $w->where($column, 'not like', $not);
                        }
                    });
                }
            }
        });
    }
}
