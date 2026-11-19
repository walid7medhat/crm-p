<?php

namespace App\Http\Controllers\Api\Deal;

use App\Exports\DealCommissionSheetExport;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Reports\DealCommissionSheet;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Deal list → accountant commission sheet (Excel).
 * accountant (their branch) / super_admin / user #30 — see DealCommissionSheet::canExport().
 * Takes the same query params as GET /deals (deal_type, stage_id, search filters).
 */
class DealCommissionSheetController extends Controller
{
    public function __invoke(Request $request, DealCommissionSheet $sheet)
    {
        $user = $request->user();

        if (! DealCommissionSheet::canExport($user)) {
            return ApiResponse::error('Unauthorized - You do not have access to this export', 403);
        }

        $type = DealCommissionSheet::DEAL_TYPE_LABELS[$request->input('deal_type')] ?? 'deals';

        return Excel::download(
            new DealCommissionSheetExport($sheet->rows($request, $user)),
            'deals-commission-'.strtolower($type).'-'.now('Asia/Dubai')->format('Y-m-d').'.xlsx'
        );
    }
}
