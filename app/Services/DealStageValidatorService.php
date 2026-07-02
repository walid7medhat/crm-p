<?php

namespace App\Services;

use App\Models\Deal;
use Illuminate\Support\Facades\Log;

class DealStageValidatorService
{
    public function __construct(
        private readonly DealStageValidator $validator,
        private readonly DealStageRequirementEngine $requirementEngine
    ) {
    }

    public function validateStageChange(Deal $deal, int $targetStageId, ?string $dealType = null, ?int $listingId = null, array $context = []): array
{
    // PRIMARY deals: backend-driven single source of truth via engine.
    if ($deal->deal_type === 'primary') {
        return $this->requirementEngine->validateStageTransition($deal, $targetStageId, $context);
    }

    $resolvedType = $dealType ?: $deal->deal_type;
    
    // ✅ استخدام listing_id من الـ Request إذا وجد، وإلا استخدم الموجود في الـ Deal
    $effectiveListingId = $listingId ?? $deal->listing_id;

    Log::info('DealStageValidatorService - validation', [
        'deal_id' => $deal->id,
        'target_stage_id' => $targetStageId,
        'is_eoi_stage' => $this->isEoiStage($targetStageId),
        'deal_listing_id' => $deal->listing_id,
        'request_listing_id' => $listingId,
        'effective_listing_id' => $effectiveListingId,
        'deal_type_from_request' => $dealType,
        'resolved_type' => $resolvedType
    ]);

    $deal->loadMissing(['parties', 'documents',   'properties.propertyType']); // ✅ أضف 'properties' هنا

    $result = $this->validator->validate($deal, $targetStageId, $resolvedType);
    
    $missingFields = $result['missing_fields'] ?? [];
    $missingByStage = $result['missing_by_stage'] ?? [];
    $isValid = $result['valid'] ?? true;
    
    // ✅ تصفية property document fields (إذا كانت المستندات موجودة مسبقاً)
    $missingFields = $this->filterPropertyDocumentFields($missingFields, $deal);
    $missingByStage = $this->filterMissingByStagePropertyDocuments($missingByStage, $deal);
      $stageDateRequirements = $this->getRequiredStageDates($deal, $targetStageId, $resolvedType);
        
        foreach ($stageDateRequirements as $dateField) {
            if (!in_array($dateField, $missingFields)) {
                $missingFields[] = $dateField;
            }
        }
    // ✅ تصفية budget fields حسب المرحلة (قبل أي تصفية أخرى)
    // $missingFields = $this->filterBudgetFieldsByStage($missingFields, $targetStageId);
    // $missingByStage = $this->filterMissingByStageBudgetFields($missingByStage, $targetStageId);
$missingByStage = $this->filterMissingByStageBudgetFields($missingByStage, $targetStageId);

$missingFields = collect($missingByStage)
    ->pluck('missing_fields')
    ->flatten()
    ->unique()
    ->values()
    ->toArray();

$missingFields = $this->filterBudgetFieldsByStage(
    $missingFields,
    $targetStageId
);

$missingFields = $this->filterBedroomsFieldsByPropertyType($missingFields, $deal);
    $missingByStage = $this->filterMissingByStageBedroomsFields($missingByStage, $deal);

    // ✅ purchase_price required for primary/secondary at stage order >= 3 (Booking / MOU and beyond)
    $this->ensurePurchasePriceRequiredForStage($missingFields, $missingByStage, $deal, $targetStageId, $resolvedType);

    // ✅ Property details (area, type, unit_no, etc.) must be required for SECONDARY at every stage.
    $this->ensurePropertyDetailsRequiredForSecondary($missingFields, $missingByStage, $deal, $targetStageId, $resolvedType);

    // ✅ security_deposit is OPTIONAL — strip from missing fields so it never blocks the stage transition.
    $missingFields = $this->filterOptionalSecurityDeposit($missingFields);
    $missingByStage = $this->filterMissingByStageSecurityDeposit($missingByStage);

    // ✅ تمرير effectiveListingId و resolvedType للتصفية
    $filteredMissingFields = $this->filterFieldsByListingAndType($missingFields, $effectiveListingId, $resolvedType);
    $filteredMissingByStage = $this->filterMissingByStageByListingAndType($missingByStage, $effectiveListingId, $resolvedType);
    
    $finalValid = empty($filteredMissingFields);

    return [
        'valid' => $finalValid,
        'missing_fields' => $filteredMissingFields,
        'grouped_missing' => [
            'sections' => $this->validator->getMissingFieldsGroupedForUI($filteredMissingFields)['sections'] ?? [],
            'by_stage' => $this->validator->getMissingFieldsGroupedByStageForUI($filteredMissingByStage)['stages'] ?? [],
        ],
        'message' => empty($filteredMissingFields) ? 'Validation passed' : 'Missing required fields',
        'missing_fields_grouped' => $this->validator->getMissingFieldsGroupedForUI($filteredMissingFields),
        'missing_by_stage' => $filteredMissingByStage,
        'missing_fields_grouped_by_stage' => $this->validator->getMissingFieldsGroupedByStageForUI($filteredMissingByStage),
        'has_listing_id' => !is_null($effectiveListingId),
        'deal_type' => $resolvedType,
    ];
}
    
    /**
     * تحديد ما إذا كان يجب إخفاء Seller
     */
    private function shouldHideSeller(?int $listingId, string $dealType): bool
    {
        $hasListingId = !is_null($listingId);
        $isSecondary = $dealType === 'secondary';
        
        Log::info('shouldHideSeller', [
            'has_listing_id' => $hasListingId,
            'deal_type' => $dealType,
            'result' => $hasListingId && $isSecondary
        ]);
        
        return $hasListingId && $isSecondary;
    }

    /**
     * تحديد ما إذا كان يجب إخفاء Landlord
     */
    private function shouldHideLandlord(?int $listingId, string $dealType): bool
    {
        $hasListingId = !is_null($listingId);
        $isRental = $dealType === 'rental';
        
        return $hasListingId && $isRental;
    }

    /**
     * تصفية الحقول المطلوبة
     */
    private function filterFieldsByListingAndType(array $fields, ?int $listingId, string $dealType): array
    {
        if (empty($fields)) {
            return [];
        }
        
        if (is_null($listingId)) {
            Log::info('No listing_id in request, returning all fields');
            return $fields;
        }
        
        Log::info('Filtering with listing_id', [
            'listing_id' => $listingId,
            'deal_type' => $dealType,
            'original_count' => count($fields)
        ]);
        
        $filtered = $fields;
        
        if ($this->shouldHideSeller($listingId, $dealType)) {
            $filtered = array_filter($filtered, function($field) {
                return !str_starts_with($field, 'seller_') && !str_contains($field, 'seller_document_');
            });
            Log::info('After filtering seller fields', ['count' => count($filtered)]);
        }
        
        if ($this->shouldHideLandlord($listingId, $dealType)) {
            $filtered = array_filter($filtered, function($field) {
                return !str_starts_with($field, 'landlord_') && !str_contains($field, 'landlord_document_');
            });
        }
        $filtered = array_filter($filtered, function($field) {
            if (!str_starts_with($field, 'property_')) return true;
                return true;
        });
        
        return array_values($filtered);
    }
    
    /**
     * تصفية missing_by_stage
     */
    private function filterMissingByStageByListingAndType(array $missingByStage, ?int $listingId, string $dealType): array
    {
        if (empty($missingByStage)) {
            return [];
        }
        
        if (is_null($listingId)) {
            return $missingByStage;
        }
        
        $filteredStages = [];
        
        foreach ($missingByStage as $stage) {
            $stageFields = $stage['missing_fields'] ?? [];
            $filteredFields = $this->filterFieldsByListingAndType($stageFields, $listingId, $dealType);
            
            if (empty($filteredFields)) {
                continue;
            }
            
            $filteredStages[] = [
                'stage_order' => $stage['stage_order'] ?? 0,
                'stage_id' => $stage['stage_id'] ?? null,
                'stage_name' => $stage['stage_name'] ?? '',
                'missing_fields' => $filteredFields,
            ];
        }
        
        return $filteredStages;
    }

    /**
 * تحديد ما إذا كانت المرحلة المستهدفة هي EOI
 */
private function isEoiStage(int $stageId): bool
{
    $stage = \App\Models\Stage::find($stageId);
    if (!$stage) return false;
    
    $stageName = strtolower($stage->name);
    return str_contains($stageName, 'eoi');
}

/**
 * تصفية budget fields بناءً على المرحلة
 */
private function filterBudgetFieldsByStage(array $fields, int $targetStageId): array
{
    $isEoi = $this->isEoiStage($targetStageId);
    
    if (!$isEoi) {
        $filtered = array_filter($fields, function($field) {
            return !str_contains($field, 'budget_from') && !str_contains($field, 'budget_to');
        });
        
        Log::info('Filtering budget fields', [
            'target_stage_id' => $targetStageId,
            'is_eoi' => $isEoi,
            'original_count' => count($fields),
            'filtered_count' => count($filtered),
            'removed_fields' => array_values(array_diff($fields, $filtered))
        ]);
        
        return array_values($filtered);
    }
    
    return $fields;
}
/**
 * تصفية budget fields في missing_by_stage بناءً على المرحلة
 */
private function filterMissingByStageBudgetFields(array $missingByStage, int $targetStageId): array
{
    if (empty($missingByStage)) {
        return [];
    }
    
    $isEoi = $this->isEoiStage($targetStageId);
    
    // إذا كانت المرحلة EOI، لا نقوم بإزالة شيء
    if ($isEoi) {
        return $missingByStage;
    }
    
    $filteredStages = [];
    
    foreach ($missingByStage as $stage) {
        $stageFields = $stage['missing_fields'] ?? [];
        
        // إزالة budget fields من stage fields
        $filteredStageFields = array_filter($stageFields, function($field) {
            return !str_contains($field, 'budget_from') && !str_contains($field, 'budget_to');
        });
        
        $filteredStageFields = array_values($filteredStageFields);
        
        // فقط إذا بقي حقول بعد التصفية، نضيف المرحلة
        if (!empty($filteredStageFields)) {
            $filteredStages[] = [
                'stage_order' => $stage['stage_order'] ?? 0,
                'stage_id' => $stage['stage_id'] ?? null,
                'stage_name' => $stage['stage_name'] ?? '',
                'missing_fields' => $filteredStageFields,
            ];
        }
    }
    
    Log::info('Filtering budget fields in missing_by_stage', [
        'target_stage_id' => $targetStageId,
        'is_eoi' => $isEoi,
        'original_stages_count' => count($missingByStage),
        'filtered_stages_count' => count($filteredStages)
    ]);
    
    return $filteredStages;
}
/**
 * التحقق من وجود مستندات الـ Property في أي property
 */
private function hasPropertyDocuments(Deal $deal, string $documentType): bool
{
    // Column mapping for each document type (payment_proof, spa, mou, noc, eoi, booking).
    $columnMap = [
        'payment_proof' => 'payment_proof',
        'spa_document' => 'spa_document',
        'spa' => 'spa_document',
        'mou' => 'mou_documents',
        'mou_documents' => 'mou_documents',
        'noc' => 'noc_documents',
        'noc_documents' => 'noc_documents',
        'eoi' => 'eoi_documents',
        'eoi_documents' => 'eoi_documents',
        'booking' => 'booking_documents',
        'booking_documents' => 'booking_documents',
    ];
    $column = $columnMap[$documentType] ?? 'spa_document';

    // التحقق المباشر من قاعدة البيانات
    $exists = \App\Models\DealProperty::where('deal_id', $deal->id)
        ->where(function($query) use ($column) {
            $query->whereNotNull($column)
                  ->where($column, '!=', '')
                  ->where($column, '!=', '[]')
                  ->where($column, '!=', 'null');
        })
        ->exists();
    
    Log::info('hasPropertyDocuments direct DB check', [
        'deal_id' => $deal->id,
        'document_type' => $documentType,
        'exists' => $exists
    ]);
    
    return $exists;
}

/**
 * تصفية property document fields بناءً على وجود مستندات موجودة مسبقاً
 */
private function filterPropertyDocumentFields(array $fields, Deal $deal): array
{
    if (empty($fields)) {
        return [];
    }
    
    $filtered = [];
    
    foreach ($fields as $field) {
        $shouldSkip = false;
        
        // التحقق من وجود مستندات payment_proof
        if ($field === 'property_document_payment_proof' || 
            $field === 'property_document_payment' ||
            $field === 'payment_proof' ||
            str_contains($field, 'payment_proof')) {
            
            if ($this->hasPropertyDocuments($deal, 'payment_proof')) {
                Log::info('Skipping payment_proof requirement - documents already exist', [
                    'field' => $field,
                    'deal_id' => $deal->id
                ]);
                $shouldSkip = true;
            }
        }
        
        // التحقق من وجود مستندات spa_document
        if (!$shouldSkip && (
            $field === 'property_document_spa' ||
            $field === 'property_document_spa_document' ||
            $field === 'spa_document' ||
            str_contains($field, 'spa_document'))) {

            if ($this->hasPropertyDocuments($deal, 'spa_document')) {
                Log::info('Skipping spa_document requirement - documents already exist', [
                    'field' => $field,
                    'deal_id' => $deal->id
                ]);
                $shouldSkip = true;
            }
        }

        // التحقق من وجود مستندات mou
        if (!$shouldSkip && (
            $field === 'property_document_mou' ||
            $field === 'property_document_mou_documents' ||
            $field === 'mou_documents' ||
            str_contains($field, 'mou_document') ||
            str_contains($field, '_document_mou'))) {

            if ($this->hasPropertyDocuments($deal, 'mou')) {
                Log::info('Skipping mou requirement - documents already exist', [
                    'field' => $field,
                    'deal_id' => $deal->id
                ]);
                $shouldSkip = true;
            }
        }

        // التحقق من وجود مستندات noc (property-level)
        if (!$shouldSkip && (
            $field === 'property_document_noc' ||
            $field === 'property_document_noc_documents' ||
            $field === 'noc_documents' ||
            str_contains($field, 'noc_document') ||
            str_contains($field, '_document_noc'))) {

            if ($this->hasPropertyDocuments($deal, 'noc')) {
                Log::info('Skipping noc requirement - documents already exist', [
                    'field' => $field,
                    'deal_id' => $deal->id
                ]);
                $shouldSkip = true;
            }
        }

        if (!$shouldSkip) {
            $filtered[] = $field;
        }
    }

    return $filtered;
}
/**
 * تصفية property document fields في missing_by_stage
 */
private function filterMissingByStagePropertyDocuments(array $missingByStage, Deal $deal): array
{
    if (empty($missingByStage)) {
        return [];
    }
    
    $filteredStages = [];
    
    foreach ($missingByStage as $stage) {
        $stageFields = $stage['missing_fields'] ?? [];
        
        // ✅ تصفية property document fields
        $filteredStageFields = $this->filterPropertyDocumentFields($stageFields, $deal);
        
        if (!empty($filteredStageFields)) {
            $filteredStages[] = [
                'stage_order' => $stage['stage_order'] ?? 0,
                'stage_id' => $stage['stage_id'] ?? null,
                'stage_name' => $stage['stage_name'] ?? '',
                'missing_fields' => $filteredStageFields,
            ];
        }
    }
    
    return $filteredStages;
}
/**
 * تصفية bedrooms fields بناءً على نوع العقار (إزالة للأراضي/القطع)
 */
private function filterBedroomsFieldsByPropertyType(array $fields, Deal $deal): array
{
    if (empty($fields)) {
        return [];
    }
    
    $filtered = [];
    $properties = $deal->properties ?? collect([]);
                 Log::info('PROPERTY TYPE DEBUG');
    foreach ($fields as $field) {
        $shouldSkip = false;
        
        // التحقق من وجود property_X_bedrooms
        if (preg_match('/property_(\d+)_bedrooms/', $field, $matches)) {
            $propertyIndex = (int) $matches[1];
            $property = $properties->values()->get($propertyIndex);
            
            if ($property && $property->propertyType) {
                $typeName = strtolower($property->propertyType->name ?? '');
                Log::info('PROPERTY TYPE DEBUG', [
    'field' => $field,
    'property_index' => $propertyIndex,
    'property_id' => $property?->id,
    'property_type_id' => $property?->property_type_id,
    'property_type_relation' => $property?->propertyType,
    'property_type_name' => $typeName,
]);
                if (str_contains($typeName, 'land') || str_contains($typeName, 'plot')) {
                    Log::info('Skipping bedrooms requirement - property type is land/plot', [
                        'field' => $field,
                        'property_index' => $propertyIndex,
                        'property_type' => $typeName
                    ]);
                    $shouldSkip = true;
                }
            }
        }
        
        if (!$shouldSkip) {
            $filtered[] = $field;
        }
    }
    
    return $filtered;
}

/**
 * يضمن أن purchase_price مطلوب في primary/secondary من Booking/MOU (order 3) فما فوق.
 * يضيف property_{i}_purchase_price لأي عقار ليس له قيمة، حتى لو لم يصل المفتاح من DealStageValidator.
 */
private function ensurePurchasePriceRequiredForStage(array &$missingFields, array &$missingByStage, Deal $deal, int $targetStageId, string $dealType): void
{
    if (!in_array($dealType, ['primary', 'secondary'], true)) {
        return;
    }

    $stage = \App\Models\Stage::find($targetStageId);
    if (!$stage) {
        return;
    }
    $order = (int) ($stage->order ?? 0);
    if ($order < 3) {
        return;
    }

    $properties = $deal->properties ?? collect();
    $newKeys = [];

    if ($properties->isEmpty()) {
        $newKeys[] = 'property_0_purchase_price';
    } else {
        foreach ($properties as $index => $property) {
            $raw = $property->purchase_price ?? null;
            $hasValue = !is_null($raw) && $raw !== '' && $raw !== 0 && $raw !== '0';
            // Treat numeric 0 as "filled" intentionally — only blank/null counts as missing.
            if (is_null($raw) || $raw === '' || $raw === '0') {
                $hasValue = false;
            }
            if (!$hasValue) {
                $newKeys[] = "property_{$index}_purchase_price";
            }
        }
    }

    if (empty($newKeys)) {
        return;
    }

    foreach ($newKeys as $key) {
        if (!in_array($key, $missingFields, true)) {
            $missingFields[] = $key;
        }
    }

    // Merge into the target stage bucket inside missing_by_stage so UI groups it correctly.
    $foundStageBucket = false;
    foreach ($missingByStage as &$bucket) {
        if ((int) ($bucket['stage_id'] ?? 0) === (int) $stage->id) {
            $bucket['missing_fields'] = array_values(array_unique(array_merge($bucket['missing_fields'] ?? [], $newKeys)));
            $foundStageBucket = true;
            break;
        }
    }
    unset($bucket);

    if (!$foundStageBucket) {
        $missingByStage[] = [
            'stage_order' => $order,
            'stage_id' => $stage->id,
            'stage_name' => $stage->name,
            'missing_fields' => $newKeys,
        ];
    }
}

/**
 * Property details (area, property type, unit no, etc.) are required for SECONDARY deals at
 * every stage — stage 2 (Security Deposit) needs the basics, stage 3+ also needs bedrooms,
 * unit size, developer info. This forces them into missing_fields if the property record
 * doesn't have a value, even when DealStageValidator's per-stage check missed them.
 */
private function ensurePropertyDetailsRequiredForSecondary(
    array &$missingFields,
    array &$missingByStage,
    Deal $deal,
    int $targetStageId,
    string $dealType
): void {
    if ($dealType !== 'secondary') {
        return;
    }

    $stage = \App\Models\Stage::find($targetStageId);
    if (!$stage) {
        return;
    }
    $order = (int) ($stage->order ?? 0);
    if ($order < 2) {
        return;
    }

    // Stage 2 (Security Deposit) basics; stage 3+ adds the deeper property fields.
    $required = ['area_id', 'property_type_id', 'unit_no'];
    if ($order >= 3) {
        $required = array_merge($required, ['bedrooms', 'unit_size', 'developer_name', 'developer_phone']);
    }

    $properties = $deal->properties ?? collect();
    $newKeys = [];

    if ($properties->isEmpty()) {
        foreach ($required as $field) {
            $newKeys[] = "property_0_{$field}";
        }
    } else {
        foreach ($properties as $index => $property) {
            foreach ($required as $field) {
                $value = $property->$field ?? null;
                $isEmpty = is_null($value) || $value === '' || $value === '0';
                if ($isEmpty) {
                    $newKeys[] = "property_{$index}_{$field}";
                }
            }
        }
    }

    if (empty($newKeys)) {
        return;
    }

    foreach ($newKeys as $key) {
        if (!in_array($key, $missingFields, true)) {
            $missingFields[] = $key;
        }
    }

    // Merge into the target stage bucket so the UI groups them under the right stage.
    $foundStageBucket = false;
    foreach ($missingByStage as &$bucket) {
        if ((int) ($bucket['stage_id'] ?? 0) === (int) $stage->id) {
            $bucket['missing_fields'] = array_values(array_unique(array_merge($bucket['missing_fields'] ?? [], $newKeys)));
            $foundStageBucket = true;
            break;
        }
    }
    unset($bucket);

    if (!$foundStageBucket) {
        $missingByStage[] = [
            'stage_order' => $order,
            'stage_id' => $stage->id,
            'stage_name' => $stage->name,
            'missing_fields' => $newKeys,
        ];
    }
}

/**
 * Strip *_document_security_deposit keys from the flat missing list.
 * security_deposit is shown in the UI for secondary stage 2+ but is OPTIONAL.
 */
private function filterOptionalSecurityDeposit(array $fields): array
{
    if (empty($fields)) {
        return [];
    }

    return array_values(array_filter($fields, function ($field) {
        return !preg_match('/^(buyer|seller|tenant|landlord)_document_security_deposit$/', (string) $field);
    }));
}

/**
 * Strip *_document_security_deposit keys from each missing_by_stage bucket
 * and drop buckets that become empty after filtering.
 */
private function filterMissingByStageSecurityDeposit(array $missingByStage): array
{
    if (empty($missingByStage)) {
        return [];
    }

    $filteredStages = [];

    foreach ($missingByStage as $stage) {
        $stageFields = $this->filterOptionalSecurityDeposit($stage['missing_fields'] ?? []);

        if (!empty($stageFields)) {
            $filteredStages[] = [
                'stage_order' => $stage['stage_order'] ?? 0,
                'stage_id' => $stage['stage_id'] ?? null,
                'stage_name' => $stage['stage_name'] ?? '',
                'missing_fields' => $stageFields,
            ];
        }
    }

    return $filteredStages;
}

/**
 * تصفية bedrooms fields في missing_by_stage
 */
private function filterMissingByStageBedroomsFields(array $missingByStage, Deal $deal): array
{
    if (empty($missingByStage)) {
        return [];
    }
    
    $filteredStages = [];
    
    foreach ($missingByStage as $stage) {
        $stageFields = $stage['missing_fields'] ?? [];
        $filteredStageFields = $this->filterBedroomsFieldsByPropertyType($stageFields, $deal);
        
        if (!empty($filteredStageFields)) {
            $filteredStages[] = [
                'stage_order' => $stage['stage_order'] ?? 0,
                'stage_id' => $stage['stage_id'] ?? null,
                'stage_name' => $stage['stage_name'] ?? '',
                'missing_fields' => $filteredStageFields,
            ];
        }
    }
    
    return $filteredStages;
}
  private function getStageDateField(int $stageOrder, string $dealType): ?string
    {
        $dateFieldMap = [
            'primary' => [
                2 => 'eoi_date',
                3 => 'booking_date',
                4 => 'spa_date',
                // 5 => 'won_date',
            ],
            'secondary' => [
                2 => 'security_deposit_date',
                3 => 'mou_date',
                4 => 'noc_date',
                // 5 => 'won_date',
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
     * التحقق من وجود تاريخ المرحلة
     */
    private function hasStageDate(Deal $deal, int $stageOrder, string $dealType): bool
    {
        $field = $this->getStageDateField($stageOrder, $dealType);
        if (!$field) {
            return true;
        }

        $value = $deal->$field ?? null;
        return !empty($value);
    }

    /**
     * الحصول على تواريخ المراحل المطلوبة
     */
    private function getRequiredStageDates(Deal $deal, int $targetStageId, string $dealType): array
    {
        $stage = \App\Models\Stage::find($targetStageId);
        if (!$stage) {
            return [];
        }

        $targetOrder = (int) $stage->order;
        $requiredDates = [];

        // التحقق من تاريخ المرحلة الحالية
        $currentDateField = $this->getStageDateField($targetOrder, $dealType);
        if ($currentDateField && empty($deal->$currentDateField)) {
            $requiredDates[] = "stage_date_{$currentDateField}";
        }

        // التحقق من تواريخ المراحل السابقة
        for ($order = 2; $order < $targetOrder; $order++) {
            $dateField = $this->getStageDateField($order, $dealType);
            if ($dateField && empty($deal->$dateField)) {
                $requiredDates[] = "stage_date_{$dateField}";
            }
        }

        return $requiredDates;
    }

}