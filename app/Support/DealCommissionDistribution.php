<?php

namespace App\Support;

use App\Models\Deal;
use Illuminate\Http\Request;

/**
 * Optional commission split stored on deals.metadata.commission_distribution.
 * Does not alter deal_total_amount / deal_commission or stage requirements.
 */
class DealCommissionDistribution
{
    public const META_KEY = 'commission_distribution';

    public static function fromDeal(Deal $deal): ?array
    {
        $meta = $deal->metadata;
        if (!is_array($meta)) {
            return null;
        }

        $value = $meta[self::META_KEY] ?? null;

        return is_array($value) ? $value : null;
    }

    /** user id => display name, shared across one request (deal lists render many rows). */
    private static array $agentNameCache = [];

    /**
     * fromDeal() for API output: agents saved with a user_id but an empty name (older
     * saves / the form not sending it) get their name from the user record, so the UI
     * shows "Razan Ahmed" instead of "Agent #125".
     */
    public static function forDisplay(Deal $deal): ?array
    {
        $dist = self::fromDeal($deal);
        if (!$dist || empty($dist['agents']) || !is_array($dist['agents'])) {
            return $dist;
        }

        $dist['agents'] = self::fillAgentNames($dist['agents']);

        return $dist;
    }

    /** Fill empty agent names from users (by user_id); keeps any name already stored. */
    private static function fillAgentNames(array $agents): array
    {
        $missing = collect($agents)
            ->filter(fn ($a) => is_array($a) && !empty($a['user_id']) && trim((string) ($a['name'] ?? '')) === '')
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => array_key_exists($id, self::$agentNameCache))
            ->unique()
            ->values()
            ->all();

        if ($missing) {
            $found = \App\Models\User::query()->whereIn('id', $missing)->get(['id', 'name', 'display_name']);
            foreach ($missing as $id) {
                $user = $found->firstWhere('id', $id);
                self::$agentNameCache[$id] = $user ? ($user->display_name ?: $user->name) : '';
            }
        }

        return array_map(function ($a) {
            if (is_array($a) && !empty($a['user_id']) && trim((string) ($a['name'] ?? '')) === '') {
                $a['name'] = self::$agentNameCache[(int) $a['user_id']] ?? '';
            }

            return $a;
        }, $agents);
    }

    /**
     * Apply only when the request explicitly includes commission_distribution.
     * Missing key â†’ leave metadata unchanged.
     */
    public static function viewerCanSee(): bool
    {
        $user = auth()->user();

        // accountant: sees + edits the distribution on deals of their branch (deal access is
        // already branch-scoped by User::canViewDeal()).
        return $user && $user->hasAnyRole(['admin', 'super_admin', 'accountant']);
    }

    public static function applyFromRequest(Deal $deal, Request $request): bool
    {
        // Only admin / super_admin may write commission distribution.
        if (!self::viewerCanSee()) {
            return false;
        }

        // No-op unless the client explicitly sent the key (keeps other updates untouched).
        if (!array_key_exists('commission_distribution', $request->all())) {
            return false;
        }

        $raw = $request->input('commission_distribution');

        if (is_string($raw)) {
            $trimmed = trim($raw);
            if ($trimmed === '' || strtolower($trimmed) === 'null') {
                return self::clear($deal);
            }
            $decoded = json_decode($trimmed, true);
            $raw = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        if ($raw === null) {
            return self::clear($deal);
        }

        if (!is_array($raw)) {
            return false;
        }

        $sanitized = self::sanitize($raw);
        $meta = is_array($deal->metadata) ? $deal->metadata : [];
        $meta[self::META_KEY] = $sanitized;
        $deal->metadata = $meta;
        $deal->save();

        return true;
    }

    public static function mergeIntoMetadataArray(?array $metadata, $raw): array
    {
        $meta = is_array($metadata) ? $metadata : [];

        if (is_string($raw)) {
            $decoded = json_decode(trim($raw), true);
            $raw = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        if (!is_array($raw)) {
            return $meta;
        }

        $meta[self::META_KEY] = self::sanitize($raw);

        return $meta;
    }

    public static function clear(Deal $deal): bool
    {
        $meta = is_array($deal->metadata) ? $deal->metadata : [];
        if (!array_key_exists(self::META_KEY, $meta)) {
            return false;
        }
        unset($meta[self::META_KEY]);
        $deal->metadata = $meta;
        $deal->save();

        return true;
    }

    /** Number from a raw value; "200,000.00" â†’ 200000.0 ((float) would give 200). */
    private static function num($value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        $str = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', (string) $value));

        return is_numeric($str) ? (float) $str : 0.0;
    }

    public static function sanitize(array $raw): array
    {
        $agencyIn = is_array($raw['external_agency'] ?? null) ? $raw['external_agency'] : [];
        $agentsIn = is_array($raw['agents'] ?? null) ? $raw['agents'] : [];

        $agents = [];
        foreach ($agentsIn as $agent) {
            if (!is_array($agent)) {
                continue;
            }
            $agents[] = [
                'user_id' => isset($agent['user_id']) && $agent['user_id'] !== '' && $agent['user_id'] !== null
                    ? (int) $agent['user_id']
                    : null,
                'name' => isset($agent['name']) ? (string) $agent['name'] : '',
                'split_percent' => round(self::num($agent['split_percent'] ?? 0), 2),
                'amount' => round(self::num($agent['amount'] ?? 0), 2),
            ];
        }

        // Store the agent's name too, so the saved breakdown reads on its own.
        $agents = self::fillAgentNames($agents);

        return [
            'gross_amount' => round(self::num($raw['gross_amount'] ?? 0), 2),
            'external_agency' => [
                'enabled' => (bool) ($agencyIn['enabled'] ?? false),
                'name' => (string) ($agencyIn['name'] ?? ''),
                'representing' => (string) ($agencyIn['representing'] ?? ''),
                'split_percent' => round(self::num($agencyIn['split_percent'] ?? 0), 2),
                'amount' => round(self::num($agencyIn['amount'] ?? 0), 2),
                'referral' => (string) ($agencyIn['referral'] ?? ''),
                'mou_issued_by' => (string) ($agencyIn['mou_issued_by'] ?? ''),
            ],
            'agents' => $agents,
            'agents_total_amount' => round(self::num($raw['agents_total_amount'] ?? 0), 2),
            'oia_retained_amount' => round(self::num($raw['oia_retained_amount'] ?? 0), 2),
            'available_after_agency' => round(self::num($raw['available_after_agency'] ?? 0), 2),
        ];
    }
}
