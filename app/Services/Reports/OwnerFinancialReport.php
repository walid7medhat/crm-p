<?php

namespace App\Services\Reports;

use App\Models\Deal;
use App\Models\Stage;
use App\Models\User;
use App\Support\DealCommissionDistribution;
use App\Support\UaeDateRange;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Owner Financial Intelligence — commission revenue per deal, and totals, from the data
 * the CRM actually stores. Nothing is estimated.
 *
 * Where the numbers come from:
 *   Gross commission = deals.deal_total_amount × deals.deal_commission (a %) / 100
 *
 *   Agent / external split, per deal, from one of two sources (`commission_source`):
 *   1. 'breakdown' — the deal has a commission distribution saved by an admin on the deal
 *      form (deals.metadata.commission_distribution, CommissionDistributionSection.vue):
 *      external = external_agency.amount, agents = agents_total_amount (per-agent rows).
 *   2. 'profile'   — no distribution saved: the same rule as the user profile / Agent
 *      Performance report (AgentPerformanceReportController): the responsible agent gets
 *      users.commission_percentage (set on the user form, default 50) of the gross;
 *      no external agency.
 *   Net company revenue = gross − external − agents   ("OIA retained" / "Company Commission").
 *
 * A deal whose numbers can't be worked out (no amount / rate, no agent %, or a saved
 * breakdown that is out of date or broken) is "incomplete": its gross still counts when
 * known, but its agent / external / net values are NOT shown or summed.
 *
 * Closed = the deal sits in a deal stage named "...Won..." (deals.status is not maintained —
 * every deal is still 'draft'). Deal date = won_date when it was recorded, else created_at.
 *
 * Collections / outstanding / expenses are not reported: there is no payment ledger.
 *
 * Access: super_admin + user #30.
 */
class OwnerFinancialReport
{
    /** Users who may open the report besides super_admin. */
    public const EXTRA_USER_IDS = [30];

    /** AED difference tolerated between the stored breakdown's gross and amount × rate (rounding). */
    public const TOLERANCE = 1.0;

    public const MAX_PER_PAGE = 100;

    /** Sort keys the client may send => column of the financial row query. */
    public const SORTS = [
        'deal_date' => 'deal_date',
        'deal_number' => 'deal_number',
        'deal_amount' => 'deal_amount',
        'gross_commission' => 'gross_commission',
        'agent_commission' => 'agent_commission',
        'external_commission' => 'external_commission',
        'net_revenue' => 'net_revenue',
    ];

    /** Issue code => message. Order = order shown to the user. */
    public const ISSUES = [
        'missing_amount' => 'Deal amount is missing',
        'missing_rate' => 'Commission rate (%) is missing',
        'missing_agent_rate' => 'No commission breakdown on the deal, and the responsible agent has no commission % on their profile',
        'stale_breakdown' => 'Commission breakdown is out of date: its gross no longer matches deal amount × commission rate',
        'missing_agents' => 'No agent allocation in the commission breakdown',
        'agent_unassigned' => 'An agent allocation has no agent selected',
        'external_incomplete' => 'External agency is enabled but its name or amount is missing',
        'over_allocated' => 'Agent + external allocations exceed the gross commission',
    ];

    public const DEAL_TYPES = [
        'primary' => 'Primary (Sale)',
        'secondary' => 'Secondary (Sale)',
        'rental' => 'Rental',
    ];

    private ?array $branches = null;
    private ?array $wonStageIds = null;

    public static function canView(?User $user): bool
    {
        return $user !== null
            && ($user->hasRole('super_admin') || in_array((int) $user->id, self::EXTRA_USER_IDS, true));
    }

    // ===================================================================== filters

    /** Options for the filter controls, from the data in the database. */
    public function options(): array
    {
        $sources = Deal::query()
            ->whereNotNull('source')
            ->where('source', '!=', '')
            ->distinct()
            ->orderBy('source')
            ->pluck('source')
            ->values();

        $types = Deal::query()->distinct()->pluck('deal_type')->filter()->values();

        // Agents = everyone responsible for at least one deal.
        $agents = User::query()
            ->whereIn('id', Deal::query()->whereNotNull('responsible_person_id')->select('responsible_person_id'))
            ->orderByRaw("COALESCE(NULLIF(display_name, ''), name)")
            ->get(['id', 'name', 'display_name', 'avatar'])
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->display_name ?: $u->name,
                'avatar' => $u->avatar ? asset('storage/'.$u->avatar) : null, // same as UserResource
            ])
            ->values();

        return [
            'agents' => $agents,
            'branches' => collect($this->branches())
                ->map(fn ($b) => ['id' => $b['id'], 'name' => $b['name']])
                ->values(),
            'sources' => $sources,
            'deal_types' => $types->map(fn ($t) => ['value' => $t, 'label' => self::DEAL_TYPES[$t] ?? Str::headline($t)])->values(),
            'closed_stages' => Stage::query()->whereIn('id', $this->wonStageIds())->get(['id', 'name', 'deal_type']),
            'issues' => self::ISSUES,
        ];
    }

    /**
     * Branches = the users directly under the root super admin that have a team
     * (e.g. "abu dhabi" #25, "Dubai" #59) — same tree position User::getBranchNode() uses.
     *
     * @return array<int, array{id:int, name:string, user_ids:list<int>}>
     */
    public function branches(): array
    {
        if ($this->branches !== null) {
            return $this->branches;
        }

        $rootIds = User::query()
            ->whereNull('parent_id')
            ->whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))
            ->pluck('id');

        $branches = [];
        foreach (User::query()->whereIn('parent_id', $rootIds)->orderBy('name')->get(['id', 'name', 'display_name', 'parent_id']) as $node) {
            $ids = $node->getAllSubordinatesIds();
            if (count($ids) < 2) {
                continue; // a single user under super admin, not a branch
            }
            $branches[$node->id] = [
                'id' => (int) $node->id,
                'name' => Str::title($node->display_name ?: $node->name),
                'user_ids' => array_map('intval', $ids),
            ];
        }

        return $this->branches = $branches;
    }

    /** user id => branch name */
    private function branchByUser(): array
    {
        $map = [];
        foreach ($this->branches() as $branch) {
            foreach ($branch['user_ids'] as $id) {
                $map[$id] ??= $branch['name'];
            }
        }

        return $map;
    }

    /** @return list<int> */
    public function wonStageIds(): array
    {
        return $this->wonStageIds ??= Stage::query()
            ->where('stage_type', 'deal')
            ->whereRaw('LOWER(name) LIKE ?', ['%won%'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** Deals matching the filters that live on the deals table and its relations. */
    private function baseDeals(Request $request): EloquentBuilder
    {
        $closedOnly = $request->input('deal_status', 'closed') !== 'all';

        return Deal::query()
            ->when($closedOnly, fn ($q) => $q->whereIn('deals.stage_id', $this->wonStageIds() ?: [0]))
            ->when($request->filled('deal_type'), function ($q) use ($request) {
                $type = $request->input('deal_type');
                $types = $type === 'sale' ? ['primary', 'secondary'] : [$type];
                $q->whereIn('deals.deal_type', $types);
            })
            ->when($request->filled('source'), fn ($q) => $q->whereIn('deals.source', (array) $request->input('source')))
            ->when($request->filled('branch_id'), function ($q) use ($request) {
                $branch = $this->branches()[(int) $request->input('branch_id')] ?? null;
                $q->whereIn('deals.responsible_person_id', $branch['user_ids'] ?? [0]);
            })
            ->when($request->filled('min_amount'), fn ($q) => $q->where('deals.deal_total_amount', '>=', (float) $request->input('min_amount')))
            ->when($request->filled('max_amount'), fn ($q) => $q->where('deals.deal_total_amount', '<=', (float) $request->input('max_amount')))
            // Deal date = won_date, else created_at. Written as two index-friendly branches
            // instead of COALESCE(...) so won_date / created_at indexes can be used.
            ->when($request->filled('date_from') || $request->filled('date_to'), function ($q) use ($request) {
                $from = $request->filled('date_from') ? UaeDateRange::start((string) $request->input('date_from')) : null;
                $to = $request->filled('date_to') ? UaeDateRange::end((string) $request->input('date_to')) : null;
                $q->where(function ($w) use ($from, $to) {
                    $w->where(function ($won) use ($from, $to) {
                        $won->whereNotNull('deals.won_date');
                        if ($from) $won->where('deals.won_date', '>=', $from);
                        if ($to) $won->where('deals.won_date', '<=', $to);
                    })->orWhere(function ($created) use ($from, $to) {
                        $created->whereNull('deals.won_date');
                        if ($from) $created->where('deals.created_at', '>=', $from);
                        if ($to) $created->where('deals.created_at', '<=', $to);
                    });
                });
            })
            // One box for everything, or the dedicated agent / client / deal-number fields.
            ->when(self::text($request, 'search') !== '', function ($q) use ($request) {
                $s = self::text($request, 'search');
                $q->where(function ($w) use ($s) {
                    $w->where(fn ($x) => $this->whereDealNumber($x, $s))
                        ->orWhere(fn ($x) => $this->whereAgent($x, $s))
                        ->orWhere(fn ($x) => $this->whereClient($x, $s));
                });
            })
            ->when($request->filled('agent_id'), fn ($q) => $q->where('deals.responsible_person_id', (int) $request->input('agent_id')))
            ->when(self::text($request, 'agent') !== '',fn ($q) => $this->whereAgent($q, self::text($request, 'agent')))
            ->when(self::text($request, 'client') !== '', fn ($q) => $this->whereClient($q, self::text($request, 'client')))
            ->when(self::text($request, 'deal_number') !== '', fn ($q) => $this->whereDealNumber($q, self::text($request, 'deal_number')));
    }

    private static function text(Request $request, string $key): string
    {
        return trim((string) $request->input($key));
    }

    private static function like(string $s): string
    {
        return '%'.addcslashes($s, '%_\\').'%';
    }

    private function whereDealNumber($query, string $s): void
    {
        $query->where('deals.deal_number', 'like', self::like($s));
    }

    private function whereAgent($query, string $s): void
    {
        $like = self::like($s);
        $query->whereHas('responsiblePerson', fn ($u) => $u->where('name', 'like', $like)->orWhere('display_name', 'like', $like));
    }

    /** Client name or phone — deal buyer / tenant / client parties, or the converted lead. */
    private function whereClient($query, string $s): void
    {
        $like = self::like($s);
        // Local format "050 123 4567" must match a stored "+971501234567": drop leading zeros.
        $digits = ltrim(preg_replace('/\D+/', '', $s), '0');
        $phoneLike = strlen($digits) >= 4 ? '%'.$digits.'%' : null;
        $phoneSql = fn (string $col) => "REPLACE(REPLACE(REPLACE(REPLACE({$col}, ' ', ''), '-', ''), '+', ''), '(', '') LIKE ?";

        $query->where(function ($w) use ($like, $phoneLike, $phoneSql) {
            $w->whereHas('parties', function ($p) use ($like, $phoneLike, $phoneSql) {
                $p->whereIn('party_type', ['buyer', 'tenant', 'client'])
                    ->where(function ($pp) use ($like, $phoneLike, $phoneSql) {
                        $pp->whereRaw("CONCAT_WS(' ', first_name, last_name) LIKE ?", [$like])
                            ->orWhere('phone', 'like', $like);
                        if ($phoneLike) {
                            $pp->orWhereRaw($phoneSql('phone'), [$phoneLike]);
                        }
                    });
            })->orWhereHas('lead', function ($l) use ($like, $phoneLike, $phoneSql) {
                $l->where('lead_name', 'like', $like)->orWhere('work_phone', 'like', $like);
                if ($phoneLike) {
                    $l->orWhereRaw($phoneSql('work_phone'), [$phoneLike]);
                }
            });
        });
    }

    // ===================================================================== SQL

    private static function json(string $path): string
    {
        return "JSON_UNQUOTE(JSON_EXTRACT(deals.metadata, '$.".DealCommissionDistribution::META_KEY.$path."'))";
    }

    private static function jsonNumber(string $path): string
    {
        return 'CAST('.self::json($path).' AS DECIMAL(15,2))';
    }

    /**
     * Per-deal financial row, computed in SQL so the table, the KPI totals and the
     * breakdown all use exactly one calculation. Columns: see the select below.
     */
    private function financialRows(EloquentBuilder $deals): QueryBuilder
    {
        $key = DealCommissionDistribution::META_KEY;
        $won = implode(',', $this->wonStageIds() ?: [0]);

        // ---- level 1: raw values per deal
        $raw = (clone $deals)->select([
            'deals.id',
            'deals.deal_number',
        ])->selectRaw(implode(",\n", [
            'COALESCE(deals.won_date, deals.created_at) AS deal_date',
            '(deals.won_date IS NOT NULL) AS has_won_date',
            'deals.deal_total_amount AS deal_amount',
            'deals.deal_commission AS commission_rate',
            "(deals.stage_id IN ({$won})) AS is_closed",
            'CASE WHEN deals.deal_total_amount > 0 AND deals.deal_commission IS NOT NULL'
                .' THEN ROUND(deals.deal_total_amount * deals.deal_commission / 100, 2) END AS gross',
            "(COALESCE(JSON_TYPE(JSON_EXTRACT(deals.metadata, '$.{$key}')), '') = 'OBJECT') AS has_dist",
            'COALESCE('.self::jsonNumber('.gross_amount').', 0) AS dist_gross',
            '(COALESCE('.self::json('.external_agency.enabled').", 'false') IN ('true', '1')) AS ext_on",
            'COALESCE('.self::jsonNumber('.external_agency.amount').', 0) AS ext_amount',
            '(TRIM(COALESCE('.self::json('.external_agency.name').", '')) IN ('', 'null')) AS ext_name_empty",
            "COALESCE(JSON_LENGTH(deals.metadata, '$.{$key}.agents'), 0) AS agents_n",
            "COALESCE(JSON_CONTAINS(JSON_EXTRACT(deals.metadata, '$.{$key}.agents[*].user_id'), 'null'), 0) AS agent_no_user",
            'COALESCE('.self::jsonNumber('.agents_total_amount').', 0) AS agents_total',
            // Responsible agent's commission % from their user profile (fallback source).
            '(SELECT u.commission_percentage FROM users u WHERE u.id = deals.responsible_person_id) AS agent_rate',
        ]));

        // ---- level 2: issues + final values
        $t = self::TOLERANCE;
        $needs = '(COALESCE(r.gross, 0) > 0)';
        $withDist = "{$needs} AND r.has_dist = 1";
        $extEff = '(CASE WHEN r.ext_on = 1 THEN r.ext_amount ELSE 0 END)';
        $flags = [
            'missing_amount' => '(r.deal_amount IS NULL OR r.deal_amount <= 0)',
            'missing_rate' => '(r.commission_rate IS NULL)',
            'missing_agent_rate' => "({$needs} AND r.has_dist = 0 AND r.agent_rate IS NULL)",
            'stale_breakdown' => "({$withDist} AND ABS(r.dist_gross - r.gross) > {$t})",
            'missing_agents' => "({$withDist} AND r.agents_n = 0)",
            'agent_unassigned' => "({$withDist} AND r.agent_no_user = 1)",
            'external_incomplete' => "({$withDist} AND r.ext_on = 1 AND (r.ext_amount <= 0 OR r.ext_name_empty = 1))",
            'over_allocated' => "({$withDist} AND ({$extEff} + r.agents_total) > r.dist_gross + {$t})",
        ];
        $incomplete = '('.implode(' OR ', $flags).')';

        $select = ['r.*'];
        foreach ($flags as $code => $sql) {
            $select[] = "{$sql} AS f_{$code}";
        }
        $select[] = "{$incomplete} AS is_incomplete";
        $select[] = 'r.gross AS gross_commission';
        $select[] = "CASE WHEN r.has_dist = 1 THEN 'breakdown' ELSE 'profile' END AS commission_source";

        // Per source: saved breakdown → its amounts; otherwise the agent's profile % of gross
        // (same rounding as AgentPerformanceReportController) and no external agency.
        $external = "(CASE WHEN r.has_dist = 1 THEN {$extEff} ELSE 0 END)";
        $agents = '(CASE WHEN r.has_dist = 1 THEN r.agents_total ELSE ROUND(r.gross * r.agent_rate / 100, 2) END)';

        // gross = 0 (a legitimate 0% commission) → nothing to allocate.
        $select[] = "CASE WHEN {$incomplete} THEN NULL WHEN r.gross = 0 THEN 0 ELSE {$external} END AS external_commission";
        $select[] = "CASE WHEN {$incomplete} THEN NULL WHEN r.gross = 0 THEN 0 ELSE {$agents} END AS agent_commission";
        $select[] = "CASE WHEN {$incomplete} THEN NULL WHEN r.gross = 0 THEN 0 ELSE ROUND(r.gross - {$external} - {$agents}, 2) END AS net_revenue";

        return DB::query()->fromSub($raw, 'r')->selectRaw(implode(",\n", $select));
    }

    /** financialRows() + the filters that apply to computed values. */
    private function filteredRows(Request $request): QueryBuilder
    {
        return DB::query()
            ->fromSub($this->financialRows($this->baseDeals($request)), 'fr')
            ->when($request->filled('min_commission'), fn ($q) => $q->where('fr.gross_commission', '>=', (float) $request->input('min_commission')))
            ->when($request->filled('max_commission'), fn ($q) => $q->where('fr.gross_commission', '<=', (float) $request->input('max_commission')))
            ->when($request->input('data_status') === 'missing', fn ($q) => $q->where('fr.is_incomplete', 1))
            ->when($request->input('data_status') === 'complete', fn ($q) => $q->where('fr.is_incomplete', 0));
    }

    // ===================================================================== report

    public function summary(Request $request): array
    {
        $s = (clone $this->filteredRows($request))->selectRaw('
            COUNT(*) AS deals,
            SUM(fr.is_closed) AS closed_deals,
            SUM(fr.is_incomplete) AS incomplete_deals,
            SUM(fr.gross_commission IS NOT NULL) AS gross_deals,
            COALESCE(SUM(fr.gross_commission), 0) AS gross_commission,
            COALESCE(SUM(fr.agent_commission), 0) AS agent_commission,
            COALESCE(SUM(fr.external_commission), 0) AS external_commission,
            COALESCE(SUM(fr.net_revenue), 0) AS net_revenue,
            COALESCE(SUM(fr.deal_amount), 0) AS deal_amount
        ')->first();

        $deals = (int) $s->deals;
        $incomplete = (int) $s->incomplete_deals;

        return [
            'deals' => $deals,
            'closed_deals' => (int) $s->closed_deals,
            'incomplete_deals' => $incomplete,
            // How many deals each total is built from (incomplete deals are left out of
            // agent / external / net, so the cards can say so).
            'complete_deals' => $deals - $incomplete,
            'gross_deals' => (int) $s->gross_deals,
            'gross_commission' => round((float) $s->gross_commission, 2),
            'agent_commission' => round((float) $s->agent_commission, 2),
            'external_commission' => round((float) $s->external_commission, 2),
            'net_revenue' => round((float) $s->net_revenue, 2),
            'deal_amount' => round((float) $s->deal_amount, 2),
            'currency' => 'AED',
        ];
    }

    /** @return array{0:string,1:string} sort key, direction */
    private function sorting(Request $request): array
    {
        $sortKey = array_key_exists($request->input('sort'), self::SORTS) ? $request->input('sort') : 'deal_date';
        $dir = strtolower((string) $request->input('direction')) === 'asc' ? 'asc' : 'desc';

        return [$sortKey, $dir];
    }

    private function sortedRows(Request $request): QueryBuilder
    {
        [$sortKey, $dir] = $this->sorting($request);

        return (clone $this->filteredRows($request))
            ->select('fr.*')
            ->orderBy('fr.'.self::SORTS[$sortKey], $dir)
            ->orderBy('fr.id', 'desc');
    }

    /** Every row for the filters (same order as the table), for the Excel export. */
    public function exportRows(Request $request): array
    {
        $branchByUser = $this->branchByUser();
        $out = [];

        $this->sortedRows($request)->chunk(500, function ($chunk) use (&$out, $branchByUser) {
            $deals = $this->loadDeals($chunk->pluck('id')->all());
            foreach ($chunk as $row) {
                $out[] = $this->presentRow($row, $deals->get($row->id), $branchByUser);
            }
        });

        return $out;
    }

    public function rows(Request $request): array
    {
        [$sortKey, $dir] = $this->sorting($request);
        $perPage = max(5, min(self::MAX_PER_PAGE, (int) $request->input('per_page', 25)));

        $page = $this->sortedRows($request)->paginate($perPage);

        $deals = $this->loadDeals(collect($page->items())->pluck('id')->all());
        $branchByUser = $this->branchByUser();

        $rows = collect($page->items())
            ->map(fn ($row) => $this->presentRow($row, $deals->get($row->id), $branchByUser))
            ->values();

        return [
            'rows' => $rows,
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
            'sort' => $sortKey,
            'direction' => $dir,
        ];
    }

    /** Full breakdown of one deal. Null when it doesn't exist. */
    public function breakdown(int $dealId): ?array
    {
        $row = $this->financialRows(Deal::query()->where('deals.id', $dealId))->first();
        if (! $row) {
            return null;
        }

        /** @var Deal $deal */
        $deal = $this->loadDeals([$dealId])->get($dealId);
        $data = $this->presentRow($row, $deal, $this->branchByUser());

        $dist = DealCommissionDistribution::fromDeal($deal);
        $agentIds = collect($dist['agents'] ?? [])->pluck('user_id')->filter()->unique()->all();
        $agentNames = $agentIds
            ? User::query()->whereIn('id', $agentIds)->get(['id', 'name', 'display_name'])->keyBy('id')
            : collect();

        $data['commission_rate'] = $row->commission_rate !== null ? (float) $row->commission_rate : null;
        $data['has_won_date'] = (bool) $row->has_won_date;
        $data['breakdown'] = $dist ? [
            'recorded_gross' => round((float) ($dist['gross_amount'] ?? 0), 2),
            'external_agency' => [
                'enabled' => (bool) ($dist['external_agency']['enabled'] ?? false),
                'name' => $dist['external_agency']['name'] ?? '',
                'representing' => $dist['external_agency']['representing'] ?? '',
                'split_percent' => (float) ($dist['external_agency']['split_percent'] ?? 0),
                'amount' => round((float) ($dist['external_agency']['amount'] ?? 0), 2),
                'referral' => $dist['external_agency']['referral'] ?? '',
                'mou_issued_by' => $dist['external_agency']['mou_issued_by'] ?? '',
            ],
            'agents' => collect($dist['agents'] ?? [])->map(function ($a) use ($agentNames) {
                $user = $a['user_id'] ? $agentNames->get($a['user_id']) : null;

                return [
                    'user_id' => $a['user_id'],
                    'name' => $user ? ($user->display_name ?: $user->name) : ($a['name'] ?: null),
                    'split_percent' => (float) ($a['split_percent'] ?? 0),
                    'amount' => round((float) ($a['amount'] ?? 0), 2),
                ];
            })->values(),
            'agents_total' => round((float) ($dist['agents_total_amount'] ?? 0), 2),
            'available_after_agency' => round((float) ($dist['available_after_agency'] ?? 0), 2),
            'recorded_retained' => round((float) ($dist['oia_retained_amount'] ?? 0), 2),
        ] : null;

        $data['properties'] = $deal->properties->map(fn ($p) => [
            'label' => $this->propertyLabel($p) ?? 'Property #'.($p->sort_order + 1),
            'price' => $deal->deal_type === 'rental'
                ? ($p->rental_price !== null ? (float) $p->rental_price : null)
                : ($p->purchase_price !== null ? (float) $p->purchase_price : null),
        ])->values();

        return $data;
    }

    // ===================================================================== presentation

    private function loadDeals(array $ids): Collection
    {
        if (! $ids) {
            return collect();
        }

        return Deal::query()
            ->whereIn('id', $ids)
            ->with([
                'stage:id,name,color',
                'responsiblePerson:id,name,display_name,avatar,parent_id',
                // Archived (soft-deleted) leads still name the deal's origin.
                'lead' => fn ($q) => $q->withTrashed()->select('id', 'lead_name', 'work_phone', 'deleted_at'),
                'parties' => fn ($q) => $q->whereIn('party_type', ['buyer', 'tenant', 'client'])
                    ->orderByRaw("party_role = 'primary' DESC")
                    ->orderBy('id'),
                'properties' => fn ($q) => $q->setEagerLoads([])
                    ->with(['project:id,title', 'area:id,name']),
            ])
            ->get()
            ->keyBy('id');
    }

    private function propertyLabel($property): ?string
    {
        if (! $property) {
            return null;
        }
        $parts = array_filter([
            $property->project?->title ?: $property->area?->name,
            $property->unit_no ? 'Unit '.$property->unit_no : null,
        ]);

        return $parts ? implode(' · ', $parts) : null;
    }

    private function presentRow(object $row, ?Deal $deal, array $branchByUser): array
    {
        $issues = [];
        foreach (array_keys(self::ISSUES) as $code) {
            if ((int) ($row->{'f_'.$code} ?? 0) === 1) {
                $issues[] = ['code' => $code, 'label' => self::ISSUES[$code]];
            }
        }

        $party = $deal?->parties->first();
        $clientName = $party ? trim($party->first_name.' '.$party->last_name) : null;
        $agent = $deal?->responsiblePerson;
        $properties = $deal?->properties ?? collect();
        $property = $this->propertyLabel($properties->first()) ?: ($deal?->deal_name ?: null);
        if ($property && $properties->count() > 1) {
            $property .= ' (+'.($properties->count() - 1).')';
        }

        $num = fn ($v) => $v === null ? null : round((float) $v, 2);

        return [
            'id' => (int) $row->id,
            'deal_number' => $row->deal_number,
            'lead_id' => $deal?->lead_id ? (int) $deal->lead_id : null,
            'lead_name' => $deal?->lead?->lead_name,
            'lead_archived' => (bool) $deal?->lead?->deleted_at,
            'deal_type' => $deal?->deal_type,
            'deal_type_label' => self::DEAL_TYPES[$deal?->deal_type] ?? $deal?->deal_type,
            'property' => $property,
            'client' => [
                'name' => $clientName ?: ($deal?->lead?->lead_name ?: null),
                'phone' => $party?->phone ?: ($deal?->lead?->work_phone ?: null),
            ],
            'agent' => $agent ? [
                'id' => $agent->id,
                'name' => $agent->display_name ?: $agent->name,
                'avatar' => $agent->avatar,
            ] : null,
            'branch' => $agent ? ($branchByUser[(int) $agent->id] ?? null) : null,
            'source' => $deal?->source,
            'deal_date' => $row->deal_date ? \Carbon\Carbon::parse($row->deal_date)->toDateString() : null,
            'deal_date_is_won' => (bool) $row->has_won_date,
            'stage' => $deal?->stage ? ['name' => $deal->stage->name, 'color' => $deal->stage->color] : null,
            'is_closed' => (bool) $row->is_closed,
            'currency' => $deal?->currency ?: 'AED',
            'deal_amount' => $num($row->deal_amount),
            'commission_rate' => $num($row->commission_rate),
            // 'breakdown' = saved on the deal; 'profile' = agent's commission % from their user profile.
            'commission_source' => $row->commission_source,
            'agent_rate' => $row->commission_source === 'profile' ? $num($row->agent_rate) : null,
            'gross_commission' => $num($row->gross_commission),
            'agent_commission' => $num($row->agent_commission),
            'external_commission' => $num($row->external_commission),
            'net_revenue' => $num($row->net_revenue),
            'is_incomplete' => (bool) $row->is_incomplete,
            'issues' => $issues,
        ];
    }
}
