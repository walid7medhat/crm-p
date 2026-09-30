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
 *
 * Everything matches `lead_source` exactly (indexed, fast). Portals are the one
 * exception: Bitrix portal leads keep the Property Finder / Bayut link in
 * `more_information` rather than in `lead_source`, so only portal terms also search
 * that text. (Searching the text for every source was slow and wrong — a comment that
 * merely mentioned a word counted as that source.)
 */
class LeadSourceFilter
{
    private const GROUPS = [
        'website'  => ['website', 'Oiaproperties.com', 'Allproperties.ae'],
        'portal'   => ['portal', 'propertyfinder', 'bayut'],
        'whatsapp' => ['whatsapp', 'Whatsapp Oia Properties', 'Whatsapp All Properties'],
    ];

    /** Portal value → text patterns that identify it inside more_information. */
    private const PORTAL_TEXT = [
        'propertyfinder' => ['propertyfinder', 'property-finder'],
        'bayut'          => ['bayut'],
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
        foreach ($values as $value) {
            $key = strtolower((string) $value);
            foreach (self::GROUPS[$key] ?? [$value] as $entry) {
                $exact[] = $entry;
            }
        }
        $exact = array_values(array_unique($exact));

        $textPatterns = [];
        foreach ($exact as $entry) {
            foreach (self::PORTAL_TEXT[strtolower($entry)] ?? [] as $pattern) {
                $textPatterns[] = $pattern;
            }
        }
        $textPatterns = array_values(array_unique($textPatterns));

        $query->where(function ($q) use ($exact, $textPatterns) {
            $q->whereIn('lead_source', $exact);
            foreach ($textPatterns as $pattern) {
                $q->orWhere('more_information', 'like', '%' . $pattern . '%');
            }
        });
    }
}
