<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Models\Deal;
use Illuminate\Http\Request;

class AgentPerformanceReportController extends Controller
{
    /**
     * Sales performance report: one row per agent, with every converted
     * deal they closed, the originating lead's score, the deal amount,
     * and their commission (agent_share) on that deal.
     *
     * Date window is driven by leads.converted_at (when the lead actually
     * converted), NOT deals.won_date. Joined rather than whereHas so the
     * range can use an index on leads.converted_at.
     *
     * Grouped by deals.responsible_person_id (the agent who owns the
     * deal), NOT leads.responsible_person_id, because commission and
     * final amount only live on the deal.
     */
    public function agentPerformance(Request $request)
    {
        $fromDate = $request->from_date ?: now()->startOfMonth()->toDateString();
        $toDate = $request->to_date ?: now()->toDateString();

        $deals = Deal::query()
            // inner join already enforces lead_id IS NOT NULL
            ->join('leads', 'leads.id', '=', 'deals.lead_id')
            ->whereBetween('leads.converted_at', [
                $fromDate . ' 00:00:00',
                $toDate . ' 23:59:59',
            ])
            // REQUIRED: without this, leads.id clobbers deals.id
            ->select('deals.*')
            ->with([
                'lead:id,lead_name,score,work_phone,converted_at',
                'responsiblePerson:id,name,email',
                // mainProperty is the sort_order=0 property; its area is the
                // actual location that sold (not the lead's requested area_id).
                'mainProperty:id,deal_id,sort_order,area_id,unit_no',
                'mainProperty.area:id,name',
                'properties:id,deal_id,area_id',
            ])
            // qualified: leads also has responsible_person_id
            ->when($request->agent_id, fn($q, $v) => $q->where('deals.responsible_person_id', $v))
            ->when($request->area_id, fn($q, $v) => $q->whereHas('properties', fn($p) => $p->where('area_id', $v)))
            // ->when($request->status, fn($q, $v) => $q->where('deals.status', $v), function ($q) {
            //     // default: only count deals that actually closed won
            //     $q->where('deals.status', 'completed');
            // })
            ->get();

        $report = $deals
            ->groupBy('responsible_person_id')
            ->map(function ($agentDeals) {
                $agent = $agentDeals->first()->responsiblePerson;

               $rows = $agentDeals->map(function (Deal $deal) {
                        $extraAreas = $deal->has_multiple_properties
                            ? $deal->properties->pluck('area_id')->unique()->count() - 1
                            : 0;

                        $amount = (float) $deal->deal_total_amount;
                        $rate   = (float) $deal->deal_commission;      // percent, e.g. 5 = 5%
                        $commissionAmount = round($amount * $rate / 100, 2);

                        return [
                            'lead_id'          => $deal->lead_id,
                            'lead_name'        => $deal->lead?->lead_name,
                            'lead_score'       => $deal->lead?->score,
                            'deal_id'          => $deal->id,
                            'deal_number'      => $deal->deal_number,
                            'deal_amount'      => $amount,
                            'currency'         => $deal->currency,
                            'commission_rate'  => $rate,               // the % itself
                            'commission'       => $commissionAmount,   // AED value
                            'location'         => $deal->mainProperty?->area?->name,
                            'location_extra'   => $extraAreas > 0 ? $extraAreas : null,
                            'unit_no'          => $deal->mainProperty?->unit_no,
                            'converted_at'     => optional($deal->lead?->converted_at)->toDateString(),
                            'won_date'         => optional($deal->won_date)->toDateString(),
                        ];
                    })
                    ->sortByDesc('converted_at')
                    ->values();

                return [
                    'agent_id'         => $agent?->id,
                    'agent_name'       => $agent?->name ?? 'Unassigned',
                    'agent_email'      => $agent?->email,
                    'deals'            => $rows,
                    'converted_count'  => $rows->count(),
                    'total_amount'     => round($rows->sum('deal_amount'), 2),
                    'total_commission' => round($rows->sum('commission'), 2),
                    'avg_lead_score'   => $rows->pluck('lead_score')->filter()->avg()
                                            ? round($rows->pluck('lead_score')->filter()->avg(), 1)
                                            : null,
                ];
            })
            ->sortByDesc('total_commission')
            ->values();

        return response()->json([
            'data' => $report,
            'summary' => [
                'agents_count'     => $report->count(),
                'deals_count'      => $report->sum('converted_count'),
                'total_amount'     => round($report->sum('total_amount'), 2),
                'total_commission' => round($report->sum('total_commission'), 2),
            ],
            // Full area list (not scoped to current filters) so the
            // location dropdown doesn't shrink as filters narrow results.
            'areas' => \App\Models\Area::query()->select('id', 'name')->orderBy('name')->get(),
        ]);
    }
}