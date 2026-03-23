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
use App\Http\Requests\Deal\CheckStageRequirementsRequest;
use App\Http\Requests\Deal\UpdatePartialRequest;
use App\Services\DealStageValidator;
use App\Services\DealStageValidatorService;
use App\Http\Requests\Deal\UpdateDealStageRequest;
use Illuminate\Support\Facades\Log; 
use App\Models\DealDocument;
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
                    'parties',
                    'subcommunity'
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
            'documents',
            'subcommunity'
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
            'property_link','lost_reason'
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
            'responsiblePerson','subcommunity'
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
            'responsiblePerson',
            'parties','documents','subcommunity'
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
    if (!$deal) {
        return response()->json([
            'success' => false,
            'message' => 'Deal not found'
        ], 404);
    }
    $query = LeadHistory::where('deal_id', $dealId)
    ->when($deal->lead_id,function($q) use($deal){
         $q->orWhere('lead_id',$deal->lead_id);
    })
        ->with(['user:id,name,avatar', 'deal:id,deal_name,source,unit_no,property_reference,responsible_person_id', 'deal.responsiblePerson:id,name']);
    
    // Search functionality - search in multiple fields
    if ($request->filled('search')) {
        $searchTerm = trim((string) $request->search);
        $query->where(function($q) use ($searchTerm) {
            // Smart/global search across history row, JSON changes, actor, and deal info
            $q->where('changes->action', 'LIKE', "%{$searchTerm}%")
              ->orWhereRaw('CAST(changes AS CHAR) LIKE ?', ["%{$searchTerm}%"])
              ->orWhereHas('user', function($userQuery) use ($searchTerm) {
                  $userQuery->where('name', 'LIKE', "%{$searchTerm}%");
              })
              ->orWhereHas('deal', function($dealQuery) use ($searchTerm) {
                  $dealQuery->where('deal_name', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('source', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('unit_no', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('property_reference', 'LIKE', "%{$searchTerm}%")
                      ->orWhereHas('responsiblePerson', function ($userQuery) use ($searchTerm) {
                          $userQuery->where('name', 'LIKE', "%{$searchTerm}%");
                      });
              })
              ->orWhereRaw("JSON_EXTRACT(changes, '$.old_stage') LIKE ?", ["%{$searchTerm}%"])
              ->orWhereRaw("JSON_EXTRACT(changes, '$.new_stage') LIKE ?", ["%{$searchTerm}%"])
              ->orWhereRaw("JSON_EXTRACT(changes, '$.old_person') LIKE ?", ["%{$searchTerm}%"])
              ->orWhereRaw("JSON_EXTRACT(changes, '$.new_person') LIKE ?", ["%{$searchTerm}%"]);
        });
    }

    // Keep backward compatibility with current action filter + support event_type alias
    $actionFilter = $request->input('action', $request->input('event_type'));
    if (!empty($actionFilter)) {
        $query->where('changes->action', $actionFilter);
    }

    // Keep backward compatibility + support created_by alias
    $createdBy = $request->input('user_id', $request->input('created_by'));
    if (!empty($createdBy)) {
        $query->where('user_id', $createdBy);
    }

    if ($request->filled('from_date')) {
        $query->whereDate('created_at', '>=', $request->from_date);
    }

    if ($request->filled('to_date')) {
        $query->whereDate('created_at', '<=', $request->to_date);
    }

    // Date preset quick filter: today, yesterday, this_week, this_month, current_quarter, last_week, last_30_days, last_60_days
    if ($request->filled('date')) {
        $datePreset = (string) $request->date;
        switch ($datePreset) {
            case 'today':
                $query->whereDate('created_at', now()->toDateString());
                break;
            case 'yesterday':
                $query->whereDate('created_at', now()->subDay()->toDateString());
                break;
            case 'this_week':
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'this_month':
                $query->whereYear('created_at', now()->year)->whereMonth('created_at', now()->month);
                break;
            case 'current_quarter':
                $query->whereBetween('created_at', [now()->firstOfQuarter(), now()->lastOfQuarter()]);
                break;
            case 'last_week':
                $query->whereBetween('created_at', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()]);
                break;
            case 'last_30_days':
                $query->where('created_at', '>=', now()->subDays(30));
                break;
            case 'last_60_days':
                $query->where('created_at', '>=', now()->subDays(60));
                break;
            default:
                break;
        }
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
    
    
    
    public function checkStageRequirements(CheckStageRequirementsRequest $request, DealStageValidator $validator)
{
    $deal = Deal::find($request->deal_id);
    
    if (!$deal) {
        return response()->json([
            'success' => false,
            'message' => 'Deal not found'
        ], 404);
    }

    $deal->load(['parties', 'documents']);
    
    // تسجيل عدد المستندات الموجودة
    Log::info('Deal documents count', [
        'deal_id' => $deal->id,
        'documents_count' => $deal->documents->count()
    ]);
    
    $guard = app(DealStageValidatorService::class);
    $result = $guard->validateStageChange($deal, (int) $request->target_stage_id, $request->deal_type);

    $response = [
        'success' => true,
        'valid' => $result['valid'],
        'missing_fields' => $result['missing_fields'] ?? [],
        'grouped_missing' => $result['grouped_missing'] ?? ['sections' => [], 'by_stage' => []],
        'message' => $result['message'] ?? 'Validation checked',
    ];

    if (!empty($result['missing_fields'])) {
        // Backward-compatible payload keys expected by current frontend
        $response['missing_fields_grouped'] = $result['missing_fields_grouped'] ?? ['sections' => []];
        $response['missing_by_stage'] = $result['missing_by_stage'] ?? [];
        $response['missing_fields_grouped_by_stage'] = $result['missing_fields_grouped_by_stage'] ?? ['stages' => []];
    }

    return response()->json($response);
}

/**
 * تحديث جزئي للصفقة
 */

public function updatePartial(UpdatePartialRequest $request, $id)
{
    $deal = Deal::find($id);
    
    if (!$deal) {
        return response()->json([
            'success' => false,
            'message' => 'Deal not found'
        ], 404);
    }

    try {
        DB::beginTransaction();

        // 1. تحديث الحقول الأساسية
        $dealData = $request->except(['_token']);
        
        
        
        // إزالة الحقول الفارغة والحقول الخاصة بالأطراف
        $dealData = array_filter($dealData, function($value, $key) {
            // استبعد حقول الأطراف
            if (str_starts_with($key, 'buyer_') || 
                str_starts_with($key, 'seller_') || 
                str_starts_with($key, 'tenant_') || 
                str_starts_with($key, 'landlord_')) {
                return false;
            }
            return $value !== null && $value !== '';
        }, ARRAY_FILTER_USE_BOTH);
        
        if (!empty($dealData)) {
            $deal->update($dealData);
        }

        // 2. تحديث أو إنشاء Buyer
        $buyerData = [];
        if ($request->filled('buyer_first_name')) $buyerData['first_name'] = $request->buyer_first_name;
        if ($request->filled('buyer_last_name')) $buyerData['last_name'] = $request->buyer_last_name;
        if ($request->filled('buyer_phone')) $buyerData['phone'] = $request->buyer_phone;
        if ($request->filled('buyer_email')) $buyerData['email'] = $request->buyer_email;
        if ($request->filled('buyer_nationality')) $buyerData['nationality'] = $request->buyer_nationality;
        if ($request->filled('buyer_dob')) $buyerData['date_of_birth'] = $request->buyer_dob;
        if ($request->filled('buyer_residency_status')) $buyerData['residency_status'] = $request->buyer_residency_status;
        if ($request->filled('buyer_city')) $buyerData['city'] = $request->buyer_city;
        if ($request->filled('buyer_country')) $buyerData['country'] = $request->buyer_country;
        if ($request->filled('buyer_language')) $buyerData['language'] = $request->buyer_language;
        if ($request->filled('buyer_amount')) $buyerData['amount'] = $request->buyer_amount;

        if (!empty($buyerData)) {
            $buyer = $deal->parties()
                ->where('party_type', 'buyer')
                ->where('party_role', 'primary')
                ->first();

            if ($buyer) {
                $buyer->update($buyerData);
            } else {
                // نتأكد من وجود الاسم الأول والأخير قبل الإنشاء
                if (isset($buyerData['first_name']) && isset($buyerData['last_name'])) {
                    $deal->parties()->create([
                        'party_type' => 'buyer',
                        'party_role' => 'primary',
                        ...$buyerData
                    ]);
                }
            }
        }

        // 3. تحديث أو إنشاء Seller
        $sellerData = [];
        if ($request->filled('seller_first_name')) $sellerData['first_name'] = $request->seller_first_name;
        if ($request->filled('seller_last_name')) $sellerData['last_name'] = $request->seller_last_name;
        if ($request->filled('seller_phone')) $sellerData['phone'] = $request->seller_phone;
        if ($request->filled('seller_email')) $sellerData['email'] = $request->seller_email;
        if ($request->filled('seller_nationality')) $sellerData['nationality'] = $request->seller_nationality;
        if ($request->filled('seller_dob')) $sellerData['date_of_birth'] = $request->seller_dob;
        if ($request->filled('seller_residency_status')) $sellerData['residency_status'] = $request->seller_residency_status;
        if ($request->filled('seller_city')) $sellerData['city'] = $request->seller_city;
        if ($request->filled('seller_country')) $sellerData['country'] = $request->seller_country;
        if ($request->filled('seller_language')) $sellerData['language'] = $request->seller_language;

        if (!empty($sellerData)) {
            $seller = $deal->parties()
                ->where('party_type', 'seller')
                ->where('party_role', 'primary')
                ->first();

            if ($seller) {
                $seller->update($sellerData);
            } else {
                if (isset($sellerData['first_name']) && isset($sellerData['last_name'])) {
                    $deal->parties()->create([
                        'party_type' => 'seller',
                        'party_role' => 'primary',
                        ...$sellerData
                    ]);
                }
            }
        }

        // 4. تحديث أو إنشاء Tenant
        $tenantData = [];
        if ($request->filled('tenant_first_name')) $tenantData['first_name'] = $request->tenant_first_name;
        if ($request->filled('tenant_last_name')) $tenantData['last_name'] = $request->tenant_last_name;
        if ($request->filled('tenant_phone')) $tenantData['phone'] = $request->tenant_phone;
        if ($request->filled('tenant_email')) $tenantData['email'] = $request->tenant_email;
        if ($request->filled('tenant_nationality')) $tenantData['nationality'] = $request->tenant_nationality;
        if ($request->filled('tenant_dob')) $tenantData['date_of_birth'] = $request->tenant_dob;
        if ($request->filled('tenant_residency_status')) $tenantData['residency_status'] = $request->tenant_residency_status;
        if ($request->filled('tenant_city')) $tenantData['city'] = $request->tenant_city;
        if ($request->filled('tenant_country')) $tenantData['country'] = $request->tenant_country;
        if ($request->filled('tenant_language')) $tenantData['language'] = $request->tenant_language;
        if ($request->filled('tenant_amount')) $tenantData['amount'] = $request->tenant_amount;

        if (!empty($tenantData)) {
            $tenant = $deal->parties()
                ->where('party_type', 'tenant')
                ->where('party_role', 'primary')
                ->first();

            if ($tenant) {
                $tenant->update($tenantData);
            } else {
                if (isset($tenantData['first_name']) && isset($tenantData['last_name'])) {
                    $deal->parties()->create([
                        'party_type' => 'tenant',
                        'party_role' => 'primary',
                        ...$tenantData
                    ]);
                }
            }
        }

        // 5. تحديث أو إنشاء Landlord
        $landlordData = [];
        if ($request->filled('landlord_first_name')) $landlordData['first_name'] = $request->landlord_first_name;
        if ($request->filled('landlord_last_name')) $landlordData['last_name'] = $request->landlord_last_name;
        if ($request->filled('landlord_phone')) $landlordData['phone'] = $request->landlord_phone;
        if ($request->filled('landlord_email')) $landlordData['email'] = $request->landlord_email;
        if ($request->filled('landlord_nationality')) $landlordData['nationality'] = $request->landlord_nationality;
        if ($request->filled('landlord_dob')) $landlordData['date_of_birth'] = $request->landlord_dob;
        if ($request->filled('landlord_residency_status')) $landlordData['residency_status'] = $request->landlord_residency_status;
        if ($request->filled('landlord_city')) $landlordData['city'] = $request->landlord_city;
        if ($request->filled('landlord_country')) $landlordData['country'] = $request->landlord_country;
        if ($request->filled('landlord_language')) $landlordData['language'] = $request->landlord_language;

        if (!empty($landlordData)) {
            $landlord = $deal->parties()
                ->where('party_type', 'landlord')
                ->where('party_role', 'primary')
                ->first();

            if ($landlord) {
                $landlord->update($landlordData);
            } else {
                if (isset($landlordData['first_name']) && isset($landlordData['last_name'])) {
                    $deal->parties()->create([
                        'party_type' => 'landlord',
                        'party_role' => 'primary',
                        ...$landlordData
                    ]);
                }
            }
        }

        DealHistoryHelper::log($deal->id, [
            'action' => 'updated',
            'user_id' => auth()->id()
        ]);

        DB::commit();

        $deal->load(['parties', 'documents', 'stage']);
        broadcast(new DealUpdated($deal, 'updated'));

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
// دالة مساعدة للتحقق من وجود أي field
private function hasAnyField($request, array $fields)
{
    foreach ($fields as $field) {
        if ($request->has($field)) {
            return true;
        }
    }
    return false;
}

public function getStageRequiredFields(Request $request)
{
    $deal = Deal::find($request->deal_id);
    $targetStage = Stage::find($request->target_stage_id);
    
    if (!$deal || !$targetStage) {
        return response()->json([
            'success' => false,
            'required_fields' => []
        ]);
    }
    
    $validator = new DealStageValidator();
    $requiredFields = $validator->getRequiredFieldsForStage($deal, (int) $request->target_stage_id, $request->deal_type);
    
    return response()->json([
        'success' => true,
        'required_fields' => $requiredFields
    ]);
}
public function updateStage(Request $request, $id)
{
    $deal = Deal::find($id);
    
    if (!$deal) {
        return response()->json([
            'success' => false,
            'message' => 'Deal not found'
        ], 404);
    }

    try {
        DB::beginTransaction();

        $oldStageId = $deal->stage_id;
        $changes = [];

        // 1. تحديث المرحلة إذا وجدت (guarded by unified validator service)
        if ($request->filled('stage_id')) {
            $guard = app(DealStageValidatorService::class);
            $validation = $guard->validateStageChange($deal, (int) $request->stage_id, $deal->deal_type);

            if (!$validation['valid']) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'valid' => false,
                    'message' => $validation['message'] ?? 'Missing required fields',
                    'missing_fields' => $validation['missing_fields'] ?? [],
                    'grouped_missing' => $validation['grouped_missing'] ?? ['sections' => [], 'by_stage' => []],
                    // Backward-compatible keys
                    'missing_fields_grouped' => $validation['missing_fields_grouped'] ?? ['sections' => []],
                    'missing_by_stage' => $validation['missing_by_stage'] ?? [],
                    'missing_fields_grouped_by_stage' => $validation['missing_fields_grouped_by_stage'] ?? ['stages' => []],
                ], 422);
            }

            $deal->stage_id = $request->stage_id;
            $changes['stage'] = [
                'old' => $oldStageId,
                'new' => $request->stage_id
            ];
        }

        // 2. تحديث المعلومات الأساسية
        $basicFields = [
            'source', 'deal_name', 'unit_no', 'property_type_id', 
            'subcommunity_id', 'bedrooms', 'unit_size', 'area_id',
            'deal_total_amount', 'deal_commission', 'agent_share', 
            'company_share', 'currency', 'responsible_person_id',
            'amount', 'property_link', 'property_reference','lost_reason'
        ];

        $dealData = [];
        foreach ($basicFields as $field) {
            if ($request->has($field)) {
                $dealData[$field] = $request->$field;
            }
        }

        if (!empty($dealData)) {
            $deal->update($dealData);
            $changes['basic'] = $dealData;
        }

        // 3. تحديث الـ parties
        $partyTypes = ['buyer', 'seller', 'tenant', 'landlord'];
        foreach ($partyTypes as $type) {
            if ($request->has($type)) {
                $partyData = $request->$type;
                
                $party = $deal->parties()
                    ->where('party_type', $type)
                    ->where('party_role', 'primary')
                    ->first();

                if ($party) {
                    $party->update($partyData);
                } else {
                    // إنشاء party جديد إذا مش موجود
                    $deal->parties()->create([
                        'party_type' => $type,
                        'party_role' => 'primary',
                        ...$partyData
                    ]);
                }
                $changes['parties'][$type] = $partyData;
            }
        }

        // 4. حفظ التاريخ
        if (!empty($changes)) {
            DealHistoryHelper::log($deal->id, [
                'action' => 'stage_changed',
                'changes' => $changes,
                'user_id' => auth()->id()
            ]);
        }

        DB::commit();

        // broadcast
        broadcast(new DealUpdated($deal->fresh(), 'stage_changed'));

        return response()->json([
            'success' => true,
            'message' => 'Deal stage and info updated successfully',
            'data' => new DealResource($deal->load(['parties', 'documents', 'stage']))
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
public function changeStage(Request $request, $id)
{
    $deal = Deal::find($id);
    
    if (!$deal) {
        return response()->json([
            'success' => false,
            'message' => 'Deal not found'
        ], 404);
    }

    $dealType = $deal->deal_type;
    $newStageId = (int) $request->stage_id;

    if (!$newStageId) {
        return response()->json([
            'success' => false,
            'message' => 'Stage ID is required'
        ], 400);
    }

    $guard = app(DealStageValidatorService::class);
    $validation = $guard->validateStageChange($deal, $newStageId, $dealType);

    if (!$validation['valid']) {
        return response()->json([
            'success' => false,
            'valid' => false,
            'message' => $validation['message'] ?? 'Complete all required fields before changing stage.',
            'missing_fields' => $validation['missing_fields'] ?? [],
            'grouped_missing' => $validation['grouped_missing'] ?? ['sections' => [], 'by_stage' => []],
            // Backward-compatible keys
            'missing_fields_grouped' => $validation['missing_fields_grouped'] ?? ['sections' => []],
            'missing_by_stage' => $validation['missing_by_stage'] ?? [],
            'missing_fields_grouped_by_stage' => $validation['missing_fields_grouped_by_stage'] ?? ['stages' => []],
        ], 422);
    }

    try {
        DB::beginTransaction();

        $oldStageId = $deal->stage_id;

        $deal->stage_id = $newStageId;
        $deal->save();

        DealHistoryHelper::log($deal->id, [
            'action' => 'stage_changed',
            'old_stage' => $oldStageId,
            'new_stage' => $newStageId,
            'user_id' => auth()->id()
        ]);

        DB::commit();

        $deal->load(['stage', 'parties', 'documents']);
        broadcast(new DealUpdated($deal, 'stage_changed'));

        return response()->json([
            'success' => true,
            'message' => 'Deal stage changed successfully',
            'data' => new DealResource($deal)
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Failed to change deal stage',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function updateAndChangeStage(Request $request, $id)
{
    // تسجيل كل البيانات الواصلة
    Log::info('updateAndChangeStage called', [
        'deal_id' => $id,
        'all_input' => $request->all(),
        'has_files' => $request->hasFile('documents'),
        'files_count' => $request->hasFile('documents') ? count($request->file('documents')) : 0,
        'method' => $request->method(),
        'content_type' => $request->header('Content-Type')
    ]);

    $deal = Deal::find($id);
    
    if (!$deal) {
        return response()->json(['success' => false, 'message' => 'Deal not found'], 404);
    }

    try {
        DB::beginTransaction();

        // 1. تحديث البيانات الأساسية
        $updateData = [];
        
        $dealFields = [
            'source', 'deal_name', 'unit_no', 'property_type_id', 
            'subcommunity_id', 'bedrooms', 'unit_size', 'area_id',
            'deal_total_amount', 'deal_commission', 'agent_share', 
            'company_share', 'currency', 'responsible_person_id',
            'property_link', 'property_reference','lost_reason'
        ];
        
        foreach ($dealFields as $field) {
            if ($request->has($field) && $request->$field !== null && $request->$field !== '') {
                $updateData[$field] = $request->$field;
                Log::info('Field found', ['field' => $field, 'value' => $request->$field]);
            }
        }
        
        if (!empty($updateData)) {
            $deal->update($updateData);
            Log::info('Deal updated with data', $updateData);
        } else {
            Log::info('No deal fields to update');
        }

        // 2. تحديث الأطراف (parties)
        $this->updatePartiesFromRequest($deal, $request);

        // 3. رفع المستندات ✅ الطريقة الجديدة
        if ($request->hasFile('documents')) {
            Log::info('Documents found in request', [
                'count' => count($request->file('documents')),
                'files' => array_keys($request->file('documents'))
            ]);
            
            $files = $request->file('documents');
            $documentTypes = $request->input('document_types', []);
            $categories = $request->input('categories', []);
            $partyTypes = $request->input('party_types', []);
            
            foreach ($files as $index => $file) {
                $docType = $documentTypes[$index] ?? 'unknown';
                $category = $categories[$index] ?? 'other';
                $partyType = $partyTypes[$index] ?? null;
                
                // البحث عن الـ party المناسب
                $party = null;
                if ($partyType) {
                    $party = $deal->parties()
                        ->where('party_type', $partyType)
                        ->where('party_role', 'primary')
                        ->first();
                }
                
                // التأكد من وجود المجلد
                $storagePath = "deals/{$deal->id}/{$category}";
                
                // رفع الملف
                $path = $file->store($storagePath, 'public');
                
                // حفظ في قاعدة البيانات
                DealDocument::create([
                    'deal_id' => $deal->id,
                    'deal_party_id' => $party?->id,
                    'document_category' => $category,
                    'document_type' => $docType,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'uploaded_by' => auth()->id()
                ]);
                
                Log::info('Document uploaded', [
                    'deal_id' => $deal->id,
                    'file_name' => $file->getClientOriginalName(),
                    'document_type' => $docType,
                    'category' => $category,
                    'party_type' => $partyType
                ]);
            }
        } else {
            Log::info('No documents in request');
        }

        // 4. تغيير المرحلة (guarded by unified validator service)
        if ($request->filled('stage_id')) {
            $guard = app(DealStageValidatorService::class);
            $validation = $guard->validateStageChange($deal, (int) $request->stage_id, $deal->deal_type);

            if (!$validation['valid']) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'valid' => false,
                    'message' => $validation['message'] ?? 'Missing required fields',
                    'missing_fields' => $validation['missing_fields'] ?? [],
                    'grouped_missing' => $validation['grouped_missing'] ?? ['sections' => [], 'by_stage' => []],
                    // Backward-compatible keys
                    'missing_fields_grouped' => $validation['missing_fields_grouped'] ?? ['sections' => []],
                    'missing_by_stage' => $validation['missing_by_stage'] ?? [],
                    'missing_fields_grouped_by_stage' => $validation['missing_fields_grouped_by_stage'] ?? ['stages' => []],
                ], 422);
            }

            $deal->stage_id = $request->stage_id;
            $deal->save();
            Log::info('Stage changed', ['new_stage' => $request->stage_id]);
        }

        // 5. تسجيل التاريخ
        DealHistoryHelper::log($deal->id, [
            'action' => 'stage_changed',
            'user_id' => auth()->id()
        ]);

        DB::commit();

        broadcast(new DealUpdated($deal->fresh(), 'updated'));

        return response()->json([
            'success' => true,
            'message' => 'Deal updated successfully',
            'data' => new DealResource($deal)
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error in updateAndChangeStage', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json([
            'success' => false,
            'message' => 'Failed to update deal',
            'error' => $e->getMessage()
        ], 500);
    }
}

private function updatePartiesFromRequest($deal, $request)
{
    $partyTypes = ['buyer', 'seller', 'tenant', 'landlord'];
    
    foreach ($partyTypes as $type) {
        $partyData = [];
        $prefix = $type . '_';
        
        // الحقول الممكنة للطرف
        $partyFields = [
            'first_name', 'last_name', 'phone', 'email', 'nationality', 
            'dob', 'residency_status', 'city', 'country', 'language', 'amount'
        ];
        
        foreach ($partyFields as $field) {
            $key = $prefix . $field;
            if ($request->has($key) && $request->$key !== null && $request->$key !== '') {
                $dbField = ($field === 'dob') ? 'date_of_birth' : $field;
                $partyData[$dbField] = $request->$key;
            }
        }
        
        if (!empty($partyData)) {
            $party = $deal->parties()
                ->where('party_type', $type)
                ->where('party_role', 'primary')
                ->first();
                
            if ($party) {
                $party->update($partyData);
            } else {
                // التأكد من وجود الاسم الأول والأخير قبل الإنشاء
                if (isset($partyData['first_name']) && isset($partyData['last_name'])) {
                    $deal->parties()->create([
                        'party_type' => $type,
                        'party_role' => 'primary',
                        ...$partyData
                    ]);
                }
            }
        }
    }
}

private function uploadDocuments($deal, $request)
{
    $documents = $request->file('documents');
    
    if (!$documents) return;
    
    // تحويل documents إلى array إذا كان object
    if (!is_array($documents)) {
        $documents = [$documents];
    }
    
    foreach ($documents as $index => $file) {
        // استقبال البيانات من FormData
        $docType = $request->input("documents.{$index}.document_type");
        $category = $request->input("documents.{$index}.category");
        $partyType = $request->input("documents.{$index}.party_type");
        
        if (!$docType) {
            $docType = $request->input("documents.{$index}.document_type");
        }
        
        if (!$category) {
            $category = $request->input("documents.{$index}.category");
        }
        
        if (!$partyType) {
            $partyType = $request->input("documents.{$index}.party_type");
        }
        
        // البحث عن الـ party المناسب
        $party = null;
        if ($partyType) {
            $party = $deal->parties()
                ->where('party_type', $partyType)
                ->where('party_role', 'primary')
                ->first();
        }
        
        // استخدام category كـ default للـ document_category
        $docCategory = $category ?? $partyType ?? 'other';
        
        // التأكد من وجود المجلد
        $storagePath = "deals/{$deal->id}/{$docCategory}";
        
        // رفع الملف
        $path = $file->store($storagePath, 'public');
        
        // حفظ في قاعدة البيانات
        DealDocument::create([
            'deal_id' => $deal->id,
            'deal_party_id' => $party?->id,
            'document_category' => $docCategory,
            'document_type' => $docType ?? 'unknown',
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'uploaded_by' => auth()->id()
        ]);
        
        Log::info('Document uploaded', [
            'deal_id' => $deal->id,
            'file_name' => $file->getClientOriginalName(),
            'document_type' => $docType,
            'party_type' => $partyType
        ]);
    }
}
}