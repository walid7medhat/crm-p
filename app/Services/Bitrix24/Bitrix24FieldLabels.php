<?php

namespace App\Services\Bitrix24;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Resolves Bitrix24 custom lead field codes (e.g. UF_CRM_1649761345243) to the
 * human label configured for them in Bitrix24 (crm.lead.userfield.list) —
 * without this, the "More Information" section on a lead just shows the raw
 * machine-generated field code, which is meaningless to a user.
 */
class Bitrix24FieldLabels
{
    private const CACHE_KEY = 'bitrix24_lead_userfield_labels';
    private const CACHE_TTL = 86400; // labels rarely change — cache a day

    /**
     * @return array<string,string> FIELD_NAME => human label
     */
    public static function map(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            try {
                $client = new Bitrix24Client();
                $response = $client->call('crm.lead.userfield.list', []);
            } catch (\Throwable $e) {
                Log::warning('Bitrix24FieldLabels: failed to load userfield list: ' . $e->getMessage());
                return [];
            }

            $map = [];
            foreach ($response['result'] ?? [] as $field) {
                $name = $field['FIELD_NAME'] ?? null;
                if (!$name) {
                    continue;
                }

                $label = self::extractLabel($field['EDIT_FORM_LABEL'] ?? null)
                    ?? self::extractLabel($field['LIST_COLUMN_LABEL'] ?? null)
                    ?? self::extractLabel($field['FILTER_LABEL'] ?? null);

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
