<?php

namespace App\Services\Reports;

use App\Models\Lead;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Daily Leads Report — per active sales agent, the leads a branch admin assigned to them
 * on one (UAE) day, counted per source column (same columns as the team's Excel
 * "Daily Leads Report").
 *
 * Counted: a user with the branch_admin role assigned the lead that day (any `assigned`
 * history row that day by them — lead_histories.user_id), or created it that day. It is
 * credited to whoever had it at the end of that day, so a lead a branch admin gave to a
 * team lead who passed it to an agent the same day counts for that agent. Leads only
 * others assigned (team lead, auto-assign, …) are not counted. History stores the new
 * owner by name only; see the agent matching in build().
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

        // Each lead's latest "assigned" history row ever, and the latest one that day.
        $lastEver = DB::table('lead_histories')
            ->selectRaw('lead_id, MAX(id) as id')
            ->where('changes->action', 'assigned')
            ->groupBy('lead_id');
        $lastToday = DB::table('lead_histories')
            ->selectRaw('lead_id, MAX(id) as id')
            ->where('changes->action', 'assigned')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('lead_id');

        // A branch admin handed the lead out that day: any of the day's assignments was made
        // by a branch admin — even if a team lead then passed it to an agent the same day —
        // or a branch admin created it that day. Leads only others assigned are not counted.
        // The agent credited is whoever had it at the end of the day (see below).
        $leads = Lead::query()
            ->leftJoinSub($lastToday, 'lt', 'lt.lead_id', '=', 'leads.id')
            ->leftJoin('lead_histories as ah', 'ah.id', '=', 'lt.id')
            ->leftJoinSub($lastEver, 'le', 'le.lead_id', '=', 'leads.id')
            ->whereNotNull('leads.responsible_person_id')
            ->where(function ($q) use ($start, $end, $branchAdminIds) {
                $q->whereExists(function ($sub) use ($start, $end, $branchAdminIds) {
                    $sub->selectRaw('1')
                        ->from('lead_histories as bh')
                        ->whereColumn('bh.lead_id', 'leads.id')
                        ->where('bh.changes->action', 'assigned')
                        ->whereBetween('bh.created_at', [$start, $end])
                        ->whereIn('bh.user_id', $branchAdminIds ?: [0]);
                })
                    ->orWhere(function ($q) use ($start, $end, $branchAdminIds) {
                        $q->whereBetween('leads.created_at', [$start, $end])
                            ->whereIn('leads.added_by', $branchAdminIds ?: [0]);
                    });
            })
            ->get(['leads.id', 'leads.responsible_person_id', 'leads.lead_source', 'lt.id as day_assign_id', 'le.id as last_assign_id', 'ah.changes as day_changes']);

        // Agents: active sales users in scope only (zeros included, like the Excel).
        $users = User::role('sales')
            ->where('status', 'active')
            ->when($scope !== null, fn ($q) => $q->whereIn('id', $scope ?: [0]))
            ->get(['id', 'name', 'display_name'])
            ->keyBy('id');

        // Who got the lead that day: its current owner — unless it was re-assigned on a later
        // day, then the owner named in that day's assignment row (matched to a unique agent name).
        $idsByName = $users->groupBy(fn ($u) => mb_strtolower(trim((string) $u->name)))
            ->filter(fn ($group) => $group->count() === 1)
            ->map(fn ($group) => (int) $group->first()->id);
        foreach ($leads as $lead) {
            $lead->agent_id = (int) $lead->responsible_person_id;
            if ($lead->day_assign_id && (int) $lead->day_assign_id !== (int) $lead->last_assign_id) {
                $changes = is_string($lead->day_changes) ? json_decode($lead->day_changes, true) : (array) $lead->day_changes;
                $name = mb_strtolower(trim((string) ($changes['new_person'] ?? '')));
                $lead->agent_id = $idsByName[$name] ?? 0;
            }
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
