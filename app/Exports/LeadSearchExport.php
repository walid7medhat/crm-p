<?php

namespace App\Exports;

use App\Models\Area;
use App\Models\Lead;
use App\Models\Stage;
use App\Models\User;
use App\Services\LeadTextSearch;
use App\Support\UaeDateRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * "Export Leads" page (super_admin only): the lead search modal's filters, applied the
 * same way the kanban lead search applies them, limited to leads that have an email —
 * as Excel (Lead Name, Name, Email). Read in chunks (FromQuery), so big results don't
 * load into memory at once.
 *
 * Kept separate from the kanban code on purpose: changes here never affect the Leads board.
 */
class LeadSearchExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(private Builder $query)
    {
    }

    /** Shared by the page preview and the Excel download. Newest first. */
    public static function searchQuery(Request $request, User $user): Builder
    {
        $query = Lead::query()
            ->select(['leads.id', 'leads.lead_name', 'leads.first_name', 'leads.last_name', 'leads.email'])
            // Only leads that have an email (blank / spaces-only don't count).
            ->whereNotNull('leads.email')
            ->whereRaw("TRIM(leads.email) <> ''")
            // Lead stages as on the board (never the "lead pool" stage).
            ->whereIn('leads.stage_id', Stage::where('stage_type', 'lead')->where('name', '!=', 'lead pool')->select('id'));

        // Luxvant host: Admin Luxvant branch only (no-op on OIA).
        \App\Support\Brand\BrandCrmScope::constrainLeads($query);
        \App\Support\LeadIntegrationFilter::apply($query, $request, $user);
        \App\Support\LeadCountryCodeFilter::apply($query, $request, $user);

        $query->where(fn ($q) => self::applyFilters($q, $request, $user));

        if ($request->filled('heat')) {
            $query->where('status_lead', $request->heat);
        }

        return $query->orderByDesc('leads.id');
    }

    private static function applyFilters($q, Request $request, User $user): void
    {
        if ($request->filled('changed_by')) {
            $q->whereHas('histories', function ($query) use ($request) {
                $query->where('changes->action', 'stage_changed')
                    ->where('user_id', $request->changed_by);
            });
        }

        // Responsible person + "Inactive Sales" select.
        $inactiveRaw = $request->input('inactive_person_id');
        $inactiveIds = \App\Support\InactiveSales::requestsAll($inactiveRaw)
            ? (\App\Support\InactiveSales::ids($user) ?: [0])
            : self::idList($inactiveRaw);
        $responsibleIds = array_values(array_unique(array_merge(self::idList($request->input('responsible_person_id')), $inactiveIds)));
        if ($responsibleIds) {
            $q->whereIn('responsible_person_id', $responsibleIds);
        }

        if ($request->filled('stage_id')) {
            $q->where('stage_id', $request->stage_id);
        }
        if ($request->filled('email')) {
            $q->where('email', $request->email);
        }
        \App\Support\PhoneSearch::applyWorkPhone($q, $request);
        if ($addedBy = self::idList($request->input('added_by'))) {
            $q->whereIn('added_by', $addedBy);
        }
        UaeDateRange::apply($q, 'created_at', $request->created_from, $request->created_to, $request->created_at);
        UaeDateRange::applyModified($q, $request->modified_from, $request->modified_to, $request->modified_at, self::idList($request->input('modified_by')));

        if ($request->filled('source')) {
            \App\Support\LeadSourceFilter::apply($q, $request->source);
        }
        if ($request->boolean('has_duplicate') && $user->canUseDuplicateFilters()) {
            $q->hasDuplicates();
        }
        if ($request->boolean('has_blacklist') && $user->canUseDuplicateFilters()) {
            $q->hasBlacklistDuplicate();
        }
        if ($request->filled('bedrooms')) {
            $q->where('bedrooms', $request->bedrooms);
        }
        if ($request->filled('source_information')) {
            $q->where('source_information', 'like', "%{$request->source_information}%");
        }
        if ($request->filled('closed')) {
            $closed = Stage::where('stage_type', 'lead')->where('name', 'like', '%Converted%')->orderBy('order', 'desc')->first();
            $q->where('stage_id', $closed?->id ?? 0);
        }
        if ($request->filled('lead_name')) {
            $q->where('lead_name', 'like', "%{$request->lead_name}%");
        }
        if ($request->filled('first_name')) {
            $q->where('first_name', 'like', "%{$request->first_name}%");
        }
        if ($request->filled('lead_branch_source')) {
            $q->where('lead_branch_source', $request->lead_branch_source);
        }

        // Office / team: leads of that user and everyone under them.
        foreach (['office_branch', 'team_id'] as $key) {
            $memberIds = [];
            foreach (self::idList($request->input($key)) as $headId) {
                $head = User::find($headId);
                if ($head) {
                    $memberIds = array_merge($memberIds, $head->getAllSubordinatesIds(), [$head->id]);
                }
            }
            if ($memberIds) {
                $q->whereIn('responsible_person_id', array_values(array_unique($memberIds)));
            }
        }

        if ($request->filled('status_lead')) {
            $q->where('status_lead', $request->status_lead);
        }
        if ($request->filled('why_lost_lead')) {
            $q->where('why_lost_lead', $request->why_lost_lead);
        }
        if ($request->filled('lead_type')) {
            $q->selectedLeadType($request->lead_type);
        }
        if ($request->filled('property_status') && $request->property_status != 'both') {
            $q->where(function ($w) use ($request) {
                $w->where('property_status', $request->property_status)->orWhere('property_status', 'both');
            });
        }
        self::applyBudgetRange($q, $request);
        if ($request->filled('property_type_id')) {
            $q->where('property_type_id', $request->property_type_id);
        }
        if ($request->filled('area_id') && ($area = Area::find($request->area_id))) {
            $q->whereIn('area_id', array_merge([$area->id], $area->getChildIdsAttribute()));
        }
        if ($request->filled('interaction_result')) {
            $q->where('interaction_result', $request->interaction_result);
        }
        if ($request->filled('purpose_buying')) {
            $q->where('purpose_buying', $request->purpose_buying);
        }
        if ($request->filled('assigned_from') || $request->filled('assigned_to') || $request->filled('assigned_at')) {
            $q->whereExists(function ($h) use ($request) {
                $h->selectRaw('1')->from('lead_histories')
                    ->whereColumn('lead_histories.lead_id', 'leads.id')
                    ->whereNull('lead_histories.deleted_at');
                UaeDateRange::apply($h, 'lead_histories.created_at', $request->assigned_from, $request->assigned_to, $request->assigned_at);
                $h->where('lead_histories.changes->action', 'assigned');
            });
        }
        if ($request->filled('search') && LeadTextSearch::isActionable((string) $request->search)) {
            LeadTextSearch::apply($q, (string) $request->search, [
                'comments' => false,
                'relations' => true,
                'admin' => true,
                'lean' => true,
            ]);
        }
    }

    /** Budget range overlaps the lead's budget / budget_from–budget_to. */
    private static function applyBudgetRange($query, Request $request): void
    {
        $min = $request->filled('budget_from') ? (float) $request->budget_from : null;
        $max = $request->filled('budget_to') ? (float) $request->budget_to : null;
        if ($min === null && $max === null) {
            return;
        }
        if ($min !== null && $max !== null && $min > $max) {
            [$min, $max] = [$max, $min];
        }

        if ($min !== null && $max !== null) {
            $query->where(function ($q) use ($min, $max) {
                $q->where(fn ($w) => $w->whereNotNull('budget_from')->whereNotNull('budget_to')
                        ->where('budget_from', '<=', $max)->where('budget_to', '>=', $min))
                    ->orWhereBetween('budget', [$min, $max])
                    ->orWhere(fn ($w) => $w->whereNotNull('budget_from')->whereNull('budget_to')->whereBetween('budget_from', [$min, $max]))
                    ->orWhere(fn ($w) => $w->whereNotNull('budget_to')->whereNull('budget_from')->whereBetween('budget_to', [$min, $max]));
            });

            return;
        }

        $op = $min !== null ? '>=' : '<=';
        $value = $min ?? $max;
        $query->where(fn ($q) => $q->where('budget', $op, $value)
            ->orWhere('budget_from', $op, $value)
            ->orWhere('budget_to', $op, $value));
    }

    /** One id or several (array or comma-separated). */
    private static function idList(mixed $raw): array
    {
        if ($raw === null || $raw === '' || $raw === []) {
            return [];
        }
        $values = is_array($raw) ? $raw : preg_split('/\s*,\s*/', (string) $raw);

        return array_values(array_unique(array_filter(
            array_map(fn ($v) => is_array($v) ? 0 : (int) $v, $values),
            fn ($id) => $id > 0
        )));
    }

    public static function fullName($lead): string
    {
        return trim(trim((string) $lead->first_name) . ' ' . trim((string) $lead->last_name));
    }

    public function query()
    {
        return $this->query;
    }

    public function headings(): array
    {
        return ['Lead Name', 'Name', 'Email'];
    }

    public function map($lead): array
    {
        return [
            (string) $lead->lead_name,
            self::fullName($lead),
            (string) $lead->email,
        ];
    }
}
