<?php

namespace App\Http\Controllers\Api\Deal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Deal\ConvertLeadRequest;
use App\Http\Resources\Deal\DealResource;
use App\Models\Lead;
use App\Models\Deal;
use App\Models\Stage;
use App\Models\DealDocument;
use App\Models\DealParty;
use App\Helpers\ImageHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Helpers\DealHistoryHelper;
use App\Helpers\LeadHistoryHelper;
use App\Events\DealUpdated;
use App\Events\LeadUpdated;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
class LeadConversionController extends Controller
{
   public function convert(Request $request)
    {
        $leadId = $request->input('lead_id')
            ?? $request->input('leadId')
            ?? $request->input('id');

        Log::info('Lead conversion request received', [
            'lead_id' => $leadId,
            'deal_type' => $request->deal_type,
            'payload' => $request->all(),
            'user_id' => auth()->id(),
        ]);

        if (empty($leadId)) {
            return response()->json([
                'success' => false,
                'message' => 'Lead ID is required',
                'debug' => [
                    'received_lead_id' => $leadId,
                    'payload' => $request->all(),
                ],
            ], 422);
        }

        $user = auth()->user();
        $lead = Lead::find($leadId);
        if (!$lead) {
            return response()->json([
                'success' => false,
                'message' => 'Lead not found'
            ], 404);
        }
        if($lead){
        if (!$user->hasAnyRole(['super_admin'])) {
            $canAccess = false;
            
            if ($user->hasAnyRole(['manager', 'team_lead', 'admin'])) {
                $subordinatesIds = $user->getAllSubordinatesIds();
                $canAccess = in_array($lead->responsible_person_id, array_merge($subordinatesIds, [$user->id])) 
                        || in_array($lead->added_by, $subordinatesIds);
            } else {
                $canAccess = $lead->responsible_person_id == $user->id 
                        || $lead->added_by == $user->id;
            }
            
            if (!$canAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to convert this lead'
                ], 403);
            }
        }
        }

        if ($lead->converted_to_deal_id) {
            return response()->json([
                'success' => false,
                'message' => 'Lead already converted to deal',
                'deal_id' => $lead->converted_to_deal_id
            ], 400);
        }
        
       

        $stage = Stage::where('stage_type', 'deal')
            ->where('deal_type', $request->deal_type)
            ->orderBy('order')
            ->first();
        if (!$stage) {
            $fallbackStage = Stage::where('stage_type', 'deal')
                ->orderBy('order')
                ->first();

            if (!$fallbackStage) {
                return response()->json([
                    'success' => false,
                    'message' => 'No deal stage configured. Please create at least one deal stage in Settings.'
                ], 422);
            }

            Log::warning('No deal stage found for requested deal_type, using fallback stage', [
                'requested_deal_type' => $request->deal_type,
                'fallback_stage_id' => $fallbackStage->id,
                'fallback_stage_deal_type' => $fallbackStage->deal_type,
                'lead_id' => $leadId,
            ]);

            $stage = $fallbackStage;
        }
        

        try {
            DB::beginTransaction();

            $dealNumber = $this->generateDealNumber($lead);
            // Allow quick conversion from Kanban even if lead has incomplete property data.
            // Missing fields can be completed later from Deal edit modal/form.
            $unitNo = $request->unit_no ?? $lead->unit_no ?? null;
            $propertyTypeId = $request->property_type_id ?? $lead->property_type_id ?? null;

            $deal = Deal::create([
                'added_by'=>$user->id,
                'lead_id' => $lead?->id,
                'deal_number' => $dealNumber,
                
                'deal_type' => $request->deal_type,
                'stage_id' => $stage->id,
                
                'source' => $lead->lead_source ?? $lead->source,
                'deal_name' =>$lead->deal_name?? $lead->lead_name,

                // 'deal_total_amount' => $lead->budget,
                'currency' => $lead->currency ?? 'AED',
               
                'unit_no' => $unitNo,
                'property_type_id' => $propertyTypeId,
                'bedrooms' => $lead->bedrooms,
                'created_by' => auth()->id(),
                'responsible_person_id' => $lead->responsible_person_id ?? auth()->id(),
            ]);
            if($request->deal_type !='rental'){
            DealParty::create([
                    'deal_id' => $deal->id,
                    'party_type' => 'buyer',
                    'party_role' => 'primary',
                    'first_name' => $lead->first_name,
                    'last_name' =>$lead->last_name,
                    'date_of_birth' => $lead->date_of_birth,
                    'phone' => $lead->work_phone,
                    'email' => $lead->email,
                    
                    // 'amount' => $lead->budget,
                ]);
            }else
            {
                 DealParty::create([
                    'deal_id' => $deal->id,
                    'party_type' => 'tenant',
                    'party_role' => 'primary',
                    'first_name' => $lead->first_name,
                    'last_name' =>$lead->last_name,
                    'date_of_birth' => $lead->date_of_birth,
                    'phone' => $lead->work_phone,
                    'email' => $lead->email,
                    
                    // 'amount' => $lead->budget,
                ]);
            }
           
            DealHistoryHelper::log(
                $deal->id,
                ['action' => 'created']
            );
            $oldStage=$lead->stage;
             $lead->update([
                //  converted stage
                'stage_id' => 8,
                'last_stage_change_at' => now(),
                  'converted_to_deal_id' =>$deal->id,
                  'converted_at'=>Carbon::now(),
                //  'revert'=>null,
            ]);
            $newStage=$lead->stage;
            
         $changes = [
            'old_stage' => $oldStage->name,
            'new_stage' => $newStage->name
        ];
          LeadHistoryHelper::log(
            $lead->id,
            [
                'action' => 'stage_changed',
                'old_stage' => $oldStage->name,
                'new_stage' => $newStage->name
            ]
        );
        try {
            broadcast(new LeadUpdated($lead, 'stage_changed', null, $changes));
            broadcast(new DealUpdated($deal, 'created'));
        } catch (\Throwable $e) {
            Log::warning('Broadcast failed during lead conversion (Pusher may be unreachable)', [
                'lead_id' => $lead->id,
                'deal_id' => $deal->id,
                'error' => $e->getMessage(),
            ]);
        }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Lead converted to deal successfully',
                'data' => new DealResource($deal->load([
                    'stage',
                    'propertyType',
                    'project',
                    'area',
                    'developer',
                    'parties',
                    'responsiblePerson',
                    'documents'
                ]))
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to convert lead to deal', ['lead_id' => $leadId, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to convert lead to deal',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function store(ConvertLeadRequest $request)
    {
        $user = auth()->user();
        $lead=Lead::find($request->lead_id);
        if($lead){
        if (!$user->hasAnyRole(['super_admin'])) {
            $canAccess = false;
            
            if ($user->hasAnyRole(['manager', 'team_lead', 'admin'])) {
                $subordinatesIds = $user->getAllSubordinatesIds();
                $canAccess = in_array($lead->responsible_person_id, array_merge($subordinatesIds, [$user->id])) 
                        || in_array($lead->added_by, $subordinatesIds);
            } else {
                $canAccess = $lead->responsible_person_id == $user->id 
                        || $lead->added_by == $user->id;
            }
            
            if (!$canAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to convert this lead'
                ], 403);
            }
        }
        }

        if ($lead && $lead->converted_to_deal_id) {
            return response()->json([
                'success' => false,
                'message' => 'Lead already converted to deal',
                'deal_id' => $lead->converted_to_deal_id
            ], 400);
        }

        if (!$request->filled('stage_id')) {
            return response()->json([
                'success' => false,
                'message' => 'Stage ID is required'
            ], 400);
        }

        $stage = Stage::where('id', $request->stage_id)
            ->where('stage_type', 'deal')
            ->where('deal_type', $request->deal_type)
            ->first();

        if (!$stage) {
              $stage = Stage::where('stage_type', 'deal')
            ->where('deal_type', $request->deal_type)
            ->orderBy('order')
            ->first();
        }

        try {
            DB::beginTransaction();

            $dealNumber = $this->generateDealNumber($lead);

            $deal = Deal::create([
                'added_by'=>$user->id,
                'lead_id' => $lead?->id,
                'deal_number' => $dealNumber,
                
                'deal_type' => $request->deal_type,
                'stage_id' => $stage->id,
                
                'source' => $request->source,
                'deal_name' => $request->deal_name,
                'status' => 'draft',
                
                'deal_total_amount' => $request->deal_total_amount,
                'currency' => $request->currency ?? 'AED',
                'deal_commission' => $request->deal_commission,
                'agent_share' => $request->agent_share,
                'company_share' => $request->company_share,
                
                'unit_no' => $request->unit_no,
                'listing_id' => $request->listing_id,
                'property_type_id' => $request->property_type_id,
                'bedrooms' => $request->bedrooms,
                'unit_size' => $request->unit_size,
                'property_link' => $request->property_link,
                'property_reference' => $request->property_reference,
                
                'project_id' => $request->project_id,
                'subcommunity_id' => $request->subcommunity_id,
                'area_id' => $request->area_id,
                'developer_id' => $request->developer_id,
                'developer_name' => $request->developer_name,
                'developer_phone' => $request->developer_phone,
                
                'responsible_person_id' => $request->responsible_person_id ?? $lead->responsible_person_id ??1,
                
                'created_by' => auth()->id(),
                
                'metadata' => [
                    'converted_from_lead' => $lead?->id,
                    'converted_at' => now()->toDateTimeString(),
                    'lead_data' => [
                        'id' => $lead?->id,
                        'name' => $lead?->lead_name,
                        'email' => $lead?->email,
                        'phone' => $lead?->phone
                    ]
                ]
            ]);

            $parties = $this->createDealParties($deal, $request);

            if ($request->hasFile('documents')) {
                $this->uploadDocuments($deal, $request, $parties);
            }
            DealHistoryHelper::log(
                $deal->id,
                ['action' => 'created']
            );
            try {
                broadcast(new DealUpdated($deal, 'created'));
            } catch (\Throwable $e) {
                Log::warning('Broadcast failed during deal create/store (Pusher may be unreachable)', [
                    'deal_id' => $deal->id,
                    'error' => $e->getMessage(),
                ]);
            }


            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Lead converted to deal successfully',
                'data' => new DealResource($deal->load([
                    'stage',
                    'propertyType',
                    'project',
                    'area',
                    'developer',
                    'parties',
                    'responsiblePerson',
                    'documents'
                ]))
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to convert lead to deal (store)', ['lead_id' => $request->lead_id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to convert lead to deal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * رفع وحفظ المستندات باستخدام ImageHelper
     */
    private function uploadDocuments($deal, $request, $parties)
    {
        $documents = $request->input('documents', []);
        $files = $request->file('documents');
        
        // تجميع الملفات حسب الـ index
        $groupedFiles = [];
        if(count($files)>0){
        foreach ($files as $index => $file) {
            $groupedFiles[$index] = $file;
        }
        }
        if(count($documents)>0){
        foreach ($documents as $index => $docData) {
            if (!isset($groupedFiles[$index])) {
                continue;
            }

            $file = $groupedFiles[$index];
            $category = $docData['category']; // buyer, seller, tenant, landlord, property
            $type = $docData['document_type']; // national_id, passport, etc
            
            // تحديد الـ party_id بناءً على الـ category
            $partyId = $this->getPartyIdByCategory($parties, $category);
            
            // تحديد مسار الحفظ
            $storagePath = "deals/{$deal->id}/{$category}";
            
            // التحقق من نوع الملف
            $isImage = in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp']);
            
            if ($isImage) {
                // استخدام ImageHelper لضغط وتحويل الصور
                $imageOptions = [
                    'quality' => 80,
                    'max_width' => 1920,
                    'max_height' => 1080,
                    'watermark' => [
                        'enabled' => false, // يمكن تفعيله إذا أردت
                    ]
                ];
                
                // تحويل الصور إلى WebP
                $result = ImageHelper::compressAndConvertToWebP($file, $storagePath, $imageOptions);
                $filePath = $result['path'];
                $fileSize = $result['compressed_size'] ?? $file->getSize();
                
            } else {
                // للملفات غير الصور (PDF, DOC, etc)
                $path = $file->store($storagePath, 'public');
                $filePath = $path;
                $fileSize = $file->getSize();
            }

            // حفظ في قاعدة البيانات
            DealDocument::create([
                'deal_id' => $deal->id,
                'deal_party_id' => $partyId,
                'document_category' => $category,
                'document_type' => $type,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'mime_type' => $file->getMimeType(),
                'uploaded_by' => auth()->id()
            ]);
        }
        }
    }

    /**
     * الحصول على Party ID حسب الفئة
     */
    private function getPartyIdByCategory($parties, $category)
    {
        // Map category to party_type
        $partyTypeMap = [
            'buyer' => 'buyer',
            'seller' => 'seller',
            'tenant' => 'tenant',
            'landlord' => 'landlord',
            'property' => null // property documents don't belong to a party
        ];

        $partyType = $partyTypeMap[$category] ?? null;
        
        if (!$partyType) {
            return null;
        }

        // Find the first party with this type and primary role
        foreach ($parties as $party) {
            if ($party->party_type === $partyType && $party->party_role === 'primary') {
                return $party->id;
            }
        }

        return null;
    }

    /**
     * إنشاء أطراف الصفقة حسب النوع
     */
    private function createDealParties($deal, $request)
{
    $parties = [];
    
    // ✅ التحقق من وجود listing_id ونوعه
    $hasListingId = $request->filled('listing_id');
    $listing = null;
    $isListingConverted = false;
    $isListingRented = false;
    
    if ($hasListingId) {
        $listing = \App\Models\Listing::find($request->listing_id);
        if ($listing) {
            $isListingConverted = $listing->status === 'converted';
            $isListingRented = $listing->status === 'rented';
        }
    }

    switch ($request->deal_type) {
        
        case 'rental':
            // Client (العميل) - لو موجود
            if ($request->client_name) {
                $nameParts = explode(' ', $request->client_name, 2);
                $parties[] = DealParty::create([
                    'deal_id' => $deal->id,
                    'party_type' => 'client',
                    'party_role' => 'primary',
                    'first_name' => $nameParts[0] ?? '',
                    'last_name' => $nameParts[1] ?? '',
                    'phone' => $request->client_phone,
                    'email' => $request->client_email,
                ]);
            }

            // Tenant (المستأجر) - دائماً مطلوب
            $parties[] = DealParty::create([
                'deal_id' => $deal->id,
                'party_type' => 'tenant',
                'party_role' => 'primary',
                'first_name' => $request->tenant_first_name,
                'last_name' => $request->tenant_last_name,
                'phone' => $request->tenant_phone,
                'email' => $request->tenant_email,
                'nationality' => $request->tenant_nationality,
                'residency_status' => $request->tenant_residency_status,
                'country' => $request->tenant_country,
                'city' => $request->tenant_city,
                'language' => $request->tenant_language,
            ]);

            // ✅ Landlord (المالك) - يتم إنشاؤه فقط إذا لم يكن هناك listing_id من نوع rented
            $shouldCreateLandlord = !($hasListingId && $isListingRented);
            
            if ($shouldCreateLandlord && $request->landlord_first_name) {
                $parties[] = DealParty::create([
                    'deal_id' => $deal->id,
                    'party_type' => 'landlord',
                    'party_role' => 'primary',
                    'first_name' => $request->landlord_first_name,
                    'last_name' => $request->landlord_last_name,
                    'date_of_birth' => $request->landlord_dob,
                    'phone' => $request->landlord_phone,
                    'email' => $request->landlord_email,
                    'nationality' => $request->landlord_nationality,
                    'residency_status' => $request->landlord_residency_status,
                    'country' => $request->landlord_country,
                    'city' => $request->landlord_city,
                    'language' => $request->landlord_language,
                ]);
            }
            break;

        case 'primary':
            // Buyer (المشتري) - دائماً مطلوب
            $parties[] = DealParty::create([
                'deal_id' => $deal->id,
                'party_type' => 'buyer',
                'party_role' => 'primary',
                'first_name' => $request->buyer_first_name,
                'last_name' => $request->buyer_last_name,
                'date_of_birth' => $request->buyer_dob,
                'phone' => $request->buyer_phone,
                'email' => $request->buyer_email,
                'nationality' => $request->buyer_nationality,
                'residency_status' => $request->buyer_residency_status,
                'city' => $request->buyer_city,
                'country' => $request->buyer_country,
                'language' => $request->buyer_language,
                'amount' => $request->amount,
            ]);

            // Secondary Buyer (لو موجود)
            if ($request->filled('secondary_buyer_first_name')) {
                $parties[] = DealParty::create([
                    'deal_id' => $deal->id,
                    'party_type' => 'buyer',
                    'party_role' => 'secondary',
                    'first_name' => $request->secondary_buyer_first_name,
                    'last_name' => $request->secondary_buyer_last_name,
                    'phone' => $request->secondary_buyer_phone,
                    'email' => $request->secondary_buyer_email,
                    'amount' => $request->secondary_buyer_amount,
                ]);
            }
            break;

        case 'secondary':
            // Buyer (المشتري) - دائماً مطلوب
            $parties[] = DealParty::create([
                'deal_id' => $deal->id,
                'party_type' => 'buyer',
                'party_role' => 'primary',
                'first_name' => $request->buyer_first_name,
                'last_name' => $request->buyer_last_name,
                'date_of_birth' => $request->buyer_dob,
                'phone' => $request->buyer_phone,
                'email' => $request->buyer_email,
                'nationality' => $request->buyer_nationality,
                'residency_status' => $request->buyer_residency_status,
                'city' => $request->buyer_city,
                'country' => $request->buyer_country,
                'language' => $request->buyer_language,
                'amount' => $request->amount,
            ]);

            // ✅ Seller (البائع) - يتم إنشاؤه فقط إذا لم يكن هناك listing_id من نوع converted
            $shouldCreateSeller = !($hasListingId && $isListingConverted);
            
            if ($shouldCreateSeller && $request->seller_first_name) {
                $parties[] = DealParty::create([
                    'deal_id' => $deal->id,
                    'party_type' => 'seller',
                    'party_role' => 'primary',
                    'first_name' => $request->seller_first_name,
                    'last_name' => $request->seller_last_name,
                    'date_of_birth' => $request->seller_dob,
                    'phone' => $request->seller_phone,
                    'email' => $request->seller_email,
                    'nationality' => $request->seller_nationality,
                    'residency_status' => $request->seller_residency_status,
                    'city' => $request->seller_city,
                    'country' => $request->seller_country,
                    'language' => $request->seller_language,
                ]);
            }

            // Secondary Buyer (لو موجود)
            if ($request->filled('secondary_buyer_first_name')) {
                $parties[] = DealParty::create([
                    'deal_id' => $deal->id,
                    'party_type' => 'buyer',
                    'party_role' => 'secondary',
                    'first_name' => $request->secondary_buyer_first_name,
                    'last_name' => $request->secondary_buyer_last_name,
                    'phone' => $request->secondary_buyer_phone,
                    'email' => $request->secondary_buyer_email,
                    'amount' => $request->secondary_buyer_amount,
                ]);
            }
            break;
    }

    return $parties;
}

    /**
     * توليد رقم الصفقة
     */
    private function generateDealNumber($lead = null)
    {
        // لو الـ lead عنده رقم، استخدمه
        if ($lead && $lead->lead_number) {
            return $lead->lead_number;
        }

        // وإلا، أنشئ رقم جديد
        $prefix = 'DL';
        $year = date('Y');
        $month = date('m');
        
        $lastDeal = Deal::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();

        if ($lastDeal && preg_match('/-(\d+)$/', $lastDeal->deal_number, $matches)) {
            $sequence = intval($matches[1]) + 1;
        } else {
            $sequence = 1;
        }

        return sprintf('%s-%s%s-%04d', $prefix, $year, $month, $sequence);
    }

    /**
     * [Optionally] التحقق من إمكانية تحويل الـ Lead
     * GET /api/leads/{lead}/can-convert
     */
    public function canConvert(Lead $lead)
    {
        // التحقق من الصلاحية
        $user = auth()->user();
        
        if (!$user->hasAnyRole(['super_admin'])) {
            $canAccess = false;
            
            if ($user->hasAnyRole(['manager', 'team_lead', 'admin'])) {
                $subordinatesIds = $user->getAllSubordinatesIds();
                $canAccess = in_array($lead->responsible_person_id, array_merge($subordinatesIds, [$user->id])) 
                        || in_array($lead->added_by, $subordinatesIds);
            } else {
                $canAccess = $lead->responsible_person_id == $user->id 
                        || $lead->added_by == $user->id;
            }
            
            if (!$canAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'can_convert' => is_null($lead->converted_to_deal_id),
                'is_converted' => !is_null($lead->converted_to_deal_id),
                'converted_to_deal_id' => $lead->converted_to_deal_id,
                'converted_at' => $lead->converted_at,
                'available_deal_types' => ['primary', 'secondary', 'rental']
            ]
        ]);
    }
}