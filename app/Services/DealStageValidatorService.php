<?php

namespace App\Services;

use App\Models\Deal;
use Illuminate\Support\Facades\Log;

class DealStageValidatorService
{
    public function __construct(
        private readonly DealStageValidator $validator
    ) {
    }

    public function validateStageChange(Deal $deal, int $targetStageId, ?string $dealType = null, ?int $listingId = null): array
    {
        $resolvedType = $dealType ?: $deal->deal_type;
        
        // ✅ استخدام listing_id من الـ Request إذا وجد، وإلا استخدم الموجود في الـ Deal
        $effectiveListingId = $listingId ?? $deal->listing_id;

        Log::info('DealStageValidatorService - validation', [
            'deal_id' => $deal->id,
            'deal_listing_id' => $deal->listing_id,
            'request_listing_id' => $listingId,
            'effective_listing_id' => $effectiveListingId,
            'deal_type_from_request' => $dealType,
            'resolved_type' => $resolvedType
        ]);

        $deal->loadMissing(['parties', 'documents']);

        $result = $this->validator->validate($deal, $targetStageId, $resolvedType);
        
        $missingFields = $result['missing_fields'] ?? [];
        $missingByStage = $result['missing_by_stage'] ?? [];
        $isValid = $result['valid'] ?? true;
        
        // ✅ تمرير effectiveListingId و resolvedType للتصفية
        $filteredMissingFields = $this->filterFieldsByListingAndType($missingFields, $effectiveListingId, $resolvedType);
        $filteredMissingByStage = $this->filterMissingByStageByListingAndType($missingByStage, $effectiveListingId, $resolvedType);
        
        $finalValid =  empty($filteredMissingFields);

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
            'has_listing_id' => !is_null($effectiveListingId), // ✅ للفرونت إند
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
        
        // ✅ إذا لم يكن هناك listing_id، نرجع كل الحقول
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
}