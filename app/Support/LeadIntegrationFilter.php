<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Lead search "Campaign ID" — super_admin + admin only.
 *
 * What the Meta webhook saves on a lead (IntegrationController::fetchAndStoreLead):
 *  - integration_id → our integrations.id (the integration whose facebook_form_id sent it)
 *  - ad_id          → Meta ad id (Graph `ad_id`)
 *  - meta_lead_id   → Meta leadgen id
 *
 * The value typed in search matches a lead when it is:
 *  - the Meta form id shown in the Integrations table "ID" column
 *    (integrations.facebook_form_id — several integrations can share one form), or
 *  - our integration id (shown there when an integration has no form id), or
 *  - the lead's Meta ad id.
 *
 * Accepts one value or a comma list: ?integration_id=1219288530385917 or ?integration_id=16,18
 */
class LeadIntegrationFilter
{
    public static function canUse($user): bool
    {
        return $user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['super_admin', 'admin']);
    }

    /**
     * Requested values as digit strings (Meta ad ids are up to ~20 digits — keep them as text).
     *
     * @return list<string>
     */
    public static function requestedIds(Request $request): array
    {
        $raw = $request->input('integration_id');
        if ($raw === null || $raw === '') {
            return [];
        }

        $parts = is_array($raw) ? $raw : explode(',', (string) $raw);

        $ids = [];
        foreach ($parts as $part) {
            $value = ltrim(trim((string) $part), '0');
            if ($value !== '' && ctype_digit($value)) {
                $ids[$value] = $value;
            }
        }

        return array_values($ids);
    }

    public static function apply($query, Request $request, $user): void
    {
        if (! self::canUse($user)) {
            return;
        }

        $ids = self::requestedIds($request);
        if ($ids === []) {
            return;
        }

        $table = $query->getModel()->getTable();

        // integration_id is an int column: only ids that fit it.
        $integrationIds = array_values(array_filter(
            array_map('intval', array_filter($ids, fn ($id) => strlen($id) <= 10)),
            fn ($id) => $id > 0 && $id <= 4294967295
        ));

        $query->where(function ($q) use ($table, $ids, $integrationIds) {
            // Meta form id (Integrations table "ID") → every integration with that form.
            $q->whereIn($table.'.integration_id', function ($sub) use ($ids) {
                $sub->select('id')->from('integrations')->whereIn('facebook_form_id', $ids);
            });
            if ($integrationIds !== []) {
                $q->orWhereIn($table.'.integration_id', $integrationIds);
            }
            $q->orWhereIn($table.'.ad_id', $ids);
        });
    }
}
