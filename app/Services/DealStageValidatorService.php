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
    // التحقق المباشر من قاعدة البيانات
    $exists = \App\Models\DealProperty::where('deal_id', $deal->id)
        ->where(function($query) use ($documentType) {
            if ($documentType === 'payment_proof') {
                $query->whereNotNull('payment_proof')
                      ->where('payment_proof', '!=', '')
                      ->where('payment_proof', '!=', '[]')
                      ->where('payment_proof', '!=', 'null');
            } else {
                $query->whereNotNull('spa_document')
                      ->where('spa_document', '!=', '')
                      ->where('spa_document', '!=', '[]')
                      ->where('spa_document', '!=', 'null');
            }
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
}