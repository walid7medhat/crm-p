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
use App\Models\DealProperty;
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

       if (!$user->hasAnyRole(['super_admin']) && $user->id != 30) {
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

            $deal = Deal::create([
                'added_by' => $user->id,
                'lead_id' => $lead->id,
                'deal_number' => $dealNumber,
                'deal_type' => $request->deal_type,
                'stage_id' => $stage->id,
                'source' => $lead->lead_source ?? $lead->source,
                'deal_name' => $lead->deal_name ?? $lead->lead_name,
                'currency' => $lead->currency ?? 'AED',
                'created_by' => auth()->id(),
                'responsible_person_id' => $lead->responsible_person_id ?? auth()->id(),
            ]);

            // ✅ Create properties from lead data
            // $this->createDealPropertiesFromLead($deal, $lead);

            // Create party based on deal type
            if ($request->deal_type != 'rental') {
                DealParty::create([
                    'deal_id' => $deal->id,
                    'party_type' => 'buyer',
                    'party_role' => 'primary',
                    'first_name' => $lead->first_name,
                    'last_name' => $lead->last_name,
                    'date_of_birth' => $lead->date_of_birth,
                    'phone' => $lead->work_phone,
                    'email' => $lead->email,
                ]);
            } else {
                DealParty::create([
                    'deal_id' => $deal->id,
                    'party_type' => 'tenant',
                    'party_role' => 'primary',
                    'first_name' => $lead->first_name,
                    'last_name' => $lead->last_name,
                    'date_of_birth' => $lead->date_of_birth,
                    'phone' => $lead->work_phone,
                    'email' => $lead->email,
                ]);
            }
           
            DealHistoryHelper::log($deal->id, ['action' => 'created']);
            
            $oldStage = $lead->stage;
            $lead->update([
                'stage_id' => 8,
                'last_stage_change_at' => now(),
                'converted_to_deal_id' => $deal->id,
                'converted_at' => Carbon::now(),
            ]);
            $newStage = $lead->stage;
            
            $changes = [
                'old_stage' => $oldStage->name,
                'new_stage' => $newStage->name
            ];
            
            LeadHistoryHelper::log($lead->id, [
                'action' => 'stage_changed',
                'old_stage' => $oldStage->name,
                'new_stage' => $newStage->name
            ]);
            
            try {
                broadcast(new LeadUpdated($lead, 'stage_changed', null, $changes));
                broadcast(new DealUpdated($deal, 'created'));
            } catch (\Throwable $e) {
                Log::warning('Broadcast failed during lead conversion', [
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
                    'parties',
                    'responsiblePerson',
                    'documents',
                    'properties'
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
        $lead = Lead::find($request->lead_id);
        
        if ($lead) {
            if (!$user->hasAnyRole(['super_admin']) && $user->id != 30) {
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
                'added_by' => $user->id,
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
                'listing_id' => $request->listing_id,
                'responsible_person_id' => $request->responsible_person_id ?? $lead->responsible_person_id ?? 1,
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

            // ✅ Create multi properties
            $this->createDealProperties($deal, $request);

            // ✅ Create parties
            $parties = $this->createDealParties($deal, $request);

            // ✅ Upload documents
            if ($request->hasFile('documents')) {
                $this->uploadDocuments($deal, $request, $parties);
            }
            
            DealHistoryHelper::log($deal->id, ['action' => 'created']);
            
            try {
                broadcast(new DealUpdated($deal, 'created'));
            } catch (\Throwable $e) {
                Log::warning('Broadcast failed during deal create/store', [
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
                    'parties',
                    'responsiblePerson',
                    'documents',
                    'properties'
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
     * Create properties from lead data (for simple conversion)
     */
    private function createDealPropertiesFromLead(Deal $deal, Lead $lead)
    {
        $propertyData = [];
        
        if ($lead->unit_no) $propertyData['unit_no'] = $lead->unit_no;
        if ($lead->property_type_id) $propertyData['property_type_id'] = $lead->property_type_id;
        if ($lead->bedrooms) $propertyData['bedrooms'] = $lead->bedrooms;
        if ($lead->area_id) $propertyData['area_id'] = $lead->area_id;
        
        if (!empty($propertyData)) {
            $deal->properties()->create(array_merge($propertyData, ['sort_order' => 0]));
        }
    }


/**
 * Create multi properties for deal
 */
private function createDealProperties(Deal $deal, $request)
{
    // ========== MULTI PROPERTIES MODE ==========
    if ($request->has('properties') && is_array($request->properties) && count($request->properties) > 0) {
        foreach ($request->properties as $index => $propertyData) {
            // ✅ Handle EOI Documents
            $eoiDocumentPaths = [];
            if (isset($propertyData['eoi_documents']) && is_array($propertyData['eoi_documents'])) {
                foreach ($propertyData['eoi_documents'] as $file) {
                    if ($file instanceof \Illuminate\Http\UploadedFile) {
                        $path = $file->store("deals/{$deal->id}/properties/eoi_documents", 'public');
                        $eoiDocumentPaths[] = [
                            'original_name' => $file->getClientOriginalName(),
                            'path' => $path,
                            'mime_type' => $file->getMimeType(),
                            'size' => $file->getSize(),
                        ];
                    }
                }
            }
            
            // ✅ Handle Booking Documents
            $bookingDocumentPaths = [];
            if (isset($propertyData['booking_documents']) && is_array($propertyData['booking_documents'])) {
                foreach ($propertyData['booking_documents'] as $file) {
                    if ($file instanceof \Illuminate\Http\UploadedFile) {
                        $path = $file->store("deals/{$deal->id}/properties/booking_documents", 'public');
                        $bookingDocumentPaths[] = [
                            'original_name' => $file->getClientOriginalName(),
                            'path' => $path,
                            'mime_type' => $file->getMimeType(),
                            'size' => $file->getSize(),
                        ];
                    }
                }
            }
            
            // Handle payment_proof files
            $paymentProofPaths = [];
            if (isset($propertyData['payment_proof']) && is_array($propertyData['payment_proof'])) {
                foreach ($propertyData['payment_proof'] as $file) {
                    if ($file instanceof \Illuminate\Http\UploadedFile) {
                        $path = $file->store("deals/{$deal->id}/properties/payment_proof", 'public');
                        $paymentProofPaths[] = [
                            'original_name' => $file->getClientOriginalName(),
                            'path' => $path,
                            'mime_type' => $file->getMimeType(),
                            'size' => $file->getSize(),
                        ];
                    }
                }
            }
            
            // Handle spa_document files
            $spaDocumentPaths = [];
            if (isset($propertyData['spa_document']) && is_array($propertyData['spa_document'])) {
                foreach ($propertyData['spa_document'] as $file) {
                    if ($file instanceof \Illuminate\Http\UploadedFile) {
                        $path = $file->store("deals/{$deal->id}/properties/spa_document", 'public');
                        $spaDocumentPaths[] = [
                            'original_name' => $file->getClientOriginalName(),
                            'path' => $path,
                            'mime_type' => $file->getMimeType(),
                            'size' => $file->getSize(),
                        ];
                    }
                }
            }
            
            $property = $deal->properties()->create([
                'sort_order' => $index,
                'unit_no' => $propertyData['unit_no'] ?? null,
                'property_type_id' => $propertyData['property_type_id'] ?? null,
                'bedrooms' => $propertyData['bedrooms'] ?? null,
                'unit_size' => $propertyData['unit_size'] ?? null,
                'area_id' => $propertyData['area_id'] ?? null,
                'project_id' => $propertyData['project_id'] ?? null,
                'developer_id' => $propertyData['developer_id'] ?? null,
                'developer_name' => $propertyData['developer_name'] ?? null,
                'developer_phone' => $propertyData['developer_phone'] ?? null,
                'budget_from' => $propertyData['budget_from'] ?? null,
                'budget_to' => $propertyData['budget_to'] ?? null,
                'purchase_price' => $propertyData['purchase_price'] ?? null,
                'rental_price' => $propertyData['rental_price'] ?? null,
                'payment_proof' => !empty($paymentProofPaths) ? json_encode($paymentProofPaths) : null,
                'spa_document' => !empty($spaDocumentPaths) ? json_encode($spaDocumentPaths) : null,
                // ✅ إضافة الحقول الجديدة
                'eoi_documents' => !empty($eoiDocumentPaths) ? json_encode($eoiDocumentPaths) : null,
                'booking_documents' => !empty($bookingDocumentPaths) ? json_encode($bookingDocumentPaths) : null,
                'contract_document' => $propertyData['contract_document'] ?? null,
                'ejari_document' => $propertyData['ejari_document'] ?? null,
                'commission' => $propertyData['commission'] ?? null,
            ]);
        }
    } 
    // ========== SINGLE PROPERTY MODE ==========
    else {
        $propertyData = [];
        
        // Text fields
        if ($request->filled('unit_no')) $propertyData['unit_no'] = $request->unit_no;
        if ($request->filled('property_type_id')) $propertyData['property_type_id'] = $request->property_type_id;
        if ($request->filled('bedrooms')) $propertyData['bedrooms'] = $request->bedrooms;
        if ($request->filled('unit_size')) $propertyData['unit_size'] = $request->unit_size;
        if ($request->filled('area_id')) $propertyData['area_id'] = $request->area_id;
        if ($request->filled('project_id')) $propertyData['project_id'] = $request->project_id;
        if ($request->filled('developer_id')) $propertyData['developer_id'] = $request->developer_id;
        if ($request->filled('developer_name')) $propertyData['developer_name'] = $request->developer_name;
        if ($request->filled('developer_phone')) $propertyData['developer_phone'] = $request->developer_phone;
        if ($request->filled('budget_from')) $propertyData['budget_from'] = $request->budget_from;
        if ($request->filled('budget_to')) $propertyData['budget_to'] = $request->budget_to;
        if ($request->filled('purchase_price')) $propertyData['purchase_price'] = $request->purchase_price;
        if ($request->filled('rental_price')) $propertyData['rental_price'] = $request->rental_price;
        if ($request->filled('commission')) $propertyData['commission'] = $request->commission;
        
        // ✅ Handle EOI Documents (direct request)
        $eoiDocumentPaths = [];
        if ($request->hasFile('eoi_documents')) {
            $files = $request->file('eoi_documents');
            if (is_array($files)) {
                foreach ($files as $file) {
                    if ($file instanceof \Illuminate\Http\UploadedFile) {
                        $path = $file->store("deals/{$deal->id}/properties/eoi_documents", 'public');
                        $eoiDocumentPaths[] = [
                            'original_name' => $file->getClientOriginalName(),
                            'path' => $path,
                            'mime_type' => $file->getMimeType(),
                            'size' => $file->getSize(),
                        ];
                    }
                }
            } elseif ($files instanceof \Illuminate\Http\UploadedFile) {
                $path = $files->store("deals/{$deal->id}/properties/eoi_documents", 'public');
                $eoiDocumentPaths[] = [
                    'original_name' => $files->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $files->getMimeType(),
                    'size' => $files->getSize(),
                ];
            }
        }
        
        // ✅ Handle Booking Documents (direct request)
        $bookingDocumentPaths = [];
        if ($request->hasFile('booking_documents')) {
            $files = $request->file('booking_documents');
            if (is_array($files)) {
                foreach ($files as $file) {
                    if ($file instanceof \Illuminate\Http\UploadedFile) {
                        $path = $file->store("deals/{$deal->id}/properties/booking_documents", 'public');
                        $bookingDocumentPaths[] = [
                            'original_name' => $file->getClientOriginalName(),
                            'path' => $path,
                            'mime_type' => $file->getMimeType(),
                            'size' => $file->getSize(),
                        ];
                    }
                }
            } elseif ($files instanceof \Illuminate\Http\UploadedFile) {
                $path = $files->store("deals/{$deal->id}/properties/booking_documents", 'public');
                $bookingDocumentPaths[] = [
                    'original_name' => $files->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $files->getMimeType(),
                    'size' => $files->getSize(),
                ];
            }
        }
        
        // Handle payment_proof files from direct request
        $paymentProofPaths = [];
        if ($request->hasFile('payment_proof')) {
            $files = $request->file('payment_proof');
            if (is_array($files)) {
                foreach ($files as $file) {
                    if ($file instanceof \Illuminate\Http\UploadedFile) {
                        $path = $file->store("deals/{$deal->id}/properties/payment_proof", 'public');
                        $paymentProofPaths[] = [
                            'original_name' => $file->getClientOriginalName(),
                            'path' => $path,
                            'mime_type' => $file->getMimeType(),
                            'size' => $file->getSize(),
                        ];
                    }
                }
            } elseif ($files instanceof \Illuminate\Http\UploadedFile) {
                $path = $files->store("deals/{$deal->id}/properties/payment_proof", 'public');
                $paymentProofPaths[] = [
                    'original_name' => $files->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $files->getMimeType(),
                    'size' => $files->getSize(),
                ];
            }
        }
        
        // Handle spa_document files from direct request
        $spaDocumentPaths = [];
        if ($request->hasFile('spa_document')) {
            $files = $request->file('spa_document');
            if (is_array($files)) {
                foreach ($files as $file) {
                    if ($file instanceof \Illuminate\Http\UploadedFile) {
                        $path = $file->store("deals/{$deal->id}/properties/spa_document", 'public');
                        $spaDocumentPaths[] = [
                            'original_name' => $file->getClientOriginalName(),
                            'path' => $path,
                            'mime_type' => $file->getMimeType(),
                            'size' => $file->getSize(),
                        ];
                    }
                }
            } elseif ($files instanceof \Illuminate\Http\UploadedFile) {
                $path = $files->store("deals/{$deal->id}/properties/spa_document", 'public');
                $spaDocumentPaths[] = [
                    'original_name' => $files->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $files->getMimeType(),
                    'size' => $files->getSize(),
                ];
            }
        }
        
        // Add files to property data
        if (!empty($eoiDocumentPaths)) {
            $propertyData['eoi_documents'] = json_encode($eoiDocumentPaths);
        }
        if (!empty($bookingDocumentPaths)) {
            $propertyData['booking_documents'] = json_encode($bookingDocumentPaths);
        }
        if (!empty($paymentProofPaths)) {
            $propertyData['payment_proof'] = json_encode($paymentProofPaths);
        }
        if (!empty($spaDocumentPaths)) {
            $propertyData['spa_document'] = json_encode($spaDocumentPaths);
        }
        
        if (!empty($propertyData)) {
            $deal->properties()->create(array_merge($propertyData, ['sort_order' => 0]));
        }
    }
}

    /**
     * Upload documents
     */
    private function uploadDocuments($deal, $request, $parties)
    {
        $documents = $request->input('documents', []);
        $files = $request->file('documents');
        
        $groupedFiles = [];
        if (count($files) > 0) {
            foreach ($files as $index => $file) {
                $groupedFiles[$index] = $file;
            }
        }
        
        if (count($documents) > 0) {
            foreach ($documents as $index => $docData) {
                if (!isset($groupedFiles[$index])) {
                    continue;
                }

                $file = $groupedFiles[$index];
                $category = $docData['category'];
                $type = $docData['document_type'];
                $partyId = $this->getPartyIdByCategory($parties, $category);
                $storagePath = "deals/{$deal->id}/{$category}";
                
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
     * Get party ID by category
     */
    private function getPartyIdByCategory($parties, $category)
    {
        $partyTypeMap = [
            'buyer' => 'buyer',
            'seller' => 'seller',
            'tenant' => 'tenant',
            'landlord' => 'landlord',
            'property' => null
        ];

        $partyType = $partyTypeMap[$category] ?? null;
        
        if (!$partyType) {
            return null;
        }

        foreach ($parties as $party) {
            if ($party->party_type === $partyType && $party->party_role === 'primary') {
                return $party->id;
            }
        }

        return null;
    }

    /**
     * Create deal parties
     */
    private function createDealParties($deal, $request)
    {
        $parties = [];
        
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
     * Generate deal number
     */
    private function generateDealNumber($lead = null)
    {
        if ($lead && $lead->lead_number) {
            return $lead->lead_number;
        }

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
     * Check if lead can be converted
     */
    public function canConvert(Lead $lead)
    {
        $user = auth()->user();
        
        if (!$user->hasAnyRole(['super_admin']) && $user->id != 30) {
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