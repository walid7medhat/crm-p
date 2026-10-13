<?php

namespace App\Services\Reports;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Daily Leads Report — per active sales agent, the leads a branch admin assigned to them
 * on one (UAE) day, counted per source column (same columns as the team's Excel
 * "Daily Leads Report").
 *
 * Counted: only assignments a user with the branch_admin role made that day — each
 * `assigned` history row whose user_id (who assigned) is a branch admin counts for the
 * person it was assigned to (new_person). Not counted: leads created already set to an
 * agent (no assignment), and assignments by anyone else (team lead, auto-assign, super
 * admin, …). History stores the assigned person by name only; see the agent matching in build().
 *
 * Who may run it: super_admin, user #33 (all agents) and branch_admin (their branch).
 */
class DailyLeadsReport
{
    /** User id with access besides the super_admin / branch_admin roles. */
    public const EXTRA_VIEWER_ID = 33;

    public static function canView(?User $user): bool
    {
        return $user !== null
            && ($user->hasAnyRole(['super_admin', 'branch_admin']) || (int) $user->id === self::EXTRA_VIEWER_ID);
    }

    /**
     * Fixed source columns, in the Excel's column order. Sources matching none of them get
     * their own column, named after the source, so nothing is lost.
     */
    public const COLUMNS = [
        ['key' => 'bayut', 'label' => 'Bayut', 'match' => '/bayut|dubizzle/i'],
        ['key' => 'property_finder', 'label' => 'Property Finder', 'match' => '/property\s*finder|propertyfinder/i'],
        ['key' => 'meta', 'label' => 'Meta', 'match' => '/\bmeta\b|facebook|instagram|lead\s*form/i'],
        ['key' => 'mobile_marketing', 'label' => 'Mobile Marketing', 'match' => '/mobile\s*marketing/i'],
        ['key' => 'landline', 'label' => 'Landline', 'match' => '/land\s*line/i'],
        ['key' => 'snapchat', 'label' => 'Snapchat', 'match' => '/snapchat/i'],
        ['key' => 'allproperties', 'label' => 'Allproperties.com', 'match' => '/allpropert/i'],
        ['key' => 'oiaproperties', 'label' => 'Oiaproperties.com', 'match' => '/oia\s*properties|oiaproperties/i'],
        ['key' => 'company_website', 'label' => 'Company Website', 'match' => '/company\s*website|^web\s*form$|^web$|website/i'],
        ['key' => 'whatsapp_allproperties', 'label' => 'WhatsApp Allproperties', 'match' => '/whats\s*app.*allpropert/i'],
        ['key' => 'whatsapp_oiaproperties', 'label' => 'WhatsApp Oiaproperties', 'match' => '/whats\s*app.*oia/i'],
        ['key' => 'alreemisland', 'label' => 'alreemisland.ae', 'match' => '/alreem/i'],
        ['key' => 'saadiyatgrove', 'label' => 'saadiyatgrove.ae', 'match' => '/saadiyat\s*grove/i'],
        ['key' => 'listing_oia_website', 'label' => 'Listing Oia Website', 'match' => '/listing.*oia/i'],
        ['key' => 'referral', 'label' => 'Referral', 'match' => '/referr|reference/i'],
        ['key' => 'walk_in', 'label' => 'Walk-in Client', 'match' => '/walk\s*-?\s*in/i'],
    ];

    /**
     * Order the patterns are tried in — more specific first, so "WhatsApp Allproperties"
     * isn't taken by Allproperties.com and "Listing Oia Website" not by Oiaproperties.com /
     * Company Website.
     */
    private const MATCH_ORDER = [
        'whatsapp_allproperties', 'whatsapp_oiaproperties', 'listing_oia_website',
        'bayut', 'property_finder', 'meta', 'mobile_marketing', 'landline', 'snapchat',
        'allproperties', 'oiaproperties', 'alreemisland', 'saadiyatgrove',
        'company_website', 'referral', 'walk_in',
    ];

    /** Column key for a lead source (fixed column, or `src:<name>` for an unmatched one). */
    public static function columnFor(?string $source): string
    {
        $source = trim((string) $source);
        if ($source === '') {
            return 'src:Unknown';
        }
        $byKey = array_column(self::COLUMNS, 'match', 'key');
        foreach (self::MATCH_ORDER as $key) {
            if (preg_match($byKey[$key], $source)) {
                return $key;
            }
        }

        return 'src:' . $source;
    }

    /** Agents (user ids) this viewer may report on; null = everyone. */
    public static function scopeUserIds(User $viewer): ?array
    {
        if ($viewer->hasRole('super_admin') || (int) $viewer->id === self::EXTRA_VIEWER_ID) {
            return null;
        }

        return array_map('intval', $viewer->leadScopeUserIds());
    }

    /**
     * @return array{date: string, title: string, columns: array<int, array{key: string, label: string}>, rows: array, totals: array, agents: int}
     */
    public static function build(User $viewer, string $date): array
    {
        $tz = config('app.timezone');
        $day = Carbon::parse($date, $tz);
        $start = $day->copy()->startOfDay()->format('Y-m-d H:i:s');
        $end = $day->copy()->endOfDay()->format('Y-m-d H:i:s');
        $scope = self::scopeUserIds($viewer);

        $branchAdminIds = User::role('branch_admin')->pluck('id')->map(fn ($id) => (int) $id)->all();

        // Only assignments made by a branch admin that day (lead_histories.user_id = the user
        // who assigned). Each one counts for the person the branch admin assigned it to.
        $assignments = DB::table('lead_histories as h')
            ->join('leads', 'leads.id', '=', 'h.lead_id')
            ->whereNull('leads.deleted_at')
            ->where('h.changes->action', 'assigned')
            ->whereBetween('h.created_at', [$start, $end])
            ->whereIn('h.user_id', $branchAdminIds ?: [0])
            ->orderBy('h.id')
            ->get(['h.lead_id', 'h.changes', 'leads.lead_source', 'leads.responsible_person_id']);

        // Agents: active sales users in scope only (zeros included, like the Excel).
        $users = User::role('sales')
            ->where('status', 'active')
            ->when($scope !== null, fn ($q) => $q->whereIn('id', $scope ?: [0]))
            ->get(['id', 'name', 'display_name'])
            ->keyBy('id');

        // History stores who the lead was assigned to by name only. Use the lead's current
        // owner when the name is theirs (also settles two agents with the same name),
        // otherwise the one active agent with that name.
        $nameKey = fn ($name) => mb_strtolower(trim((string) $name));
        $idsByName = $users->groupBy(fn ($u) => $nameKey($u->name))
            ->filter(fn ($group) => $group->count() === 1)
            ->map(fn ($group) => (int) $group->first()->id);

        $leads = collect();
        $seen = [];
        foreach ($assignments as $row) {
            $changes = is_string($row->changes) ? json_decode($row->changes, true) : (array) $row->changes;
            $assignedTo = $nameKey($changes['new_person'] ?? '');
            if ($assignedTo === '') {
                continue;
            }
            $owner = $users->get((int) $row->responsible_person_id);
            $agentId = ($owner && $nameKey($owner->name) === $assignedTo)
                ? (int) $owner->id
                : ($idsByName[$assignedTo] ?? 0);
            // One lead counts once per agent per day, even if assigned to them twice.
            if (! $agentId || isset($seen[$row->lead_id . ':' . $agentId])) {
                continue;
            }
            $seen[$row->lead_id . ':' . $agentId] = true;
            $leads->push((object) ['agent_id' => $agentId, 'lead_source' => $row->lead_source]);
        }

        $counts = [];
        $extra = [];
        foreach ($leads as $lead) {
            $agentId = (int) $lead->agent_id;
            if (! $users->has($agentId)) {
                continue;
            }
            $key = self::columnFor($lead->lead_source);
            if (str_starts_with($key, 'src:')) {
                $extra[$key] = substr($key, 4);
            }
            $counts[$agentId][$key] = ($counts[$agentId][$key] ?? 0) + 1;
        }

        $columns = array_map(fn ($c) => ['key' => $c['key'], 'label' => $c['label']], self::COLUMNS);
        ksort($extra);
        foreach ($extra as $key => $label) {
            $columns[] = ['key' => $key, 'label' => $label];
        }

        $rows = $users
            ->map(function (User $user) use ($counts, $columns) {
                $row = ['agent_id' => $user->id, 'name' => User::resolveDisplayName($user) ?? $user->name, 'counts' => []];
                foreach ($columns as $column) {
                    $row['counts'][$column['key']] = $counts[$user->id][$column['key']] ?? 0;
                }
                $row['total'] = array_sum($row['counts']);

                return $row;
            })
            ->sortBy([['total', 'desc'], ['name', 'asc']])
            ->values()
            ->all();

        $totals = [];
        foreach ($columns as $column) {
            $totals[$column['key']] = array_sum(array_map(fn ($r) => $r['counts'][$column['key']], $rows));
        }

        return [
            'date' => $day->format('Y-m-d'),
            'title' => 'Daily Leads Report_' . $day->format('F j, Y'),
            'columns' => $columns,
            'rows' => $rows,
            'totals' => $totals,
            'grand_total' => array_sum($totals),
            'agents' => count($rows),
        ];
    }
}
