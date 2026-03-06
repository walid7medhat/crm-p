<?php

namespace App\Http\Controllers\Api\Deal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Deal\UpdateDealRequest;
use App\Http\Resources\Deal\DealResource;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Stage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\LeadHistory;
use App\Http\Resources\Deal\DealHistoryResource;
use App\Events\DealUpdated;
use App\Helpers\DealHistoryHelper;
use App\Helpers\ApiResponse;

class DealController extends Controller
{
    /**
     * 1. جلب كل الصفقات مع فلترة متقدمة
     */
    public function index(Request $request)
        {
            $deals = Deal::with([
                    'lead',
                    'stage',
                    'propertyType',
                    'project',
                    'area',
                    'developer',
                    'responsiblePerson',
                    'parties'
                ])
                ->visibleFor(auth()->user())
                ->filter($request)
                ->orderBy(
                    $request->get('order_by','created_at'),
                    $request->get('order_dir','desc')
                )
                ->paginate($request->get('per_page',15));
        
            return response()->json([
                'success' => true,
                'data' => DealResource::collection($deals),
                'meta' => [
                    'total'=>$deals->total(),
                    'current_page'=>$deals->currentPage(),
                    'last_page'=>$deals->lastPage()
                ]
            ]);
        }

    /**
     * 2. جلب صفقة واحدة بالتفصيل
     */
    public function show(Deal $deal)
    {
        $user = auth()->user();
        
        // التحقق من الصلاحية
        if (!$user->hasAnyRole(['super_admin'])) {
            $canAccess = false;
            
            if ($user->hasAnyRole(['manager', 'team_lead', 'admin'])) {
                $subordinatesIds = $user->getAllSubordinatesIds();
                $canAccess = in_array($deal->responsible_person_id, array_merge($subordinatesIds, [$user->id]));
            } else {
                $canAccess = $deal->responsible_person_id == $user->id;
            }
            
            if (!$canAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
        }

        $deal->load([
            'lead',
            'stage',
            'propertyType',
            'project',
            'area',
            'developer',
            'responsiblePerson',
            'parties',
            'documents'
        ]);

        return response()->json([
            'success' => true,
            'data' => new DealResource($deal)
        ]);
    }

    /**
     * 3. تحديث صفقة
     */
    public function update(Request $request, Deal $deal)
{
    DB::beginTransaction();

    try {

        $changes = [];

        /*
        |--------------------------------------------------------------------------
        | Update Deal Basic Data
        |--------------------------------------------------------------------------
        */

        $oldDeal = $deal->getOriginal();

        $deal->update($request->only([
            'deal_name',
            'deal_total_amount',
            'deal_commission',
            'agent_share',
            'company_share',
            'unit_no',
            'bedrooms',
            'unit_size',
            'property_link'
        ]));

        if ($deal->getChanges()) {
            $changes['deal'] = [
                'old' => $oldDeal,
                'new' => $deal->getChanges()
            ];
        }


        /*
        |--------------------------------------------------------------------------
        | Update Parties (buyer / seller / tenant)
        |--------------------------------------------------------------------------
        */

        if ($request->filled('parties')) {

            foreach ($request->parties as $partyData) {

                $party = DealParty::find($partyData['id']);

                if (!$party) continue;

                $oldParty = $party->getOriginal();

                $party->update($partyData);

                if ($party->getChanges()) {
                    $changes['parties'][] = [
                        'party_id' => $party->id,
                        'type' => $party->party_type,
                        'old' => $oldParty,
                        'new' => $party->getChanges()
                    ];
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Upload Documents
        |--------------------------------------------------------------------------
        */

      if ($request->hasFile('documents')) {

            foreach ($request->file('documents') as $index => $file) {
        
                $docData = $request->documents[$index];
        
                $storagePath = "deals/{$deal->id}/{$docData['category']}";
        
                $isImage = in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp']);
        
                if ($isImage) {
                    // ضغط وتحويل الصورة
                    $imageOptions = [
                        'quality' => 80,
                        'max_width' => 1920,
                        'max_height' => 1080,
                        'watermark' => [
                            'enabled' => false
                        ]
                    ];
                    $result = ImageHelper::compressAndConvertToWebP($file, $storagePath, $imageOptions);
                    $filePath = $result['path'];
                    $fileSize = $result['compressed_size'] ?? $file->getSize();
                } else {
                    // ملفات PDF, DOC, إلخ
                    $path = $file->store($storagePath, 'public');
                    $filePath = $path;
                    $fileSize = $file->getSize();
                }
        
                DealDocument::create([
                    'deal_id' => $deal->id,
                    'deal_party_id' => $docData['deal_party_id'] ?? null,
                    'document_category' => $docData['category'],
                    'document_type' => $docData['type'],
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $filePath,
                    'file_size' => $fileSize,
                    'mime_type' => $file->getMimeType(),
                    'uploaded_by' => auth()->id()
                ]);
            }
        
            $changes['documents_uploaded'] = count($request->file('documents'));
        }


        /*
        |--------------------------------------------------------------------------
        | History
        |--------------------------------------------------------------------------
        */

        if (!empty($changes)) {

            DealHistoryHelper::log(
                $deal->id,
                [
                    'action' => 'updated',
                    'changes' => $changes,
                    'user_id' => auth()->id()
                ]
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Broadcast
        |--------------------------------------------------------------------------
        */

        $deal->load([
            'stage',
            'propertyType',
            'project',
            'area',
            'developer',
            'parties',
            'documents',
            'responsiblePerson'
        ]);

        broadcast(new DealUpdated($deal, 'updated'))->toOthers();


        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Deal updated successfully',
            'data' => new DealResource($deal)
        ]);

    } catch (\Exception $e) {

        DB::rollBack();

        return response()->json([
            'success' => false,
            'message' => 'Failed to update deal',
            'error' => $e->getMessage()
        ], 500);
    }
}
    /**
     * 4. جلب الصفقات مجمعة حسب المرحلة (للكانبان)
     */
  public function getDealsGroupedByStage(Request $request)
{
    $user = auth()->user();

    $stages = Stage::where('stage_type','deal')
        ->when($request->deal_type, fn($q)=>$q->where('deal_type',$request->deal_type))
        ->orderBy('deal_type')
        ->orderBy('order')
        ->get();

    $deals = Deal::with([
            'lead',
            'propertyType',
            'project',
            'area',
            'developer',
            'responsiblePerson'
        ])
        ->visibleFor($user)
        ->filter($request)
        ->get()
        ->groupBy('stage_id');

    $result = $stages->map(function($stage) use ($deals){

        $stageDeals = $deals->get($stage->id, collect());

        return [
            'stage_id'=>$stage->id,
            'stage_name'=>$stage->name,
            'stage_color'=>$stage->color,
            'deal_type'=>$stage->deal_type,
            'deals_count'=>$stageDeals->count(),
            'deals'=>DealResource::collection($stageDeals)
        ];

    });

    return response()->json([
        'success'=>true,
        'data'=>$result
    ]);
}



    // ======================history =======================
        public function history(Request $request, $dealId)
{
    $deal=Deal::find($dealId);
    $query = LeadHistory::where('deal_id', $dealId)
    ->when($deal->lead_id,function($q) use($deal){
         $q->orWhere('lead_id',$deal->lead_id);
    })
        ->with('user:id,name,avatar');
    
    // Search functionality - search in multiple fields
    if ($request->filled('search')) {
        $searchTerm = $request->search;
        $query->where(function($q) use ($searchTerm) {
            // Search in action field inside changes JSON
            $q->where('changes->action', 'LIKE', "%{$searchTerm}%")
              // Search in user name
              ->orWhereHas('user', function($userQuery) use ($searchTerm) {
                  $userQuery->where('name', 'LIKE', "%{$searchTerm}%");
              })
              // Search in old_stage
              ->orWhereRaw("JSON_EXTRACT(changes, '$.old_stage') LIKE ?", ["%{$searchTerm}%"])
              // Search in new_stage
              ->orWhereRaw("JSON_EXTRACT(changes, '$.new_stage') LIKE ?", ["%{$searchTerm}%"])
              // Search in old_person
              ->orWhereRaw("JSON_EXTRACT(changes, '$.old_person') LIKE ?", ["%{$searchTerm}%"])
              // Search in new_person
              ->orWhereRaw("JSON_EXTRACT(changes, '$.new_person') LIKE ?", ["%{$searchTerm}%"]);
        });
    }

    if ($request->filled('action')) {
        $query->where('changes->action', $request->action);
    }

    if ($request->filled('user_id')) {
        $query->where('user_id', $request->user_id);
    }

    if ($request->filled('from_date')) {
        $query->whereDate('created_at', '>=', $request->from_date);
    }

    if ($request->filled('to_date')) {
        $query->whereDate('created_at', '<=', $request->to_date);
    }

    // Get per_page from request or default to 10
    $perPage = $request->input('per_page', 10);
    
    $histories = $query->latest()->paginate($perPage);

    return ApiResponse::success([
        'items' => DealHistoryResource::collection($histories),
        'pagination' => [
            'current_page' => $histories->currentPage(),
            'last_page'    => $histories->lastPage(),
            'per_page'     => $histories->perPage(),
            'total'        => $histories->total(),
            'next_page'    => $histories->nextPageUrl(),
            'prev_page'    => $histories->previousPageUrl(),
        ]
    ], 'Deal History retrieved successfully');
}
          public function view_history($id)
    {
        try {
            $deal=Deal::find($id);
            DealHistoryHelper::log(
                $deal->id,
                ['action' => 'view']
            );

            return ApiResponse::success(
                new DealResource($deal),
                'Lead history saved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve lead: ' . $e->getMessage());
        }
    }
}