<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\Stage;
use Illuminate\Support\Facades\Log;
class DealStageValidator
{
    /** Map UI/requirement field names to model attributes */
    protected array $partyFieldMap = [];

    protected array $stageRequirements = [];

    public function __construct()
    {
        $this->partyFieldMap = config('deal_stage_requirements.party_field_map', [
            'dob' => 'date_of_birth',
        ]);

        $this->stageRequirements = config('deal_stage_requirements.requirements', []);
    }

   public function validate(Deal $deal, int $targetStageId, string $dealType): array
{
    $currentStage = Stage::find($deal->stage_id);
    $targetStage = Stage::find($targetStageId);
    
    Log::info('Starting validation', [
        'deal_id' => $deal->id,
        'current_stage' => $currentStage?->name,
        'target_stage' => $targetStage?->name,
        'deal_type' => $dealType
    ]);
    
    if (!$currentStage || !$targetStage) {
        return ['valid' => true];
    }

    // إذا كان الهدف أقل أو يساوي الحالي، يسمح
    if ($targetStage->order <= $currentStage->order) {
        return ['valid' => true];
    }

    $missingFields = [];
    $missingByStage = [];
    $allStages = Stage::where('deal_type', $dealType)
        ->where('stage_type', 'deal')
        ->orderBy('order')
        ->get();

    $startOrder = (int) $currentStage->order + 1;
    $endOrder = (int) $targetStage->order;

    $parties = [];
    foreach ($deal->parties as $party) {
        $parties[$party->party_type] = $party;
    }

    Log::info('Loaded parties', ['count' => count($parties), 'types' => array_keys($parties)]);

    // لو الهدف هو lost_reason (order = 8 أو 6 حسب النوع)، نجيب متطلبات المرحلة المستهدفة بس
    $isLostStage = false;
    if ($dealType === 'primary' && $targetStage->order == 6) {
        $isLostStage = true;
    } elseif (($dealType === 'secondary' || $dealType === 'rental') && $targetStage->order == 8) {
        $isLostStage = true;
    }
    
    if ($isLostStage) {
        // نجيب متطلبات المرحلة المستهدفة بس
        $requirements = $this->stageRequirements[$dealType][$targetStage->order] ?? null;
        if ($requirements) {
            $stageMissing = [];

            // التحقق من الحقول الأساسية
            foreach ($requirements['fields'] ?? [] as $field) {
                $value = $deal->$field ?? null;
                if (empty($value)) {
                    $stageMissing[] = $field;
                    $missingFields[] = $field;
                }
            }

            if (!empty($stageMissing)) {
                $missingByStage[] = [
                    'stage_order' => (int) $targetStage->order,
                    'stage_id' => $targetStage->id,
                    'stage_name' => $targetStage->name,
                    'missing_fields' => array_values(array_unique($stageMissing)),
                ];
            }
        }
    } else {
        // لو مش lost_reason، نجيب كل المراحل زي الأول
        for ($order = $startOrder; $order <= $endOrder; $order++) {
            $stage = $allStages->firstWhere('order', $order);
            if (!$stage) continue;

            $requirements = $this->stageRequirements[$dealType][$order] ?? null;
            if (!$requirements) continue;

            $stageMissing = [];

            // التحقق من الحقول الأساسية
            foreach ($requirements['fields'] ?? [] as $field) {
                $value = $deal->$field ?? null;
                if (empty($value)) {
                    $stageMissing[] = $field;
                    $missingFields[] = $field;
                }
            }

            // التحقق من الأطراف (Parties)
            foreach ($requirements['parties'] ?? [] as $partyType => $fields) {
                $party = $parties[$partyType] ?? null;
                
                if (!$party) {
                    // الطرف غير موجود - نضيف كل الحقول المطلوبة
                    $stageMissing[] = "{$partyType}_party";
                    $missingFields[] = "{$partyType}_party";
                    
                    // نضيف كل الحقول الفردية المطلوبة
                    foreach ($fields as $field) {
                        $stageMissing[] = "{$partyType}_{$field}";
                        $missingFields[] = "{$partyType}_{$field}";
                    }
                    continue;
                }
                
                // الطرف موجود - نتحقق من كل حقل
                foreach ($fields as $field) {
                    $modelField = $this->partyFieldMap[$field] ?? $field;
                    $value = $party->$modelField ?? null;
                    if (empty($value)) {
                        $stageMissing[] = "{$partyType}_{$field}";
                        $missingFields[] = "{$partyType}_{$field}";
                    }
                }
            }

          // Documents
        foreach ($requirements['documents'] ?? [] as $partyType => $docs) {
            $party = $parties[$partyType] ?? null;
            
            // الحصول على حالة الإقامة والجنسية للطرف (إذا كان موجود)
            $residencyStatus = null;
            $nationality = null;
            
            if ($party) {
                // محاولة الحصول على حالة الإقامة من الـ party
                $residencyStatus = $party->residency_status ?? null;
                $nationality = $party->nationality ?? null;
            } else {
                // إذا لم يكن الطرف موجوداً، نحاول الحصول من الـ deal
                $residencyStatus = $deal->{"{$partyType}_residency_status"} ?? null;
                $nationality = $deal->{"{$partyType}_nationality"} ?? null;
            }
            
            // الحصول على المستندات المطلوبة حسب حالة الإقامة
            $requiredDocs = $this->getRequiredDocumentsByResidency($party, $residencyStatus, $nationality);
            
            foreach ($docs as $docType) {
                // فقط تحقق من المستند إذا كان مطلوباً حسب حالة الإقامة
                if (!in_array($docType, $requiredDocs)) {
                    Log::info('Skipping document not required for residency', [
                        'partyType' => $partyType,
                        'docType' => $docType,
                        'residencyStatus' => $residencyStatus,
                        'nationality' => $nationality,
                        'requiredDocs' => $requiredDocs
                    ]);
                    continue;
                }
                
                $hasDoc = false;
                
                // لو فيه party، نتأكد من وجود المستند
                if ($party) {
                    $hasDoc = $deal->documents()
                        ->where('deal_party_id', $party->id)
                        ->where('document_type', $docType)
                        ->exists();
                }
                
                // لو المستند مش موجود (حتى لو party مش موجود، نعتبره ناقص)
                if (!$hasDoc) {
                    $key = "{$partyType}_document_{$docType}";
                    $stageMissing[] = $key;
                    $missingFields[] = $key;
                    
                    Log::info('Document missing', [
                        'partyType' => $partyType,
                        'docType' => $docType,
                        'party_exists' => $party ? 'yes' : 'no',
                        'residencyStatus' => $residencyStatus,
                        'key' => $key
                    ]);
                }
            }
        }

            if (!empty($stageMissing)) {
                $missingByStage[] = [
                    'stage_order' => (int) $stage->order,
                    'stage_id' => $stage->id,
                    'stage_name' => $stage->name,
                    'missing_fields' => array_values(array_unique($stageMissing)),
                ];
            }
        }
    }

    $missingFields = array_values(array_unique($missingFields));
    $result = [
        'valid' => empty($missingFields),
        'missing_fields' => $missingFields,
        'missing_by_stage' => $missingByStage,
    ];

    Log::info('Validation result', ['valid' => $result['valid'], 'missing_count' => count($missingFields), 'stages_with_missing' => count($missingByStage)]);

    return $result;
}

public function getRequiredFieldsForStage(Deal $deal, int $targetStageId, string $dealType): array
{
    $stage = Stage::find($targetStageId);
    if (!$stage) {
        Log::warning('Stage not found', ['stage_id' => $targetStageId]);
        return [];
    }
    
    $stageOrder = (int) $stage->order;
    
    Log::info('Getting required fields', [
        'stage_id' => $targetStageId,
        'stage_order' => $stageOrder,
        'deal_type' => $dealType
    ]);
    
    $requirements = $this->stageRequirements[$dealType][$stageOrder] ?? null;
    
    Log::info('Requirements found', [
        'has_requirements' => !is_null($requirements),
        'requirements' => $requirements
    ]);
    
    if (!$requirements) return [];
    
    // تحميل الأطراف
    $deal->loadMissing(['parties']);
    $parties = [];
    foreach ($deal->parties as $party) {
        $parties[$party->party_type] = $party;
    }
    
    $requiredFields = [];
    
    foreach ($requirements['fields'] ?? [] as $field) {
        $requiredFields[] = $field;
    }
    
    foreach ($requirements['parties'] ?? [] as $partyType => $fields) {
        foreach ($fields as $field) {
            $requiredFields[] = "{$partyType}_{$field}";
        }
    }
    
    foreach ($requirements['documents'] ?? [] as $partyType => $docs) {
        $party = $parties[$partyType] ?? null;
        
        $residencyStatus = null;
        $nationality = null;
        
        if ($party) {
            $residencyStatus = $party->residency_status ?? null;
            $nationality = $party->nationality ?? null;
        } else {
            $residencyStatus = $deal->{"{$partyType}_residency_status"} ?? null;
            $nationality = $deal->{"{$partyType}_nationality"} ?? null;
        }
        
        // الحصول على المستندات المطلوبة حسب حالة الإقامة
        $requiredDocs = $this->getRequiredDocumentsByResidency($party, $residencyStatus, $nationality);
        
        foreach ($docs as $docType) {
            // فقط أضف المستند إذا كان مطلوباً حسب حالة الإقامة
            if (in_array($docType, $requiredDocs)) {
                $requiredFields[] = "{$partyType}_document_{$docType}";
            }
        }
    }
    
    return $requiredFields;
}

      /**
     * Group missing field keys into UI sections with labels and types for the modal.
     */
    public function getMissingFieldsGroupedForUI(array $missingFields): array
    {
        $fieldMeta = $this->getFieldMeta();
        $sectionOrder = [
            'Buyer Details',
            'Seller Details',
            'Tenant Details',
            'Landlord Details',
            'Property Details',
            'Upload Buyer Documents',
            'Upload Seller Documents',
            'Upload Tenant Documents',
            'Upload Landlord Documents',
            'Deal Financials',
            'Other',
        ];
        
        $bySection = [];
        
        foreach ($missingFields as $key) {
            if (str_ends_with($key, '_party')) {
                continue;
            }
            // المستندات
            if (str_contains($key, '_document_')) {
                $parts = explode('_document_', $key, 2);
                $partyType = $parts[0] ?? 'buyer';
                $docType = $parts[1] ?? $key;
                $section = 'Upload ' . ucfirst($partyType) . ' Documents';
                
                $bySection[$section][] = [
                    'key' => $key,
                    'label' => ucfirst($partyType) . ' ' . ucfirst(str_replace('_', ' ', $docType)),
                    'type' => 'file'
                ];
            } 
            // الحقول العادية
            else {
                $meta = $fieldMeta[$key] ?? [
                    'section' => 'Other', 
                    'label' => $this->humanizeFieldKey($key), 
                    'type' => 'text'
                ];
                
                $section = $meta['section'];
                
                if (!isset($bySection[$section])) {
                    $bySection[$section] = [];
                }
                
                $bySection[$section][] = [
                    'key' => $key,
                    'label' => $meta['label'],
                    'type' => $meta['type'],
                ];
            }
        }
        
        // ترتيب الأقسام حسب $sectionOrder
        $sections = [];
        foreach ($sectionOrder as $title) {
            if (!empty($bySection[$title])) {
                $sections[] = [
                    'title' => $title, 
                    'fields' => $bySection[$title]
                ];
            }
        }
        
        // إضافة أي أقسام أخرى تحت 'Other'
        if (!empty($bySection['Other'])) {
            $sections[] = [
                'title' => 'Other', 
                'fields' => $bySection['Other']
            ];
        }
        
        return ['sections' => $sections];
    }

   
   /**
     * Group missing fields by stage for UI
     */
    public function getMissingFieldsGroupedByStageForUI(array $missingByStage): array
    {
        $fieldMeta = $this->getFieldMeta();
        $sectionOrder = [
            'Property Details',
            'Buyer Details',
            'Seller Details',
            'Tenant Details',
            'Landlord Details',
            'Upload Buyer Documents',
            'Upload Seller Documents',
            'Upload Tenant Documents',
            'Upload Landlord Documents',
            'Deal Financials',
            'Other',
        ];
        
        $stagesForUi = [];
        
        foreach ($missingByStage as $stageBlock) {
            $stageOrder = $stageBlock['stage_order'] ?? 0;
            $stageId = $stageBlock['stage_id'] ?? 0;
            $stageName = $stageBlock['stage_name'] ?? 'Stage ' . $stageOrder;
            $missing = $stageBlock['missing_fields'] ?? [];
            
            $bySection = [];
            
            foreach ($missing as $key) {
                // المستندات
                if (str_contains($key, '_document_')) {
                    $parts = explode('_document_', $key, 2);
                    $partyType = $parts[0] ?? 'buyer';
                    $docType = $parts[1] ?? $key;
                    $section = 'Upload ' . ucfirst($partyType) . ' Documents';
                    
                    $bySection[$section][] = [
                        'key' => $key,
                        'label' => ucfirst($partyType) . ' ' . ucfirst(str_replace('_', ' ', $docType)),
                        'type' => 'file'
                    ];
                } 
                // الحقول العادية
                else {
                    $meta = $fieldMeta[$key] ?? [
                        'section' => 'Other', 
                        'label' => $this->humanizeFieldKey($key), 
                        'type' => 'text'
                    ];
                    
                    $section = $meta['section'];
                    
                    if (!isset($bySection[$section])) {
                        $bySection[$section] = [];
                    }
                    
                    $bySection[$section][] = [
                        'key' => $key,
                        'label' => $meta['label'],
                        'type' => $meta['type'],
                    ];
                }
            }
            
            // ترتيب الأقسام
            $sections = [];
            foreach ($sectionOrder as $title) {
                if (!empty($bySection[$title])) {
                    $sections[] = [
                        'title' => $title, 
                        'fields' => $bySection[$title]
                    ];
                }
            }
            
            if (!empty($bySection['Other'])) {
                $sections[] = [
                    'title' => 'Other', 
                    'fields' => $bySection['Other']
                ];
            }
            
            $stagesForUi[] = [
                'stage_order' => $stageOrder,
                'stage_id' => $stageId,
                'stage_name' => $stageName,
                'sections' => $sections,
            ];
        }
        
        return ['stages' => $stagesForUi];
    }
    protected function humanizeFieldKey(string $key): string
    {
        $key = str_replace('_', ' ', $key);
        return ucwords($key);
    }
    
     protected function getFieldMeta(): array
    {
        $meta = [
            // Property Details
            'source' => ['section' => 'Property Details', 'label' => 'Source', 'type' => 'text'],
            'deal_name' => ['section' => 'Property Details', 'label' => 'Deal Name', 'type' => 'text'],
            'unit_no' => ['section' => 'Property Details', 'label' => 'Unit No', 'type' => 'text'],
            'property_type_id' => ['section' => 'Property Details', 'label' => 'Property Type', 'type' => 'select'],
            // 'subcommunity_id' => ['section' => 'Property Details', 'label' => 'Sub Community', 'type' => 'select'],
            'area_id' => ['section' => 'Property Details', 'label' => 'Property Location', 'type' => 'select'],
            // 'project_id' => ['section' => 'Property Details', 'label' => 'Project', 'type' => 'select'],
            // 'developer_id' => ['section' => 'Property Details', 'label' => 'Developer', 'type' => 'select'],
            'developer_name' => ['section' => 'Property Details', 'label' => 'Developer sales person name', 'type' => 'select'],
            'developer_phone' => ['section' => 'Property Details', 'label' => 'Developer sales person phone', 'type' => 'select'],
            'responsible_person_id' => ['section' => 'Property Details', 'label' => 'Responsible Person', 'type' => 'select'],
            'bedrooms' => ['section' => 'Property Details', 'label' => 'Bedrooms', 'type' => 'select'],
            'unit_size' => ['section' => 'Property Details', 'label' => 'Unit Size', 'type' => 'text'],
            'lost_reason'=>['section' => 'Property Details', 'label' => 'Lost Reason', 'type' => 'text'],
            // Deal Financials
            'deal_total_amount' => ['section' => 'Deal Financials', 'label' => 'Deal Total Amount', 'type' => 'number'],
            'deal_commission' => ['section' => 'Deal Financials', 'label' => 'Deal Total Commission %', 'type' => 'number'],
            'agent_share' => ['section' => 'Deal Financials', 'label' => 'Agent Share %', 'type' => 'number'],
            'company_share' => ['section' => 'Deal Financials', 'label' => 'Company Share %', 'type' => 'number'],
            'currency' => ['section' => 'Deal Financials', 'label' => 'Currency', 'type' => 'select'],
            
            // Buyer Details
            'buyer_first_name' => ['section' => 'Buyer Details', 'label' => 'Buyer First Name', 'type' => 'text'],
            'buyer_last_name' => ['section' => 'Buyer Details', 'label' => 'Buyer Last Name', 'type' => 'text'],
            'buyer_phone' => ['section' => 'Buyer Details', 'label' => 'Buyer Phone Number', 'type' => 'text'],
            'buyer_email' => ['section' => 'Buyer Details', 'label' => 'Buyer Email', 'type' => 'text'],
            'buyer_nationality' => ['section' => 'Buyer Details', 'label' => 'Buyer Nationality', 'type' => 'select'],
            'buyer_dob' => ['section' => 'Buyer Details', 'label' => 'Buyer Date Of Birth', 'type' => 'date'],
            'buyer_residency_status' => ['section' => 'Buyer Details', 'label' => 'Buyer Residency Status', 'type' => 'select'],
            'buyer_city' => ['section' => 'Buyer Details', 'label' => 'Buyer City Of Residence', 'type' => 'text'],
            'buyer_country' => ['section' => 'Buyer Details', 'label' => 'Buyer Country Of Residence', 'type' => 'select'],
            'buyer_language' => ['section' => 'Buyer Details', 'label' => 'Buyer Language', 'type' => 'select'],
            'buyer_amount' => ['section' => 'Buyer Details', 'label' => 'Amount & Currency', 'type' => 'number'],
            
            // Seller Details
            'seller_first_name' => ['section' => 'Seller Details', 'label' => 'Seller First Name', 'type' => 'text'],
            'seller_last_name' => ['section' => 'Seller Details', 'label' => 'Seller Last Name', 'type' => 'text'],
            'seller_phone' => ['section' => 'Seller Details', 'label' => 'Seller Phone Number', 'type' => 'text'],
            'seller_email' => ['section' => 'Seller Details', 'label' => 'Seller Email', 'type' => 'text'],
            'seller_nationality' => ['section' => 'Seller Details', 'label' => 'Seller Nationality', 'type' => 'select'],
            'seller_dob' => ['section' => 'Seller Details', 'label' => 'Seller Date Of Birth', 'type' => 'date'],
            'seller_residency_status' => ['section' => 'Seller Details', 'label' => 'Seller Residency Status', 'type' => 'select'],
            'seller_city' => ['section' => 'Seller Details', 'label' => 'Seller City', 'type' => 'text'],
            'seller_country' => ['section' => 'Seller Details', 'label' => 'Seller Country', 'type' => 'select'],
            'seller_language' => ['section' => 'Seller Details', 'label' => 'Seller Language', 'type' => 'select'],
            
            // Tenant Details
            'tenant_first_name' => ['section' => 'Tenant Details', 'label' => 'Tenant First Name', 'type' => 'text'],
            'tenant_last_name' => ['section' => 'Tenant Details', 'label' => 'Tenant Last Name', 'type' => 'text'],
            'tenant_phone' => ['section' => 'Tenant Details', 'label' => 'Tenant Phone', 'type' => 'text'],
            'tenant_email' => ['section' => 'Tenant Details', 'label' => 'Tenant Email', 'type' => 'text'],
            'tenant_nationality' => ['section' => 'Tenant Details', 'label' => 'Tenant Nationality', 'type' => 'select'],
            'tenant_residency_status' => ['section' => 'Tenant Details', 'label' => 'Tenant Residency Status', 'type' => 'select'],
            'tenant_city' => ['section' => 'Tenant Details', 'label' => 'Tenant City', 'type' => 'text'],
            'tenant_country' => ['section' => 'Tenant Details', 'label' => 'Tenant Country', 'type' => 'select'],
            'tenant_language' => ['section' => 'Tenant Details', 'label' => 'Tenant Language', 'type' => 'select'],
            'tenant_amount' => ['section' => 'Tenant Details', 'label' => 'Amount & Currency', 'type' => 'number'],
            
            // Landlord Details
            'landlord_first_name' => ['section' => 'Landlord Details', 'label' => 'Landlord First Name', 'type' => 'text'],
            'landlord_last_name' => ['section' => 'Landlord Details', 'label' => 'Landlord Last Name', 'type' => 'text'],
            'landlord_phone' => ['section' => 'Landlord Details', 'label' => 'Landlord Phone', 'type' => 'text'],
            'landlord_email' => ['section' => 'Landlord Details', 'label' => 'Landlord Email', 'type' => 'text'],
            'landlord_nationality' => ['section' => 'Landlord Details', 'label' => 'Landlord Nationality', 'type' => 'select'],
            'landlord_dob' => ['section' => 'Landlord Details', 'label' => 'Landlord Date Of Birth', 'type' => 'date'],
            'landlord_residency_status' => ['section' => 'Landlord Details', 'label' => 'Landlord Residency Status', 'type' => 'select'],
            'landlord_city' => ['section' => 'Landlord Details', 'label' => 'Landlord City', 'type' => 'select'],
            'landlord_country' => ['section' => 'Landlord Details', 'label' => 'Landlord Country', 'type' => 'select'],
            'landlord_language' => ['section' => 'Landlord Details', 'label' => 'Landlord Language', 'type' => 'select'],
            
          ];
        
        // Dynamically add document fields for all possible document types
        $documentTypes = [
            'passport', 'kyc', 'national_id', 'payment_proof', 'title_deed', 
            'noc', 'visa', 'ejari', 'tenancy_contract', 'move_in_form'
        ];
        
        $partyTypes = ['buyer', 'seller', 'tenant', 'landlord'];
        
        foreach ($partyTypes as $party) {
            foreach ($documentTypes as $docType) {
                $key = "{$party}_document_{$docType}";
                $meta[$key] = [
                    'section' => 'Upload ' . ucfirst($party) . ' Documents',
                    'label' => ucfirst($party) . ' ' . ucfirst(str_replace('_', ' ', $docType)),
                    'type' => 'file'
                ];
            }
        }
        
        return $meta;
    }
    
   
protected function getRequiredDocumentsByResidency($party, $residencyStatus, $nationality): array
{
    // مقيم (Resident) - يطلب Passport + Visa + National ID
    if ($residencyStatus === 'resident') {
        return ['passport', 'visa', 'national_id'];
    }
    
    // غير مقيم / سائح (Non-resident / Tourist) - يطلب Passport فقط
    if ($residencyStatus === 'non_resident' || $residencyStatus === 'tourist') {
        return ['passport'];
    }
    
    // Investor - يطلب Passport + Investor Visa + National ID
    if ($residencyStatus === 'investor') {
        return ['passport', 'visa', 'national_id'];
    }
    
    // Student - يطلب Passport + Student Visa + National ID
    if ($residencyStatus === 'student') {
        return ['passport', 'visa', 'national_id'];
    }
    
    // Citizen - يطلب Passport + National ID
    if ($residencyStatus === 'citizen') {
        return ['passport', 'national_id'];
    }
    
    // الحالة الافتراضية - يطلب Passport فقط
    return ['passport'];
}
    
    
}