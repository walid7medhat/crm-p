<?php

namespace App\Services\Reports;

use App\Models\Deal;
use App\Models\User;
use App\Support\DealCommissionDistribution;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Accountant commission sheet (deal list → Excel), laid out like the finance team's
 * manual workbook. One row per deal, using the same visibility + filters as the deal list.
 *
 * Not stored in the CRM, so exported blank for the accountant to fill:
 *   BS Paid / BS Payable, Listing Coordinator, Team Leader Share, TL Paid / TL Payable,
 *   Senior Share, SS Paid / SS Payable, Notes.
 *
 * Broker Share: the deal's saved commission split (agents total) when it has one,
 * otherwise the agent's profile commission % of the gross commission — the same rule as
 * the Owner Financial Report, so both show the same numbers.
 */
class DealCommissionSheet
{
    /** user #30 — same extra viewer as the Owner Financial Report. */
    public const EXTRA_USER_IDS = OwnerFinancialReport::EXTRA_USER_IDS;

    public const DEAL_TYPE_LABELS = [
        'primary' => 'Primary',
        'secondary' => 'Resale',
        'rental' => 'Rental',
    ];

    /** @var array<int, array{parent_id:?int, name:string}>|null */
    private ?array $users = null;

    /** @var array<int, true>|null */
    private ?array $teamLeadIds = null;

    public static function canExport(?User $user): bool
    {
        return $user !== null && (
            $user->hasRole('super_admin')
            || $user->isAccountant()
            || in_array((int) $user->id, self::EXTRA_USER_IDS, true)
        );
    }

    /** Rows for every deal the user sees in the deal list with these filters (all pages). */
    public function rows(Request $request, User $user): array
    {
        $deals = Deal::query()
            ->with([
                'parties',
                'documents:id,deal_id,document_type',
                'properties.propertyType:id,name',
                'properties.project:id,title',
                'properties.developer:id,name',
                'properties.area:id,name',
                'properties.listing:id,agent_id',
                'properties.listing.agent:id,name,display_name',
                'listing:id,agent_id',
                'listing.agent:id,name,display_name',
                'responsiblePerson:id,name,display_name,parent_id,commission_percentage',
                'responsiblePerson.employeeProfile:id,user_id,employee_code',
            ])
            ->visibleFor($user)
            ->filter($request)
            ->orderBy('created_at', 'desc')
            ->get();

        return $deals->map(fn (Deal $deal) => $this->row($deal))->all();
    }

    private function row(Deal $deal): array
    {
        $agent = $deal->responsiblePerson;
        $props = $deal->properties;
        $isRental = $deal->deal_type === 'rental';

        $buyer = $this->party($deal, $isRental ? 'tenant' : 'buyer');
        $seller = $isRental ? $this->party($deal, 'landlord') : $this->party($deal, 'seller');
        $sellerName = $seller
            ? trim($seller->first_name.' '.$seller->last_name)
            : ($deal->deal_type === 'primary' ? $this->join($props->map(fn ($p) => $p->developer?->name)) : null);

        $amount = $deal->deal_total_amount !== null ? (float) $deal->deal_total_amount : null;
        $rate = $deal->deal_commission !== null ? (float) $deal->deal_commission : null;
        $gross = ($amount !== null && $rate !== null) ? round($amount * $rate / 100, 2) : null;

        $dist = DealCommissionDistribution::fromDeal($deal);
        $referral = ($dist && ! empty($dist['external_agency']['enabled']))
            ? round((float) ($dist['external_agency']['amount'] ?? 0), 2)
            : null;

        return [
            'date' => ($deal->won_date ?? $deal->created_at)?->timezone('Asia/Dubai'),
            'deal_type' => self::DEAL_TYPE_LABELS[$deal->deal_type] ?? ucfirst((string) $deal->deal_type),
            'team_leader' => $agent ? $this->teamLeaderName((int) $agent->id) : null,
            'emp_code' => $agent?->employeeProfile?->employee_code,
            'agent' => $agent ? ($agent->display_name ?: $agent->name) : null,
            'seller' => $sellerName ?: null,
            'buyer' => $buyer ? trim($buyer->first_name.' '.$buyer->last_name) : null,
            'buyer_phone' => $buyer?->phone,
            'buyer_email' => $buyer?->email,
            'unit_type' => $this->join($props->map(fn ($p) => $p->propertyType?->name)),
            'unit_no' => $this->join($props->map(fn ($p) => $p->unit_no)),
            'rooms' => $this->join($props->map(fn ($p) => $this->rooms($p->bedrooms))),
            'project' => $this->join($props->map(fn ($p) => $p->project?->title ?: $p->area?->name)),
            'source' => $deal->source,
            'amount' => $amount,
            'rate' => $rate !== null ? $rate / 100 : null, // stored as a fraction → Excel % format
            'referral' => $referral,
            'broker_share' => $this->brokerShare($dist, $gross, $agent),
            'listing_agent' => $this->listingAgent($deal),
            'spa' => $this->hasSpa($deal),
        ];
    }

    /** Primary-role party of a type (a joint/secondary buyer never replaces the main one). */
    private function party(Deal $deal, string $type)
    {
        return $deal->parties->first(fn ($p) => $p->party_type === $type && $p->party_role !== 'secondary')
            ?? $deal->parties->firstWhere('party_type', $type);
    }

    private function brokerShare(?array $dist, ?float $gross, ?User $agent): ?float
    {
        if ($dist && is_array($dist['agents'] ?? null) && count($dist['agents']) > 0) {
            $total = (float) ($dist['agents_total_amount'] ?? 0);
            if ($total <= 0) {
                $total = array_sum(array_map(fn ($a) => (float) ($a['amount'] ?? 0), $dist['agents']));
            }

            return round($total, 2);
        }

        if ($gross === null || $agent?->commission_percentage === null) {
            return null;
        }

        return round($gross * (float) $agent->commission_percentage / 100, 2);
    }

    private function listingAgent(Deal $deal): ?string
    {
        $names = $deal->properties
            ->map(fn ($p) => $p->listing?->agent)
            ->push($deal->listing?->agent)
            ->filter()
            ->map(fn ($u) => $u->display_name ?: $u->name);

        return $this->join($names);
    }

    private function hasSpa(Deal $deal): bool
    {
        if ($deal->properties->contains(fn ($p) => ! empty($p->spa_document))) {
            return true;
        }

        return $deal->documents->contains(fn ($d) => in_array(strtolower((string) $d->document_type), ['spa', 'spa_document'], true));
    }

    private function rooms($bedrooms): ?string
    {
        if ($bedrooms === null || $bedrooms === '') {
            return null;
        }
        if (strtolower((string) $bedrooms) === 'studio' || (string) $bedrooms === '0') {
            return 'Studio';
        }

        return $bedrooms.'Br';
    }

    private function join(Collection $values): ?string
    {
        $out = $values->map(fn ($v) => is_string($v) ? trim($v) : $v)->filter(fn ($v) => $v !== null && $v !== '')->unique()->implode(', ');

        return $out !== '' ? $out : null;
    }

    /** Nearest team_lead above the agent in the parent_id tree. */
    private function teamLeaderName(int $userId): ?string
    {
        $this->loadUserTree();

        $seen = [];
        $current = $this->users[$userId]['parent_id'] ?? null;
        while ($current && ! isset($seen[$current])) {
            $seen[$current] = true;
            if (isset($this->teamLeadIds[$current])) {
                return $this->users[$current]['name'] ?? null;
            }
            $current = $this->users[$current]['parent_id'] ?? null;
        }

        return null;
    }

    private function loadUserTree(): void
    {
        if ($this->users !== null) {
            return;
        }

        $this->users = User::query()
            ->get(['id', 'parent_id', 'name', 'display_name'])
            ->mapWithKeys(fn ($u) => [(int) $u->id => [
                'parent_id' => $u->parent_id ? (int) $u->parent_id : null,
                'name' => $u->display_name ?: $u->name,
            ]])
            ->all();

        $this->teamLeadIds = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'team_lead'))
            ->pluck('id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }
}
