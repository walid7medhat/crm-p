<?php

namespace App\Http\Controllers\Api;

use App\Exports\OwnerFinancial\OwnerFinancialReportExport;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Reports\OwnerFinancialReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Owner Financial Intelligence (deals commission report).
 * super_admin + user #30 only — see OwnerFinancialReport::canView().
 */
class OwnerFinancialReportController extends Controller
{
    public function __construct(private OwnerFinancialReport $report)
    {
    }

    /** Filter options (branches, lead sources, deal types). */
    public function options(Request $request)
    {
        if (! OwnerFinancialReport::canView($request->user())) {
            return $this->forbidden();
        }

        return ApiResponse::success($this->report->options());
    }

    /** KPI totals + one page of deals, both for the same filters. */
    public function index(Request $request)
    {
        if (! OwnerFinancialReport::canView($request->user())) {
            return $this->forbidden();
        }

        $request->validate($this->rules());

        return ApiResponse::success([
            'summary' => $this->report->summary($request),
            ...$this->report->rows($request),
        ]);
    }

    /** Excel of every deal matching the filters (all pages) + a KPI summary sheet. */
    public function export(Request $request)
    {
        if (! OwnerFinancialReport::canView($request->user())) {
            return $this->forbidden();
        }

        $request->validate($this->rules());

        return Excel::download(
            new OwnerFinancialReportExport(
                $this->report->exportRows($request),
                $this->report->summary($request),
                $this->describeFilters($request),
            ),
            'owner-financial-report-'.now('Asia/Dubai')->format('Y-m-d').'.xlsx'
        );
    }

    /** Aggregations (deal type / agent / branch / month) for the same filters. */
    public function insights(Request $request)
    {
        if (! OwnerFinancialReport::canView($request->user())) {
            return $this->forbidden();
        }

        $request->validate($this->rules());

        return ApiResponse::success($this->report->insights($request));
    }

    /**
     * Premium PDF: KPIs + filters + up to 250 deal rows (full set remains on Excel).
     */
    public function pdf(Request $request)
    {
        if (! OwnerFinancialReport::canView($request->user())) {
            return $this->forbidden();
        }

        $request->validate($this->rules());

        $summary = $this->report->summary($request);
        $rows = $this->report->exportRows($request);
        $totalRows = count($rows);
        $cap = 250;
        $truncated = $totalRows > $cap;
        if ($truncated) {
            $rows = array_slice($rows, 0, $cap);
        }

        $generatedAt = now('Asia/Dubai')->format('d M Y · H:i').' GST';

        return Pdf::loadView('pdf.owner-financial-report', [
            'summary' => $summary,
            'rows' => $rows,
            'filters' => $this->describeFilters($request),
            'generated_at' => $generatedAt,
            'truncated' => $truncated,
            'total_rows' => $totalRows,
            'shown_rows' => count($rows),
        ])
            ->setPaper('a4', 'landscape')
            ->setOption(['isFontSubsettingEnabled' => true])
            ->download('owner-financial-report-'.now('Asia/Dubai')->format('Y-m-d').'.pdf');
    }

    /** Full commission breakdown of one deal. */
    public function show(Request $request, int $deal)
    {
        if (! OwnerFinancialReport::canView($request->user())) {
            return $this->forbidden();
        }

        $data = $this->report->breakdown($deal);

        return $data ? ApiResponse::success($data) : ApiResponse::error('Deal not found', 404);
    }

    private function rules(): array
    {
        return [
            'search' => 'nullable|string|max:100',
            'agent' => 'nullable|string|max:100',
            'agent_id' => 'nullable|integer',
            'client' => 'nullable|string|max:100',
            'deal_number' => 'nullable|string|max:100',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'source' => 'nullable',
            'branch_id' => 'nullable|integer',
            'deal_type' => 'nullable|in:sale,primary,secondary,rental',
            'deal_status' => 'nullable|in:closed,all',
            'data_status' => 'nullable|in:all,complete,missing',
            'min_amount' => 'nullable|numeric',
            'max_amount' => 'nullable|numeric',
            'min_commission' => 'nullable|numeric',
            'max_commission' => 'nullable|numeric',
            'sort' => 'nullable|string',
            'direction' => 'nullable|in:asc,desc',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:5|max:'.OwnerFinancialReport::MAX_PER_PAGE,
        ];
    }

    /** Human-readable list of the filters used, for the export's Summary sheet. */
    private function describeFilters(Request $request): array
    {
        $branches = $this->report->branches();
        $range = fn ($min, $max) => trim(($request->filled($min) ? 'from '.number_format((float) $request->input($min)) : '')
            .' '.($request->filled($max) ? 'to '.number_format((float) $request->input($max)) : ''));

        $out = [
            'Deal status' => $request->input('deal_status') === 'all' ? 'All deals' : 'Closed deals only',
            'Search' => $request->input('search'),
            'Agent' => $request->filled('agent_id')
                ? (optional(\App\Models\User::find((int) $request->input('agent_id')), fn ($u) => $u->display_name ?: $u->name) ?? '#'.$request->input('agent_id'))
                : $request->input('agent'),
            'Client name / phone' => $request->input('client'),
            'Deal number' => $request->input('deal_number'),
            'Deal date' => trim(($request->input('date_from') ? 'from '.$request->input('date_from') : '').' '.($request->input('date_to') ? 'to '.$request->input('date_to') : '')),
            'Lead source' => implode(', ', array_filter((array) $request->input('source'))),
            'Branch' => $request->filled('branch_id') ? ($branches[(int) $request->input('branch_id')]['name'] ?? $request->input('branch_id')) : null,
            'Deal type' => match ($request->input('deal_type')) {
                'sale' => 'Sale (Primary + Secondary)',
                null, '' => null,
                default => OwnerFinancialReport::DEAL_TYPES[$request->input('deal_type')] ?? $request->input('deal_type'),
            },
            'Deal amount (AED)' => $range('min_amount', 'max_amount'),
            'Gross commission (AED)' => $range('min_commission', 'max_commission'),
            'Financial data' => match ($request->input('data_status')) {
                'missing' => 'Missing data only',
                'complete' => 'Complete only',
                default => null,
            },
        ];

        return array_filter($out, fn ($v) => $v !== null && $v !== '');
    }

    private function forbidden()
    {
        return ApiResponse::error('Unauthorized - You do not have access to this report', 403);
    }
}
