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
        foreach ($values as $value) {
            $key = strtolower((string) $value);
            foreach (self::GROUPS[$key] ?? [$value] as $entry) {
                $exact[] = $entry;
            }
        }
        $exact = array_values(array_unique($exact));

        $patterns = [];
        foreach ($exact as $entry) {
            foreach (self::PORTAL_PATTERNS[strtolower($entry)] ?? [] as $pattern) {
                $patterns[] = $pattern;
            }
        }
        $patterns = array_values(array_unique($patterns));

        $query->where(function ($q) use ($exact, $patterns) {
            $q->whereIn('lead_source', $exact);
            foreach ($patterns as $pattern) {
                $q->orWhere('lead_source', 'like', $pattern)
                    ->orWhere('more_information', 'like', $pattern);
            }
        });
    }
}
