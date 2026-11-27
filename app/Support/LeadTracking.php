<?php

namespace App\Support;

use App\Models\Lead;
use App\Models\User;

/**
 * Ad-click tracking that WordPress sends with a website lead (gclid, utm_*, landing_page).
 *
 * Stored in dedicated lead columns, never shown as a form "question", and only visible
 * to super_admin and user #33.
 */
class LeadTracking
{
    /** Lead columns, same as the WordPress payload keys. */
    public const FIELDS = [
        'gclid',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'landing_page',
    ];

    /** Users who see tracking without being super_admin. */
    public const EXTRA_VIEWER_IDS = [33];

    public static function isTrackingKey(?string $key): bool
    {
        return in_array(strtolower(trim((string) $key)), self::FIELDS, true);
    }

    /**
     * Tracking values from a payload, matched case-insensitively ("UTM_Source" works).
     * Only non-empty values; each cut to the column size.
     *
     * @return array<string, string>
     */
    public static function extract(array $payload): array
    {
        $out = [];
        foreach ($payload as $key => $value) {
            $field = strtolower(trim((string) $key));
            if (! in_array($field, self::FIELDS, true) || ! is_scalar($value)) {
                continue;
            }
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            $out[$field] = mb_substr($value, 0, $field === 'landing_page' ? 2000 : 500);
        }

        return $out;
    }

    public static function canView(?User $user): bool
    {
        return $user !== null
            && (in_array((int) $user->id, self::EXTRA_VIEWER_IDS, true) || $user->hasRole('super_admin'));
    }

    /** @return array<string, string|null>|null null when the lead has no tracking at all */
    public static function forLead(Lead $lead): ?array
    {
        $values = [];
        foreach (self::FIELDS as $field) {
            $values[$field] = $lead->getAttribute($field);
        }

        return array_filter($values, fn ($v) => $v !== null && $v !== '') ? $values : null;
    }
}
