<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Lead search "Country Code" — super_admin and admin only.
 *
 * Matches leads whose phone (work_phone or work_phone_2) starts with the dial code,
 * written as +971, 971 or 00971. Uses work_phone_digits (indexed, formatting stripped)
 * for the primary phone. UAE (971) also matches local numbers (050…, 04…, and the
 * other UAE mobile and landline trunks) once formatting is stripped.
 *
 * ?country_code=international returns every lead that is not a UAE phone, in any
 * stored shape (+971, 971, 00971, 050…, 04…).
 *
 * Accepts one value or a comma list: ?country_code=971 or ?country_code=+971,+966
 */
class LeadCountryCodeFilter
{
    public static function canUse($user): bool
    {
        return $user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['super_admin', 'admin']);
    }

    /**
     * Requested dial codes as digit strings (1–4 digits, no leading 00 / +).
     *
     * @return list<string>
     */
    public static function requestedCodes(Request $request): array
    {
        $raw = $request->input('country_code');
        if ($raw === null || $raw === '') {
            return [];
        }

        $parts = is_array($raw) ? $raw : explode(',', (string) $raw);

        $codes = [];
        foreach ($parts as $part) {
            $code = preg_replace('/\D/', '', (string) $part);
            if (str_starts_with($code, '00')) {
                $code = substr($code, 2);
            }
            if ($code !== '' && strlen($code) <= 4 && $code[0] !== '0') {
                $codes[$code] = $code;
            }
        }

        return array_values($codes);
    }

    /**
     * Special selector value. Exclusive: mixed with dial codes, International wins.
     */
    public static function wantsInternational(Request $request): bool
    {
        $raw = $request->input('country_code');
        if ($raw === null || $raw === '') {
            return false;
        }

        $parts = is_array($raw) ? $raw : explode(',', (string) $raw);
        foreach ($parts as $part) {
            $token = strtolower(trim((string) $part));
            $token = ltrim($token, '+');
            if (in_array($token, ['international', 'intl', 'non-uae', 'non_uae', '!uae'], true)) {
                return true;
            }
        }

        return false;
    }

    public static function apply($query, Request $request, $user): void
    {
        if (! self::canUse($user)) {
            return;
        }

        if (self::wantsInternational($request)) {
            PhoneSearch::applyExcludeUae($query);

            return;
        }

        $codes = self::requestedCodes($request);
        if ($codes === []) {
            return;
        }

        $table = $query->getModel()->getTable();

        $query->where(function ($q) use ($table, $codes) {
            foreach ($codes as $code) {
                PhoneSearch::orDialCode($q, $table, $code);
            }
        });
    }
}
