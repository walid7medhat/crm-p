<?php

namespace App\Support;

use App\Models\Lead;
use Illuminate\Http\Request;

/**
 * Intelligent lead phone search.
 *
 * work_phone is matched through the indexed generated column work_phone_digits
 * (formatting already stripped). work_phone_2 has no generated column, so the same
 * strip is applied in SQL. Comparisons are prefix matches — no leading wildcard.
 *
 * UAE detection is length-aware. A short prefix such as 050 or 971 is not treated
 * as a UAE number unless the stored value has a real UAE shape. A 10-digit number
 * starting with 971 is a NANP number (Oregon), not UAE.
 */
class PhoneSearch
{
    /** @var list<string> */
    private const UAE_MOBILE = ['50', '52', '54', '55', '56', '58'];

    /** @var list<string> */
    private const UAE_AREAS = ['2', '3', '4', '6', '7', '9'];

    public static function digits(?string $phone): string
    {
        return Lead::phoneDigits($phone);
    }

    public static function isUae(?string $phone): bool
    {
        return self::isUaeDigits(self::digits($phone));
    }

    /**
     * True only for a complete UAE number: +971 / 971 / 00971, or a local mobile
     * (05x, 10 digits) or landline (02/03/04/06/07/09, 9 digits).
     */
    public static function isUaeDigits(string $digits): bool
    {
        if ($digits === '') {
            return false;
        }

        foreach (self::strictRules() as [$prefix, $length]) {
            if (strlen($digits) === $length && str_starts_with($digits, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * "+971" / "00971" / "971" with nothing after the country code.
     * That means "UAE numbers", including local 05… and 04… forms.
     */
    public static function isBareUaeCode(?string $raw): bool
    {
        return in_array(self::digits($raw), ['971', '00971'], true);
    }

    /**
     * Prefixes to seek for a number the user is typing.
     * $dial is the country the user picked (digit string, no +). Null when they
     * did not pick one, or when the typed value already carries + / 00.
     *
     * @return list<string>
     */
    public static function typedVariants(?string $raw, ?string $dial = null): array
    {
        $digits = self::digits($raw);
        if ($digits === '' || self::isBareUaeCode($raw)) {
            return [];
        }

        $variants = [$digits];
        $core = $digits;
        if (str_starts_with($digits, '00') && strlen($digits) > 2) {
            $core = substr($digits, 2);
            $variants[] = $core;
        }

        $hadPlus = str_contains((string) $raw, '+');

        if ($dial !== null && $dial !== '' && ! $hadPlus && ! str_starts_with($digits, '00')) {
            self::addDialVariants($variants, $core, $dial);
        } else {
            self::addInferredVariants($variants, $core, $hadPlus);
        }

        if (! str_starts_with($core, '0') && strlen($core) >= 2) {
            $variants[] = '00'.$core;
        }

        $variants = array_values(array_unique(array_filter(
            $variants,
            fn ($variant) => is_string($variant) && strlen($variant) >= 2 && strlen($variant) <= 64
        )));

        return array_slice($variants, 0, 12);
    }

    public static function applyWorkPhone($query, Request $request): void
    {
        if (! $request->filled('work_phone')) {
            return;
        }

        $raw = (string) $request->input('work_phone');

        if (self::isBareUaeCode($raw)) {
            self::applyUaeCountry($query);

            return;
        }

        $dial = null;
        if (! LeadCountryCodeFilter::wantsInternational($request)) {
            $codes = LeadCountryCodeFilter::requestedCodes($request);
            $explicit = str_contains($raw, '+') || str_starts_with(self::digits($raw), '00');
            if (! $explicit && count($codes) === 1) {
                $dial = $codes[0];
            }
        }

        $variants = self::typedVariants($raw, $dial);
        if ($variants === []) {
            // One digit is too broad and too ambiguous. Match nothing until
            // the user has typed a real prefix, instead of returning every lead.
            $query->whereRaw('1 = 0');

            return;
        }

        $table = $query->getModel()->getTable();
        $phone2 = self::digitsExpr($table.'.work_phone_2');

        $query->where(function ($outer) use ($table, $phone2, $variants) {
            $outer->whereIn($table.'.id', function ($sub) use ($variants) {
                $sub->select('id')->from('leads')->where(function ($phones) use ($variants) {
                    foreach ($variants as $variant) {
                        $phones->orWhere('work_phone_digits', 'like', $variant.'%');
                    }
                });
            });

            $outer->orWhere(function ($secondary) use ($phone2, $variants) {
                foreach ($variants as $variant) {
                    $secondary->orWhereRaw($phone2.' LIKE ?', [$variant.'%']);
                }
            });
        });
    }

    /**
     * Leads whose primary or secondary phone is a UAE number, in any stored shape.
     * Also keeps the previous "starts with 971" match, except 10-digit NANP numbers.
     */
    public static function applyUaeCountry($query): void
    {
        $table = $query->getModel()->getTable();
        $digits = $table.'.work_phone_digits';
        $phone2 = self::digitsExpr($table.'.work_phone_2');

        [$primary, $primaryBind] = self::broadUaeSql($digits);
        $primaryBind[] = self::broadUaeRegexp();

        $query->whereRaw('('.$primary.' OR '.$phone2.' REGEXP ?)', $primaryBind);
    }

    /**
     * Leads that are not a recognized UAE phone. Empty phones stay included.
     * A number is excluded only when its normalized shape is a full UAE number.
     */
    public static function applyExcludeUae($query): void
    {
        $table = $query->getModel()->getTable();
        $digits = $table.'.work_phone_digits';
        $phone2Col = $table.'.work_phone_2';
        $phone2 = self::digitsExpr($phone2Col);

        [$primary, $primaryBind] = self::strictUaeSql($digits);
        $primaryBind[] = self::strictUaeRegexp();

        $query->whereRaw(
            "({$digits} IS NULL OR {$digits} = '' OR NOT {$primary}) AND ({$phone2Col} IS NULL OR {$phone2Col} = '' OR {$phone2} = '' OR {$phone2} NOT REGEXP ?)",
            $primaryBind
        );
    }

    /**
     * One dial code, OR-ed into the country-code group the caller opened.
     */
    public static function orDialCode($query, string $table, string $code): void
    {
        $digits = $table.'.work_phone_digits';
        $phone2 = self::digitsExpr($table.'.work_phone_2');

        if ($code === '971') {
            [$sql, $bind] = self::broadUaeSql($digits);
            $query->orWhereRaw($sql, $bind);
            $query->orWhereRaw($phone2.' REGEXP ?', [self::broadUaeRegexp()]);

            return;
        }

        $query->orWhereRaw(
            "{$digits} LIKE ? OR {$digits} LIKE ? OR {$phone2} LIKE ? OR {$phone2} LIKE ?",
            [$code.'%', '00'.$code.'%', $code.'%', '00'.$code.'%']
        );
    }

    /**
     * Same characters the generated work_phone_digits column strips.
     */
    public static function digitsExpr(string $column): string
    {
        if (! preg_match('/^[A-Za-z0-9_]+(\.[A-Za-z0-9_]+)?$/', $column)) {
            throw new \InvalidArgumentException('Unexpected phone column');
        }

        $expr = $column;
        foreach (['+', ' ', '-', '(', ')', '.', '/'] as $char) {
            $expr = "REPLACE({$expr}, '{$char}', '')";
        }

        return "LEFT({$expr}, 64)";
    }

    /**
     * @param  list<string>  $variants
     */
    private static function addDialVariants(array &$variants, string $core, string $dial): void
    {
        $national = $core;
        if (str_starts_with($national, $dial) && strlen($national) > strlen($dial)) {
            $national = substr($national, strlen($dial));
        }
        if (str_starts_with($national, '0')) {
            $national = substr($national, 1);
        }

        if ($national !== '') {
            $variants[] = $national;
            $variants[] = $dial.$national;
            $variants[] = '00'.$dial.$national;
        }

        // Local trunk form is reliable for UAE. Other countries stay on the dial prefix
        // so a short national number is not guessed into a UAE local number.
        if ($dial === '971' && $national !== '') {
            $variants[] = '0'.$national;
        }
    }

    /**
     * @param  list<string>  $variants
     */
    private static function addInferredVariants(array &$variants, string $core, bool $hadPlus): void
    {
        $looksLikeUaeInternational = str_starts_with($core, '971')
            && strlen($core) > 3
            && ! (strlen($core) === 10 && ! $hadPlus);

        if ($looksLikeUaeInternational) {
            $rest = substr($core, 3);
            if (str_starts_with($rest, '0')) {
                $rest = substr($rest, 1);
            }
            if ($rest !== '') {
                $variants[] = '971'.$rest;
                $variants[] = '00971'.$rest;
                $variants[] = '0'.$rest;
            }

            return;
        }

        // Local UAE mobile: 050 / 052 / 054 / 055 / 056 / 058, still at or under 10 digits.
        if (preg_match('/^05(0|2|4|5|6|8)/', $core) && strlen($core) <= 10) {
            $nsn = substr($core, 1);
            $variants[] = '971'.$nsn;
            $variants[] = '00971'.$nsn;

            return;
        }

        // Local UAE landline trunk. "04" is Dubai; "02" Abu Dhabi, and so on.
        // A bare "44" or "4" is not this — those are not a trunk-prefixed landline.
        if (preg_match('/^0(2|3|4|6|7|9)/', $core) && strlen($core) <= 9) {
            $nsn = substr($core, 1);
            $variants[] = '971'.$nsn;
            $variants[] = '00971'.$nsn;

            return;
        }

        // Complete UAE mobile written without the trunk 0 (9 digits).
        // Shorter values such as "52" or "55" are real country codes, so they are not expanded.
        if (strlen($core) === 9 && preg_match('/^5(0|2|4|5|6|8)/', $core)) {
            $variants[] = '0'.$core;
            $variants[] = '971'.$core;
            $variants[] = '00971'.$core;

            return;
        }

        // 50 is not a country code, so a partial "50…" is a UAE mobile being typed.
        if (strlen($core) >= 2 && strlen($core) < 9 && str_starts_with($core, '50')) {
            $variants[] = '0'.$core;
            $variants[] = '971'.$core;
            $variants[] = '00971'.$core;
        }
    }

    /**
     * Full UAE shapes used to exclude UAE from International.
     *
     * @return list<array{0: string, 1: int}>
     */
    private static function strictRules(): array
    {
        $rules = [];
        foreach (self::UAE_MOBILE as $prefix) {
            $rules[] = ['0'.$prefix, 10];
            $rules[] = ['971'.$prefix, 12];
            $rules[] = ['9710'.$prefix, 13];
            $rules[] = ['00971'.$prefix, 14];
            $rules[] = ['009710'.$prefix, 15];
        }
        foreach (self::UAE_AREAS as $area) {
            $rules[] = ['0'.$area, 9];
            $rules[] = ['971'.$area, 11];
            $rules[] = ['9710'.$area, 12];
            $rules[] = ['00971'.$area, 13];
            $rules[] = ['009710'.$area, 14];
        }

        return $rules;
    }

    /**
     * Local trunk forms. International 971 / 00971 are matched separately so a
     * selected UAE filter still finds partial numbers saved with a 971 prefix.
     *
     * @return list<array{0: string, 1: int}>
     */
    private static function localRules(): array
    {
        $rules = [];
        foreach (self::UAE_MOBILE as $prefix) {
            $rules[] = ['0'.$prefix, 10];
        }
        foreach (self::UAE_AREAS as $area) {
            $rules[] = ['0'.$area, 9];
        }

        return $rules;
    }

    /**
     * One pass over a stripped secondary phone. The primary phone stays on
     * indexed LIKE predicates against work_phone_digits.
     */
    private static function strictUaeRegexp(): string
    {
        $mobile = '5(0|2|4|5|6|8)';
        $area = '(2|3|4|6|7|9)';

        return '^('
            .'0'.$mobile.'[0-9]{7}'
            .'|0'.$area.'[0-9]{7}'
            .'|971'.$mobile.'[0-9]{7}'
            .'|9710'.$mobile.'[0-9]{7}'
            .'|971'.$area.'[0-9]{7}'
            .'|9710'.$area.'[0-9]{7}'
            .'|00971'.$mobile.'[0-9]{7}'
            .'|009710'.$mobile.'[0-9]{7}'
            .'|00971'.$area.'[0-9]{7}'
            .'|009710'.$area.'[0-9]{7}'
            .')$';
    }

    private static function broadUaeRegexp(): string
    {
        $mobile = '5(0|2|4|5|6|8)';
        $area = '(2|3|4|6|7|9)';

        return '^('
            .'971(.{0,6}|.{8,})'
            .'|00971.*'
            .'|0'.$mobile.'[0-9]{7}'
            .'|0'.$area.'[0-9]{7}'
            .')$';
    }

    /**
     * @return array{0: string, 1: list<string|int>}
     */
    private static function strictUaeSql(string $expr): array
    {
        return self::rulesSql($expr, self::strictRules());
    }

    /**
     * @return array{0: string, 1: list<string|int>}
     */
    private static function broadUaeSql(string $expr): array
    {
        $parts = [
            "({$expr} LIKE ? AND CHAR_LENGTH({$expr}) <> 10)",
            "({$expr} LIKE ?)",
        ];
        $bindings = ['971%', '00971%'];

        foreach (self::localRules() as [$prefix, $length]) {
            $parts[] = "({$expr} LIKE ? AND CHAR_LENGTH({$expr}) = ?)";
            $bindings[] = $prefix.'%';
            $bindings[] = $length;
        }

        return ['('.implode(' OR ', $parts).')', $bindings];
    }

    /**
     * @param  list<array{0: string, 1: int}>  $rules
     * @return array{0: string, 1: list<string|int>}
     */
    private static function rulesSql(string $expr, array $rules): array
    {
        $parts = [];
        $bindings = [];
        foreach ($rules as [$prefix, $length]) {
            $parts[] = "({$expr} LIKE ? AND CHAR_LENGTH({$expr}) = ?)";
            $bindings[] = $prefix.'%';
            $bindings[] = $length;
        }

        return ['('.implode(' OR ', $parts).')', $bindings];
    }
}
