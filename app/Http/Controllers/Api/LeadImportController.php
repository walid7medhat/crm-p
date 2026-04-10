<?php

namespace App\Http\Controllers\Api;

use App\Events\LeadUpdated;
use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Http\Requests\Lead\LeadRequest;
use App\Http\Requests\Lead\LeadIntegrationRequest;
use App\Http\Requests\Lead\AssignResponsiblePersonRequest;
use App\Http\Resources\Lead\LeadResource;
use App\Http\Resources\Lead\LeadCollection;
use App\Models\Lead;
use App\Models\Integration;
use App\Models\LeadParticipant;
use App\Models\LeadObserver;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Http\Resources\Lead\DuplicateLeadResource;
use App\Helpers\LeadHistoryHelper;
use App\Http\Resources\Lead\LeadHistoryResource;
use App\Models\LeadHistory;
use App\Models\LeadComment;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\LeadsImport;
class LeadImportController extends Controller
{
    
   
public function import(Request $request)
{set_time_limit(300);
    $request->validate([
'file' => 'required|file|mimetypes:text/plain,text/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',        'start' => 'nullable|integer|min:1',
        'end' => 'nullable|integer|min:1'
    ]);

    $start = $request->start ?? 1;
    $end = $request->end ?? 1000;

       $import = new LeadsImport(
        $request->start ?? 1,
        $request->end ?? 100000
    );

    Excel::import($import, $request->file('file'));

    return response()->json([
        'message' => 'Imported with range',
          'errors' => $import->getErrors()
    ]);
}

}
