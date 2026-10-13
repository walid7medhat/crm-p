<?php

namespace App\Services\Reports;

use App\Models\Lead;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Daily Leads Report — per sales agent, the leads assigned to them on one (UAE) day,
 * counted per source column (same columns as the team's Excel "Daily Leads Report").
 *
 * "Assigned that day" = the lead's current owner, where the lead's latest `assigned`
 * history row is on that day — or, for a lead never re-assigned, it was created that
 * day (new leads are assigned on arrival). Assignment history stores the new owner by
 * name only, so the current responsible person is the agent counted.
 */
class DailyLeadsReport
{
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
        if ($viewer->hasAnyRole(['super_admin', 'admin'])) {
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

        // Latest "assigned" history row per lead.
        $lastAssigned = DB::table('lead_histories')
            ->selectRaw('lead_id, MAX(created_at) as assigned_at')
            ->where('changes->action', 'assigned')
            ->groupBy('lead_id');

        $leads = Lead::query()
            ->leftJoinSub($lastAssigned, 'la', 'la.lead_id', '=', 'leads.id')
            ->whereNotNull('leads.responsible_person_id')
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('la.assigned_at', [$start, $end])
                    ->orWhere(function ($q) use ($start, $end) {
                        $q->whereNull('la.assigned_at')->whereBetween('leads.created_at', [$start, $end]);
                    });
            })
            ->when($scope !== null, fn ($q) => $q->whereIn('leads.responsible_person_id', $scope ?: [0]))
            ->get(['leads.id', 'leads.responsible_person_id', 'leads.lead_source']);

        // Agents: every active sales user in scope (zeros included, like the Excel) plus
        // anyone else in scope who received a lead that day.
        $agentIds = User::role('sales')
            ->where('status', 'active')
            ->when($scope !== null, fn ($q) => $q->whereIn('id', $scope ?: [0]))
            ->pluck('id')
            ->merge($leads->pluck('responsible_person_id'))
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === 1) // "super admin" placeholder owner of unassigned leads
            ->unique();

        $users = User::whereIn('id', $agentIds)->get(['id', 'name', 'display_name'])->keyBy('id');

        $counts = [];
        $extra = [];
        foreach ($leads as $lead) {
            $agentId = (int) $lead->responsible_person_id;
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
