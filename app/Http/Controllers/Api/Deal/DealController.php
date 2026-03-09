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
use App\Http\Requests\Deal\UpdateDealStageRequest;

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
            'responsiblePerson',
            'parties','documents'
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
    public function checkStageRequirements(CheckStageRequirementsRequest $request, DealStageValidator $validator)
{
    $deal = Deal::find($request->deal_id);
    
    if (!$deal) {
        return response()->json([
            'success' => false,
            'message' => 'Deal not found'
        ], 404);
    }

    $result = $validator->validate($deal, $request->target_stage_id, $request->deal_type);

    return response()->json([
        'success' => true,
        ...$result
    ]);
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

        // 1. تحديث المرحلة إذا وجدت
        if ($request->filled('stage_id')) {
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
            'amount', 'property_link', 'property_reference'
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

    try {
        DB::beginTransaction();

        $oldStageId = $deal->stage_id;
        $newStageId = $request->stage_id;

        if (!$newStageId) {
            return response()->json([
                'success' => false,
                'message' => 'Stage ID is required'
            ], 400);
        }

        // تحديث المرحلة
        $deal->stage_id = $newStageId;
        $deal->save();

        // تسجيل التاريخ
        DealHistoryHelper::log($deal->id, [
            'action' => 'stage_changed',
            'old_stage' => $oldStageId,
            'new_stage' => $newStageId,
            'user_id' => auth()->id()
        ]);

        DB::commit();

        // Load fresh data for broadcast
        $deal->load(['stage', 'parties', 'documents']);

        // broadcast
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
    $requiredFields = $validator->getRequiredFieldsForStage($deal, $targetStage->order, $request->deal_type);
    
    return response()->json([
        'success' => true,
        'required_fields' => $requiredFields
    ]);
}
}