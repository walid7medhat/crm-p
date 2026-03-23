<?php

namespace App\Services;

use App\Models\Deal;

class DealStageValidatorService
{
    public function __construct(
        private readonly DealStageValidator $validator
    ) {
    }

    public function validateStageChange(Deal $deal, int $targetStageId, ?string $dealType = null): array
    {
        $resolvedType = $dealType ?: $deal->deal_type;

        $deal->loadMissing(['parties', 'documents']);

        $result = $this->validator->validate($deal, $targetStageId, $resolvedType);
        $missingFields = $result['missing_fields'] ?? [];
        $missingByStage = $result['missing_by_stage'] ?? [];

        return [
            'valid' => (bool) ($result['valid'] ?? true),
            'missing_fields' => $missingFields,
            // New normalized contract
            'grouped_missing' => [
                'sections' => $this->validator->getMissingFieldsGroupedForUI($missingFields)['sections'] ?? [],
                'by_stage' => $this->validator->getMissingFieldsGroupedByStageForUI($missingByStage)['stages'] ?? [],
            ],
            'message' => empty($missingFields) ? 'Validation passed' : 'Missing required fields',

            // Backward-compatible keys used by existing frontend
            'missing_fields_grouped' => $this->validator->getMissingFieldsGroupedForUI($missingFields),
            'missing_by_stage' => $missingByStage,
            'missing_fields_grouped_by_stage' => $this->validator->getMissingFieldsGroupedByStageForUI($missingByStage),
        ];
    }
}

