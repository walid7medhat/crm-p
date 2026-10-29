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

    /**
     * Apply only when the request explicitly includes commission_distribution.
     * Missing key → leave metadata unchanged.
     */
    public static function viewerCanSee(): bool
    {
        $user = auth()->user();

        return $user && $user->hasAnyRole(['admin', 'super_admin']);
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
                'split_percent' => round((float) ($agent['split_percent'] ?? 0), 2),
                'amount' => round((float) ($agent['amount'] ?? 0), 2),
            ];
        }

        return [
            'gross_amount' => round((float) ($raw['gross_amount'] ?? 0), 2),
            'external_agency' => [
                'enabled' => (bool) ($agencyIn['enabled'] ?? false),
                'name' => (string) ($agencyIn['name'] ?? ''),
                'representing' => (string) ($agencyIn['representing'] ?? ''),
                'split_percent' => round((float) ($agencyIn['split_percent'] ?? 0), 2),
                'amount' => round((float) ($agencyIn['amount'] ?? 0), 2),
                'referral' => (string) ($agencyIn['referral'] ?? ''),
                'mou_issued_by' => (string) ($agencyIn['mou_issued_by'] ?? ''),
            ],
            'agents' => $agents,
            'agents_total_amount' => round((float) ($raw['agents_total_amount'] ?? 0), 2),
            'oia_retained_amount' => round((float) ($raw['oia_retained_amount'] ?? 0), 2),
            'available_after_agency' => round((float) ($raw['available_after_agency'] ?? 0), 2),
        ];
    }
}
