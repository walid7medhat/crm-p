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
use Illuminate\Support\Facades\Log; 
use App\Models\DealDocument;
use App\Models\DealParty;
use App\Models\User;
use App\Helpers\ImageHelper;
use App\Http\Requests\Deal\UpdatePropertyRequest;
use App\Http\Resources\Deal\PropertyDocumentResource;
use App\Models\DealProperty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

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
                'responsiblePerson',
                'parties',
                'properties'
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
                'total' => $deals->total(),
                'current_page' => $deals->currentPage(),
                'last_page' => $deals->lastPage()
            ]
        ]);
    }

    /**
     * 2. جلب صفقة واحدة بالتفصيل
     */
    public function show(Deal $deal)
    {
        $user = auth()->user();
        
        if (!$user->hasAnyRole(['super_admin']) && $user->id != 30) {
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
            'responsiblePerson',
            'parties.documents',
            'documents',
            'properties'
        ]);

        return response()->json([
            'success' => true,
            'data' => new DealResource($deal)
        ]);
    }

    /**
     * 3. تحديث صفقة
     */
    public function update(UpdateDealRequest $request, Deal $deal)
    {
        DB::beginTransaction();
        try {
            $changes = [];
            $oldDeal = $deal->getOriginal();

            // تحديث البيانات الأساسية فقط (الحقول العامة)
            $deal->update($request->only([
                'deal_name',
                'deal_total_amount',
                'deal_commission',
                'agent_share',
                'company_share',
                'property_link',
                'lost_reason',
                'listing_id',
                  'eoi_date', 'booking_date', 'spa_date', 
            'security_deposit_date', 'mou_date', 'noc_date'
            ]));

            if ($deal->getChanges()) {
                $changes['deal'] = [
                    'old' => $oldDeal,
                    'new' => $deal->getChanges()
                ];
            }

            // تحديث الـ Properties
            if ($request->has('properties')) {
                $this->syncProperties($deal, $request->properties);
                $changes['properties'] = 'updated';
            }

            // تحديث الـ Parties
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

            // رفع المستندات
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
                            'watermark' => ['enabled' => false]
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

            // تسجيل التاريخ
            if (!empty($changes)) {
                DealHistoryHelper::log($deal->id, [
                    'action' => 'updated',
                    'changes' => $changes,
                    'user_id' => auth()->id()
                ]);
            }

            $deal->load([
                'stage',
                'parties',
                'documents',
                'responsiblePerson',
                'properties'
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
            ->when($request->deal_type, fn($q) => $q->where('deal_type', $request->deal_type))
            ->orderBy('deal_type')
            ->orderBy('order')
            ->get();
        
        $perPage = $request->input('per_page', 10);
        
        $result = $stages->map(function($stage) use ($user, $request, $perPage) {
            $dealsQuery = Deal::with([
                'lead',
                'responsiblePerson',
                'parties',
                'documents',
                'properties'
            ])
            ->visibleFor($user)
            ->filter($request)
            ->where('stage_id', $stage->id)->orderBy('updated_at','desc');
            
            $totalCount = $dealsQuery->count();
            $stageDeals = $dealsQuery->paginate($perPage, ['*'], 'page', 1);
            
            return [
                'stage_id' => $stage->id,
                'stage_name' => $stage->name,
                'stage_color' => $stage->color,
                'deal_type' => $stage->deal_type,
                'deals_count' => $totalCount,
                'total_count' => $totalCount,
                'current_page' => 1,
                'per_page' => $perPage,
                'deals' => DealResource::collection($stageDeals),
                'has_more_pages' => $stageDeals->hasMorePages()
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }

    /**
     * جلب صفقات مرحلة معينة مع Pagination
     */
    public function getDealsByStage(Request $request)
    {
        $user = auth()->user();
        
        $request->validate([
            'stage_id' => 'required|exists:stages,id',
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100'
        ]);
        
        $stageId = $request->stage_id;
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 10);
        
        $dealsQuery = Deal::with([
            'lead',
            'responsiblePerson',
            'parties',
            'documents',
            'properties'
        ])
        ->visibleFor($user)
        ->filter($request)
        ->where('stage_id', $stageId)->orderBy('updated_at','desc');
        
        $deals = $dealsQuery->paginate($perPage, ['*'], 'page', $page);
        
        return response()->json([
            'success' => true,
            'data' => DealResource::collection($deals),
            'current_page' => $deals->currentPage(),
            'last_page' => $deals->lastPage(),
            'per_page' => $deals->perPage(),
            'total' => $deals->total(),
            'has_more_pages' => $deals->hasMorePages()
        ]);
    }

    /**
     * تعيين شخص مسؤول للصفقة
     */
    public function assignResponsiblePerson(Request $request, Deal $deal): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!($user->hasRole(['super_admin','admin', 'manager', 'team_lead']))) {
                return ApiResponse::error('You are not authorized to assign responsible person', 403);
            }

            $responsiblePerson = User::find($request->responsible_person_id);

            if (!($user->hasRole('admin') || $user->hasRole('super_admin'))) {
                $subordinatesIds = $user->getAllSubordinatesIds();
                if (!in_array($request->responsible_person_id, $subordinatesIds)) {
                    return ApiResponse::error('You can only assign responsible person from your team', 403);
                }
            }
            
            $oldPerson = User::find($deal->responsible_person_id);

            $deal->update([
                'responsible_person_id' => $request->responsible_person_id,
                'last_stage_change_at' => now(),
                'revert' => null,
            ]);
            
            $changes = [
                'old_person_id' => $oldPerson?->id,
                'old_person' => $oldPerson?->name,
                'new_person' => $responsiblePerson?->name
            ];
            
            broadcast(new DealUpdated($deal, 'assigned', null, $changes));
            
            DealHistoryHelper::log($deal->id, [
                'action' => 'assigned',
                'old_person_id' => $oldPerson?->id,
                'old_person' => $oldPerson?->name,
                'new_person' => $responsiblePerson?->name
            ]);

            return ApiResponse::success(
                new DealResource($deal->load(['responsiblePerson', 'stage'])),
                'Responsible person assigned successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to assign responsible person: ' . $e->getMessage());
        }
    }

    /**
     * تاريخ الصفقة
     */
    public function history(Request $request, $dealId)
    {
        $deal = Deal::find($dealId);
        if (!$deal) {
            return response()->json([
                'success' => false,
                'message' => 'Deal not found'
            ], 404);
        }
        
        $query = LeadHistory::where('deal_id', $dealId)
            ->when($deal->lead_id, function($q) use($deal) {
                $q->orWhere('lead_id', $deal->lead_id);
            })
            ->with(['user:id,name,avatar', 'deal:id,deal_name,source,responsible_person_id', 'deal.responsiblePerson:id,name']);
        
        if ($request->filled('search')) {
            $searchTerm = trim((string) $request->search);
            $query->where(function($q) use ($searchTerm) {
                $q->where('changes->action', 'LIKE', "%{$searchTerm}%")
                  ->orWhereRaw('CAST(changes AS CHAR) LIKE ?', ["%{$searchTerm}%"])
                  ->orWhereHas('user', function($userQuery) use ($searchTerm) {
                      $userQuery->where('name', 'LIKE', "%{$searchTerm}%");
                  })
                  ->orWhereHas('deal', function($dealQuery) use ($searchTerm) {
                      $dealQuery->where('deal_name', 'LIKE', "%{$searchTerm}%")
                          ->orWhere('source', 'LIKE', "%{$searchTerm}%")
                        //   ->orWhere('unit_no', 'LIKE', "%{$searchTerm}%")
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

        $actionFilter = $request->input('action', $request->input('event_type'));
        if (!empty($actionFilter)) {
            $query->where('changes->action', $actionFilter);
        }

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

    /**
     * تسجيل مشاهدة الصفقة في التاريخ
     */
    public function view_history($id)
    {
        try {
            $deal = Deal::find($id);
            DealHistoryHelper::log($deal->id, ['action' => 'view']);

            return ApiResponse::success(
                new DealResource($deal),
                'Lead history saved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve lead: ' . $e->getMessage());
        }
    }

    /**
     * التحقق من متطلبات المرحلة
     */
    public function checkStageRequirements(CheckStageRequirementsRequest $request, DealStageValidator $validator)
    {
        $deal = Deal::find($request->deal_id);
        
        if (!$deal) {
            return response()->json([
                'success' => false,
                'message' => 'Deal not found'
            ], 404);
        }

        $deal->load(['parties', 'documents', 'properties']);
        
        $guard = app(DealStageValidatorService::class);
        
        $result = $guard->validateStageChange(
            $deal, 
            (int) $request->target_stage_id, 
            $request->deal_type,
            $request->listing_id
        );

        $response = [
            'success' => $result['valid'],
            'valid' => $result['valid'],
            'missing_fields' => $result['missing_fields'] ?? [],
            'grouped_missing' => $result['grouped_missing'] ?? ['sections' => [], 'by_stage' => []],
            'grouped_by_stage' => $result['grouped_by_stage'] ?? [],
            'message' => $result['message'] ?? ($result['valid'] ? 'Validation passed' : 'Missing required fields'),
            'has_listing_id' => $result['has_listing_id'] ?? false,
            'deal_type' => $result['deal_type'] ?? $request->deal_type,
                'required_fields' => $result['required_fields'] ?? [],
        ];

        if (!empty($result['missing_fields'])) {
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
            
            $dealData = array_filter($dealData, function($value, $key) {
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

            // 2. تحديث الـ Buyer
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
                    if (isset($buyerData['first_name']) && isset($buyerData['last_name'])) {
                        $deal->parties()->create([
                            'party_type' => 'buyer',
                            'party_role' => 'primary',
                            ...$buyerData
                        ]);
                    }
                }
            }

            // 3. تحديث الـ Seller
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

            // 4. تحديث الـ Tenant
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

            // 5. تحديث الـ Landlord
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

            // 6. تحديث الـ Properties
            if ($request->has('properties')) {
                $this->syncProperties($deal, $request->properties);
            }

            DealHistoryHelper::log($deal->id, [
                'action' => 'updated',
                'user_id' => auth()->id()
            ]);

            DB::commit();

            $deal->load(['parties', 'documents', 'stage', 'properties']);
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

    /**
     * الحصول على الحقول المطلوبة لمرحلة معينة
     */
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

    /**
     * تحديث المرحلة والمعلومات معاً
     */
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

            // 1. تحديث المرحلة
            if ($request->filled('stage_id')) {
                $guard = app(DealStageValidatorService::class);
                $paymentProofRootCount = count($this->extractRootKeyedValidFiles($request, 'payment_proof'));
                $newPaymentProofUploads = $paymentProofRootCount > 0
                    ? $paymentProofRootCount
                    : $this->countPropertyPaymentProofViaDocumentsSlots($request);
                $validation = $guard->validateStageChange(
                    $deal,
                    (int) $request->stage_id,
                    $deal->deal_type,
                    null,
                    ['new_payment_proof_uploads' => $newPaymentProofUploads]
                );

                if (!$validation['valid']) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'valid' => false,
                        'message' => $validation['message'] ?? 'Missing required fields',
                        'missing_fields' => $validation['missing_fields'] ?? [],
                        'grouped_missing' => $validation['grouped_missing'] ?? ['sections' => [], 'by_stage' => []],
                        'grouped_by_stage' => $validation['grouped_by_stage'] ?? ($validation['missing_by_stage'] ?? []),
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
                'source', 'deal_name', 'deal_total_amount', 'deal_commission', 
                'agent_share', 'company_share', 'currency', 'responsible_person_id',
                 'lost_reason'
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

            // 3. تحديث الـ Properties
            if ($request->has('properties')) {
                $this->syncProperties($deal, $request->properties);
                $changes['properties'] = 'updated';
            }

            // 4. تحديث الـ parties
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
                        $deal->parties()->create([
                            'party_type' => $type,
                            'party_role' => 'primary',
                            ...$partyData
                        ]);
                    }
                    $changes['parties'][$type] = $partyData;
                }
            }

            // 5. حفظ التاريخ
            if (!empty($changes)) {
                DealHistoryHelper::log($deal->id, [
                    'action' => 'stage_changed',
                    'changes' => $changes,
                    'user_id' => auth()->id()
                ]);
            }

            DB::commit();

            broadcast(new DealUpdated($deal->fresh(), 'stage_changed'));

            return response()->json([
                'success' => true,
                'message' => 'Deal stage and info updated successfully',
                'data' => new DealResource($deal->load(['parties', 'documents', 'stage', 'properties']))
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
     * تغيير المرحلة فقط
     */
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
                'grouped_by_stage' => $validation['grouped_by_stage'] ?? ($validation['missing_by_stage'] ?? []),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $oldStageId = $deal->stage_id;
            $deal->stage_id = $newStageId;
            $deal->save();
        $this->updateStageDate($deal, $newStageId);

            DealHistoryHelper::log($deal->id, [
                'action' => 'stage_changed',
                'old_stage' => $oldStageId,
                'new_stage' => $newStageId,
                'user_id' => auth()->id()
            ]);

            DB::commit();

            $deal->load(['stage', 'parties', 'documents', 'properties']);
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

    /**
     * تحديث وتغيير المرحلة معاً (للمودال)
     */
    public function updateAndChangeStage(Request $request, $id)
    {
        Log::info('updateAndChangeStage called', [
            'deal_id' => $id,
            'all_input' => $request->all(),
            'has_files' => $request->hasFile('documents'),
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
                'source', 'deal_name', 'deal_total_amount', 'deal_commission', 
                'agent_share', 'company_share', 'currency', 'responsible_person_id',
                'property_link', 'property_reference', 'lost_reason', 'eoi_date', 'booking_date', 'spa_date', 
            'security_deposit_date', 'mou_date', 'noc_date'
            ];
            
            foreach ($dealFields as $field) {
                if ($request->has($field) && $request->$field !== null && $request->$field !== '') {
                    $updateData[$field] = $request->$field;
                }
            }
            
            if (!empty($updateData)) {
                $deal->update($updateData);
            }

            // 2. تحديث الأطراف
            $this->updatePartiesFromRequest($deal, $request);

            // 3. تحديث الـ Properties — مصفوفة كاملة، أو حقول مسطحة من مودال عرض الصفقة (property_details)
            if ($request->has('properties')) {
                $this->syncProperties($deal, $request->properties);
            } else {
                $this->syncPrimaryPropertyFromFlatRequest($deal, $request);
            }

            // 3b. Root-level property files (payment_proof[*], spa_document[*]) —
            // merged onto the primary property even when properties JSON exists.
            $this->mergeRootPropertyFilesOntoPrimaryProperty($deal, $request);

            // 4. رفع المستندات
            if ($request->hasFile('documents')) {
                $files = $request->file('documents');
                $documentTypes = $request->input('document_types', []);
                $categories = $request->input('categories', []);
                $partyTypes = $request->input('party_types', []);
                
                foreach ($files as $index => $file) {
                    $docType = $documentTypes[$index] ?? 'unknown';
                    $category = $categories[$index] ?? 'other';
                    $partyType = $partyTypes[$index] ?? null;
                    
                    $party = null;
                    if ($partyType) {
                        $party = $deal->parties()
                            ->where('party_type', $partyType)
                            ->where('party_role', 'primary')
                            ->first();
                    }
                    
                    $storagePath = "deals/{$deal->id}/{$category}";
                    $path = $file->store($storagePath, 'public');
                    
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
                }
            }

            // 5. تغيير المرحلة
            if ($request->filled('stage_id')) {
                $guard = app(DealStageValidatorService::class);
                $paymentProofRootCount = count($this->extractRootKeyedValidFiles($request, 'payment_proof'));
                $newPaymentProofUploads = $paymentProofRootCount > 0
                    ? $paymentProofRootCount
                    : $this->countPropertyPaymentProofViaDocumentsSlots($request);
                $validation = $guard->validateStageChange(
                    $deal,
                    (int) $request->stage_id,
                    $deal->deal_type,
                    null,
                    ['new_payment_proof_uploads' => $newPaymentProofUploads]
                );

                if (!$validation['valid']) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'valid' => false,
                        'message' => $validation['message'] ?? 'Missing required fields',
                        'missing_fields' => $validation['missing_fields'] ?? [],
                        'grouped_missing' => $validation['grouped_missing'] ?? ['sections' => [], 'by_stage' => []],
                        'grouped_by_stage' => $validation['grouped_by_stage'] ?? ($validation['missing_by_stage'] ?? []),
                    ], 422);
                }

                $deal->stage_id = $request->stage_id;
                $deal->save();
                  $this->updateStageDate($deal, (int) $request->stage_id);
            }

            // 6. تسجيل التاريخ
            DealHistoryHelper::log($deal->id, [
                'action' => 'stage_changed',
                'user_id' => auth()->id()
            ]);

            DB::commit();

            broadcast(new DealUpdated($deal->fresh(), 'updated'));

            return response()->json([
                'success' => true,
                'message' => 'Deal updated successfully',
                'data' => new DealResource($deal->load(['parties', 'documents', 'stage', 'properties']))
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

    /**
     * تحديث الأطراف من الـ Request
     */
    private function updatePartiesFromRequest($deal, $request)
    {
        $partyTypes = ['buyer', 'seller', 'tenant', 'landlord'];
        
        foreach ($partyTypes as $type) {
            $partyData = [];
            $prefix = $type . '_';
            
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

    /**
     * حذف مستند
     */
    public function deleteDocument($id)
    {
        try {
            $document = DealDocument::find($id);
            $deal = $document->deal;
            
            if (!$document) {
                return response()->json([
                    'success' => false,
                    'message' => 'Document not found'
                ], 404);
            }

            if (\Storage::disk('public')->exists($document->file_path)) {
                \Storage::disk('public')->delete($document->file_path);
            }

            $document->delete();
            broadcast(new DealUpdated($deal->fresh(), 'updated'));
            
            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Delete failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update or create the primary deal_property row from flat multipart fields (view-deal inline save).
     * Only keys present on the request are applied so other sections can save without wiping property data.
     */
    private function syncPrimaryPropertyFromFlatRequest(Deal $deal, Request $request): void
    {
        $scalarKeys = [
            'unit_no',
            'property_type_id',
            'bedrooms',
            'unit_size',
            'area_id',
            'project_id',
            'listing_id',
            'developer_id',
            'developer_name',
            'developer_phone',
            'budget_from',
            'budget_to',
            'purchase_price',
            'rental_price',
            'commission',
        ];

        $data = [];
        foreach ($scalarKeys as $key) {
            if (!$request->has($key)) {
                continue;
            }
            $val = $request->input($key);
            if ($val === '' || $val === null) {
                $data[$key] = null;
            } else {
                $data[$key] = $val;
            }
        }

        if (empty($data)) {
            return;
        }

        $property = $deal->properties()->orderBy('sort_order')->orderBy('id')->first();

        if ($property) {
            $property->update($data);

            return;
        }

        // No row yet: create if we have enough to show as a property (area and/or unit)
        if (($data['area_id'] ?? null) || ($data['unit_no'] ?? null)) {
            $deal->properties()->create(array_merge($data, [
                'sort_order' => 0,
            ]));
        }
    }

    /**
     * True when multipart key belongs to root uploads like payment_proof, payment_proof[0], spa_document.1
     */
    private function isRootFileFieldKey(string $key, string $prefix): bool
    {
        if ($key === $prefix) {
            return true;
        }
        $len = strlen($prefix);
        if (strlen($key) <= $len || substr($key, 0, $len) !== $prefix) {
            return false;
        }
        $next = $key[$len];

        return $next === '[' || $next === '.';
    }

    /**
     * @return list<UploadedFile>
     */
    private function flattenValidUploadedFiles(mixed $node): array
    {
        if ($node instanceof UploadedFile) {
            return $node->isValid() ? [$node] : [];
        }
        if (! is_array($node)) {
            return [];
        }
        $out = [];
        foreach ($node as $child) {
            $out = array_merge($out, $this->flattenValidUploadedFiles($child));
        }

        return $out;
    }

    /**
     * Collect valid uploads for root keys payment_proof* / spa_document* (handles payment_proof[0], payment_proof[], Laravel dot keys).
     *
     * @return list<UploadedFile>
     */
    private function extractRootKeyedValidFiles(Request $request, string $prefix): array
    {
        $out = [];
        foreach ($request->allFiles() as $key => $payload) {
            if (! $this->isRootFileFieldKey((string) $key, $prefix)) {
                continue;
            }
            $out = array_merge($out, $this->flattenValidUploadedFiles($payload));
        }

        return $out;
    }

    /**
     * Property payment proofs sent via documents[] + categories + document_types (when not split to root keys).
     */
    private function countPropertyPaymentProofViaDocumentsSlots(Request $request): int
    {
        if (! $request->hasFile('documents')) {
            return 0;
        }
        $files = $request->file('documents');
        $types = $request->input('document_types', []);
        $categories = $request->input('categories', []);
        $list = is_array($files) && ! ($files instanceof UploadedFile)
            ? $files
            : [$files];
        $count = 0;
        foreach ($list as $index => $file) {
            if (! ($file instanceof UploadedFile) || ! $file->isValid()) {
                continue;
            }
            $cat = strtolower((string) ($categories[$index] ?? ''));
            $typ = strtolower((string) ($types[$index] ?? ''));
            if ($cat === 'property' && ($typ === 'payment_proof' || $typ === 'payment')) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Append SPA / Payment Proof uploads from multipart root keys onto the primary deal property (no properties[] payload).
     */
    private function mergeRootPropertyFilesOntoPrimaryProperty(Deal $deal, Request $request): void
{
    $properties = $deal->properties()
        ->orderBy('sort_order')
        ->orderBy('id')
        ->get();

    if ($properties->isEmpty()) {
        return;
    }

    foreach ($properties as $index => $property) {

        $paymentFiles = $this->extractPropertyFiles($request, 'payment_proof', $index);
        $spaFiles = $this->extractPropertyFiles($request, 'spa_document', $index);
        $eoiFiles = $this->extractPropertyFiles($request, 'eoi_documents', $index);
        $bookingFiles = $this->extractPropertyFiles($request, 'booking_documents', $index);
        $mouFiles = $this->extractPropertyFiles($request, 'mou_documents', $index);
        $nocFiles = $this->extractPropertyFiles($request, 'noc_documents', $index);

        $changed = false;

        // =========================
        // Payment Proof
        // =========================
        if (!empty($paymentFiles)) {

            $existing = is_array($property->payment_proof)
                ? $property->payment_proof
                : [];

            foreach ($paymentFiles as $file) {

                $path = $file->store(
                    "deals/{$deal->id}/properties/{$property->id}/payment_proof",
                    'public'
                );

                $existing[] = [
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ];
            }

            $property->payment_proof = $existing;
            $changed = true;
        }

        // =========================
        // SPA Document
        // =========================
        if (!empty($spaFiles)) {

            $existing = is_array($property->spa_document)
                ? $property->spa_document
                : [];

            foreach ($spaFiles as $file) {

                $path = $file->store(
                    "deals/{$deal->id}/properties/{$property->id}/spa_document",
                    'public'
                );

                $existing[] = [
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ];
            }

            $property->spa_document = $existing;
            $changed = true;
        }

        // =========================
        // EOI documents (per property index)
        // =========================
        if (!empty($eoiFiles)) {
            $existing = is_array($property->eoi_documents) ? $property->eoi_documents : [];
            foreach ($eoiFiles as $file) {
                $path = $file->store(
                    "deals/{$deal->id}/properties/{$property->id}/eoi_documents",
                    'public'
                );
                $existing[] = [
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ];
            }
            $property->eoi_documents = array_values($existing);
            $changed = true;
        }

        // =========================
        // Booking documents (per property index)
        // =========================
        if (!empty($bookingFiles)) {
            $existing = is_array($property->booking_documents) ? $property->booking_documents : [];
            foreach ($bookingFiles as $file) {
                $path = $file->store(
                    "deals/{$deal->id}/properties/{$property->id}/booking_documents",
                    'public'
                );
                $existing[] = [
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ];
            }
            $property->booking_documents = array_values($existing);
            $changed = true;
        }

        // =========================
        // MOU documents (per property index)
        // =========================
        if (!empty($mouFiles)) {
            $existing = is_array($property->mou_documents) ? $property->mou_documents : [];
            foreach ($mouFiles as $file) {
                $path = $file->store(
                    "deals/{$deal->id}/properties/{$property->id}/mou_documents",
                    'public'
                );
                $existing[] = [
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ];
            }
            $property->mou_documents = array_values($existing);
            $changed = true;
        }

        // =========================
        // NOC documents (per property index)
        // =========================
        if (!empty($nocFiles)) {
            $existing = is_array($property->noc_documents) ? $property->noc_documents : [];
            foreach ($nocFiles as $file) {
                $path = $file->store(
                    "deals/{$deal->id}/properties/{$property->id}/noc_documents",
                    'public'
                );
                $existing[] = [
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ];
            }
            $property->noc_documents = array_values($existing);
            $changed = true;
        }

        if ($changed) {
            $property->save();
        }
    }
}

    /**
     * مزامنة الـ Properties (Multi Properties)
     */
    private function syncProperties(Deal $deal, $propertiesData)
{
    // ✅ تحويل string إلى array إذا لزم الأمر
    if (is_string($propertiesData)) {
        $decoded = json_decode($propertiesData, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $propertiesData = $decoded;
        } else {
            // محاولة فك ترميز URL-encoded string
            parse_str($propertiesData, $parsed);
            if (isset($parsed['properties']) && is_array($parsed['properties'])) {
                $propertiesData = $parsed['properties'];
            } elseif (isset($parsed[0]) && is_array($parsed[0])) {
                $propertiesData = $parsed;
            } else {
                Log::warning('syncProperties: Could not decode properties data', [
                    'type' => gettype($propertiesData),
                    'data' => substr($propertiesData, 0, 500)
                ]);
                $propertiesData = [];
            }
        }
    }
    
    // ✅ تأكد أنه array
    if (!is_array($propertiesData)) {
        Log::error('syncProperties: propertiesData is not an array', [
            'type' => gettype($propertiesData)
        ]);
        $propertiesData = [];
    }
    
    $deal->properties()->delete();
    
    if (empty($propertiesData)) {
        return;
    }
    
    foreach ($propertiesData as $index => $propertyData) {
        // تأكد أن propertyData هو array
        if (!is_array($propertyData)) {
            continue;
        }
        
        $deal->properties()->create([
            'sort_order' => $index,
            'unit_no' => $propertyData['unit_no'] ?? null,
            'property_type_id' => $propertyData['property_type_id'] ?? null,
            'bedrooms' => $propertyData['bedrooms'] ?? null,
            'unit_size' => $propertyData['unit_size'] ?? null,
            'area_id' => $propertyData['area_id'] ?? null,
            'project_id' => $propertyData['project_id'] ?? null,
            'listing_id' => $propertyData['listing_id'] ?? null,
            'developer_id' => $propertyData['developer_id'] ?? null,
            'developer_name' => $propertyData['developer_name'] ?? null,
            'developer_phone' => $propertyData['developer_phone'] ?? null,
            'budget_from' => $propertyData['budget_from'] ?? null,
            'budget_to' => $propertyData['budget_to'] ?? null,
            'purchase_price' => $propertyData['purchase_price'] ?? null,
            'rental_price' => $propertyData['rental_price'] ?? null,
            'payment_proof' => $propertyData['payment_proof'] ?? null,
            'spa_document' => $propertyData['spa_document'] ?? null,
            'contract_document' => $propertyData['contract_document'] ?? null,
            'ejari_document' => $propertyData['ejari_document'] ?? null,
            'eoi_documents' => $propertyData['eoi_documents'] ?? null,
            'booking_documents' => $propertyData['booking_documents'] ?? null,
            'mou_documents' => $propertyData['mou_documents'] ?? null,
            'noc_documents' => $propertyData['noc_documents'] ?? null,
        ]);
    }
}

    /**
     * جلب الـ Properties لصفقة معينة
     */
    public function getProperties(Deal $deal)
    {
        return response()->json([
            'success' => true,
            'data' => $deal->properties()->with(['propertyType', 'area', 'developer'])->get()
        ]);
    }

     public function updateProperty(UpdatePropertyRequest $request, Deal $deal, DealProperty $property)
{
    // Check authorization
    if (!$this->authorizeAccess($deal)) {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    try {
        DB::beginTransaction();

        // Update text fields
        $property->update($request->only([
            'unit_no', 'property_type_id', 'bedrooms', 'unit_size',
            'area_id', 'listing_id', 'developer_id', 'developer_name', 'developer_phone',
            'budget_from', 'budget_to', 'purchase_price', 'commission'
        ]));

        // ✅ Handle EOI Documents (append)
        if ($request->hasFile('eoi_documents')) {
            $existing = is_array($property->eoi_documents) ? $property->eoi_documents : [];
            $newEoiDocs = [];
            foreach ($request->file('eoi_documents') as $file) {
                $path = $file->store("deals/{$deal->id}/properties/eoi_documents", 'public');
                $newEoiDocs[] = [
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ];
            }
            $property->eoi_documents = array_values(array_merge($existing, $newEoiDocs));
        }

        // ✅ Handle Booking Documents (append)
        if ($request->hasFile('booking_documents')) {
            $existing = is_array($property->booking_documents) ? $property->booking_documents : [];
            $newBookingDocs = [];
            foreach ($request->file('booking_documents') as $file) {
                $path = $file->store("deals/{$deal->id}/properties/booking_documents", 'public');
                $newBookingDocs[] = [
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ];
            }
            $property->booking_documents = array_values(array_merge($existing, $newBookingDocs));
        }

        // Handle payment_proof files (append)
        if ($request->hasFile('payment_proof')) {
            $existing = is_array($property->payment_proof) ? $property->payment_proof : [];
            $newProof = [];
            foreach ($request->file('payment_proof') as $file) {
                $path = $file->store("deals/{$deal->id}/properties/payment_proof", 'public');
                $newProof[] = [
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ];
            }
            $property->payment_proof = array_values(array_merge($existing, $newProof));
        }

        // Handle spa_document files (append)
        if ($request->hasFile('spa_document')) {
            $existing = is_array($property->spa_document) ? $property->spa_document : [];
            $newSpa = [];
            foreach ($request->file('spa_document') as $file) {
                $path = $file->store("deals/{$deal->id}/properties/spa_document", 'public');
                $newSpa[] = [
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ];
            }
            $property->spa_document = array_values(array_merge($existing, $newSpa));
        }

        // ✅ Handle MOU Documents (append)
        if ($request->hasFile('mou_documents')) {
            $existing = is_array($property->mou_documents) ? $property->mou_documents : [];
            $newMou = [];
            foreach ($request->file('mou_documents') as $file) {
                $path = $file->store("deals/{$deal->id}/properties/mou_documents", 'public');
                $newMou[] = [
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ];
            }
            $property->mou_documents = array_values(array_merge($existing, $newMou));
        }

        // ✅ Handle NOC Documents (append)
        if ($request->hasFile('noc_documents')) {
            $existing = is_array($property->noc_documents) ? $property->noc_documents : [];
            $newNoc = [];
            foreach ($request->file('noc_documents') as $file) {
                $path = $file->store("deals/{$deal->id}/properties/noc_documents", 'public');
                $newNoc[] = [
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ];
            }
            $property->noc_documents = array_values(array_merge($existing, $newNoc));
        }

        $property->save();

        DB::commit();

        // Load relationships for response
        $property->load(['propertyType', 'area', 'developer']);

        $payload = $property->toArray();
        $payload['payment_proof'] = (new PropertyDocumentResource($property->payment_proof, 'payment_proof'))->resolve($request);
        $payload['spa_document'] = (new PropertyDocumentResource($property->spa_document, 'spa'))->resolve($request);
        $payload['eoi_documents'] = (new PropertyDocumentResource($property->eoi_documents, 'eoi'))->resolve($request);
        $payload['booking_documents'] = (new PropertyDocumentResource($property->booking_documents, 'booking'))->resolve($request);
        $payload['mou_documents'] = (new PropertyDocumentResource($property->mou_documents, 'mou'))->resolve($request);
        $payload['noc_documents'] = (new PropertyDocumentResource($property->noc_documents, 'noc'))->resolve($request);

        return response()->json([
            'success' => true,
            'data' => $payload,
            'message' => 'Property updated successfully'
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Error updating property: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to update property: ' . $e->getMessage()
        ], 500);
    }
}

    private function authorizeAccess($deal)
    {
        $user = auth()->user();
        if ($user->hasAnyRole(['super_admin']) || $user->id==30) {
            return true;
        }
        
        if ($user->hasAnyRole(['manager', 'team_lead', 'admin'])) {
            $subordinatesIds = $user->getAllSubordinatesIds();
            return in_array($deal->responsible_person_id, array_merge($subordinatesIds, [$user->id])) 
                   || $deal->added_by == $user->id;
        }
        
        return $deal->responsible_person_id == $user->id || $deal->added_by == $user->id;
    }


public function deletePropertyDocument(Request $request)
{
    try {
        $deal = Deal::find($request->deal_id);
        $property = null;
        if ($request->filled('property_id')) {
            $property = DealProperty::find($request->property_id);
        }
        if (!$property && $deal) {
            $property = $deal->properties()->orderBy('sort_order')->orderBy('id')->first();
        }
        
        if (!$deal || !$property) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        if (!$this->authorizeAccess($deal)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        
        // ✅ دعم جميع أنواع المستندات
        $validDocumentTypes = ['payment_proof', 'spa_document', 'eoi_documents', 'booking_documents', 'mou_documents', 'noc_documents'];
        $documentType = $request->document_type;
        
        if (!in_array($documentType, $validDocumentTypes, true)) {
            return response()->json(['success' => false, 'message' => 'Invalid document type'], 422);
        }

        $filePath = (string) $request->file_path;
        $normalizedInputPath = ltrim(parse_url($filePath, PHP_URL_PATH) ?: $filePath, '/');
        $inputBasename = $normalizedInputPath ? basename($normalizedInputPath) : null;
        
        // حذف الملف من التخزين
        if ($normalizedInputPath !== '' && \Storage::disk('public')->exists($normalizedInputPath)) {
            \Storage::disk('public')->delete($normalizedInputPath);
        }
        
        // DealProperty casts payment_proof/spa_document to array
        $currentDocs = $property->{$documentType};
        if (!is_array($currentDocs)) {
            if (is_string($currentDocs) && $currentDocs !== '') {
                $decoded = json_decode($currentDocs, true);
                $currentDocs = is_array($decoded) ? $decoded : [];
            } else {
                $currentDocs = [];
            }
        }

        $filteredDocs = array_values(array_filter($currentDocs, function ($doc) use ($normalizedInputPath, $inputBasename) {
            $docPath = (string) ($doc['path'] ?? $doc['file_path'] ?? '');
            $docUrl = (string) ($doc['url'] ?? $doc['file_url'] ?? '');
            $docOriginalName = (string) ($doc['original_name'] ?? $doc['file_name'] ?? $doc['name'] ?? '');

            $normalizedDocPath = ltrim(parse_url($docPath, PHP_URL_PATH) ?: $docPath, '/');
            $normalizedDocUrlPath = ltrim(parse_url($docUrl, PHP_URL_PATH) ?: $docUrl, '/');
            $docBasename = $normalizedDocPath ? basename($normalizedDocPath) : ($normalizedDocUrlPath ? basename($normalizedDocUrlPath) : null);

            $matchesByPath = $normalizedInputPath !== '' && (
                $normalizedDocPath === $normalizedInputPath ||
                $normalizedDocUrlPath === $normalizedInputPath
            );
            $matchesByBasename = $inputBasename && $docBasename && $inputBasename === $docBasename;
            $matchesByOriginalName = $inputBasename && $docOriginalName !== '' && $docOriginalName === $inputBasename;

            // Keep only docs that DO NOT match the delete target.
            return !($matchesByPath || $matchesByBasename || $matchesByOriginalName);
        }));
        
        $property->{$documentType} = !empty($filteredDocs) ? $filteredDocs : null;
        $property->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Document deleted successfully'
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Delete failed: ' . $e->getMessage()
        ], 500);
    }
}
public function addProperty(Request $request, Deal $deal)
{
    if (!$this->authorizeAccess($deal)) {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
    }
    
    // ✅ Validation rules
    $validated = $request->validate([
        'unit_no' => 'required|string|max:255',
        'property_type_id' => 'required|exists:property_types,id',
        'bedrooms' => 'nullable|string|max:50',
        'unit_size' => 'required|numeric|min:0',
        'area_id' => 'required|exists:areas,id',
        'developer_id' => 'required|exists:developers,id',
        'developer_name' => 'required|string|max:255',
        'developer_phone' => 'required|string|max:50',
        'budget_from' => 'nullable|numeric|min:0',
        'budget_to' => 'nullable|numeric|min:0|gte:budget_from',
        'purchase_price' => 'nullable|numeric|min:0',
        'commission' => 'nullable|numeric|min:0|max:100',
    ]);
    
    try {
        DB::beginTransaction();
        
        $property = $deal->properties()->create([
            'sort_order' => $deal->properties()->count(),
            'unit_no' => $validated['unit_no'] ?? null,
            'property_type_id' => $validated['property_type_id'] ?? null,
            'bedrooms' => $validated['bedrooms'] ?? null,
            'unit_size' => $validated['unit_size'] ?? null,
            'area_id' => $validated['area_id'] ?? null,
            'listing_id' => $request->input('listing_id'),
            'developer_id' => $validated['developer_id'] ?? null,
            'developer_name' => $validated['developer_name'] ?? null,
            'developer_phone' => $validated['developer_phone'] ?? null,
            'budget_from' => $validated['budget_from'] ?? null,
            'budget_to' => $validated['budget_to'] ?? null,
            'purchase_price' => $validated['purchase_price'] ?? null,
            'commission' => $validated['commission'] ?? null,
        ]);
        
        DB::commit();
        
        // Load relationships for response
        $property->load(['propertyType', 'area', 'developer']);
        
        return response()->json([
            'success' => true,
            'data' => $property,
            'message' => 'Property added successfully'
        ]);
        
    } catch (\Illuminate\Validation\ValidationException $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Failed to add property: ' . $e->getMessage()
        ], 500);
    }
}
private function extractPropertyFiles(Request $request, string $key, int $index): array
{
    $files = $request->file($key);

    if (!is_array($files)) {
        return [];
    }

    $fileGroup = $files[$index] ?? null;

    if (!$fileGroup) {
        return [];
    }

    return $this->flattenValidUploadedFiles($fileGroup);
}
   private function getStageDateField(int $stageOrder, string $dealType): ?string
    {
        $dateFieldMap = [
            'primary' => [
                2 => 'eoi_date',
                3 => 'booking_date',
                4 => 'spa_date',
                5 => 'won_date',
            ],
            'secondary' => [
                2 => 'security_deposit_date',
                3 => 'mou_date',
                4 => 'noc_date',
                5 => 'won_date',
            ],
            // 'rental' => [
            //     2 => 'application_date',
            //     3 => 'contract_date',
            //     4 => 'ejari_date',
            //     5 => 'won_date',
            // ],
        ];

        return $dateFieldMap[$dealType][$stageOrder] ?? null;
    }

    /**
     * تحديث تاريخ المرحلة تلقائياً عند الوصول إليها
     */
    private function updateStageDate(Deal $deal, int $newStageId)
    {
        $stage = Stage::find($newStageId);
        if (!$stage) {
            return;
        }

        $stageOrder = (int) $stage->order;
        $dealType = $deal->deal_type;
        $dateField = $this->getStageDateField($stageOrder, $dealType);

        if ($dateField && empty($deal->$dateField)) {
            $deal->$dateField = Carbon::now();
            $deal->save();

            Log::info('Stage date auto-updated', [
                'deal_id' => $deal->id,
                'stage' => $stage->name,
                'field' => $dateField,
                'date' => $deal->$dateField
            ]);
        }
    }
}