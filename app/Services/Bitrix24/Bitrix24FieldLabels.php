<?php

namespace App\Services\Bitrix24;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Resolves Bitrix24 custom lead field codes (e.g. UF_CRM_1649761345243) to the
 * human label configured for them in Bitrix24 — without this, the "More
 * Information" section on a lead just shows the raw machine-generated field
 * code, which is meaningless to a user.
 *
 * Uses crm.lead.fields (not crm.lead.userfield.list) — on this portal,
 * userfield.list returns the field definitions but no label at all (no
 * EDIT_FORM_LABEL/LIST_COLUMN_LABEL/FILTER_LABEL keys present), whereas
 * crm.lead.fields returns a `title` per field, including custom UF_CRM_*
 * ones. Bitrix24LeadImporter::ensureFieldMaps() already relies on this same
 * endpoint for STATUS_ID/SOURCE_ID enum labels.
 */
class Bitrix24FieldLabels
{
    private const CACHE_KEY = 'bitrix24_lead_field_labels';
    private const CACHE_TTL = 86400; // labels rarely change — cache a day

    /**
     * Manually-curated overrides for fields Bitrix24 never exposes a real label
     * for over REST (checked crm.lead.userfield.list, crm.lead.fields, and
     * crm.webform.list — none carry the CRM Form question text for these).
     * Resolves instantly and has zero dependency on Bitrix being reachable at
     * all. Add to this list whenever a new unlabeled UF_CRM_* code shows up —
     * find the real question text in Bitrix24's own lead view and copy it here.
     *
     * @var array<string,string>
     */
    private const KNOWN_LABELS = [
        'UF_CRM_1649761345243' => 'Purpose you are looking to purchase?',
        'UF_CRM_68D4F6FE4AEAA' => 'How can we contact you?',
    ];

    /**
     * @return array<string,string> FIELD_NAME => human label
     */
    public static function map(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            try {
                $client = new Bitrix24Client();
                $response = $client->call('crm.lead.fields', []);
            } catch (\Throwable $e) {
                Log::warning('Bitrix24FieldLabels: failed to load lead fields: ' . $e->getMessage());
                return [];
            }

            $map = [];
            foreach ($response['result'] ?? [] as $name => $field) {
                if (!is_array($field)) {
                    continue;
                }

                $label = self::extractLabel($field['title'] ?? ($field['formLabel'] ?? null));

                if ($label) {
                    $map[$name] = $label;
                }
            }

            return $map;
        });
    }

    /** Resolve one field code to its human label, or null if unknown. */
    public static function resolve(?string $fieldName): ?string
    {
        if (!$fieldName) {
            return null;
        }

        // Manual overrides first — no Bitrix API call needed, so this still
        // works even if the portal/webhook is ever unreachable.
        if (isset(self::KNOWN_LABELS[$fieldName])) {
            return self::KNOWN_LABELS[$fieldName];
        }

        return self::map()[$fieldName] ?? null;
    }

    /**
     * Bitrix24 label fields come back per-language, e.g. {"en": "...", "1033": "..."}.
     * Prefer English, otherwise take whatever's first.
     */
    private static function extractLabel($raw): ?string
    {
        if (is_string($raw)) {
            $trimmed = trim($raw);
            return $trimmed !== '' ? $trimmed : null;
        }

        if (is_array($raw)) {
            foreach (['en', '1033'] as $key) {
                if (!empty($raw[$key]) && is_string($raw[$key])) {
                    return trim($raw[$key]);
                }
            }
            $first = reset($raw);
            if (is_string($first) && trim($first) !== '') {
                return trim($first);
            }
        }

        return null;
    }
}
