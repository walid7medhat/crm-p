<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\Stage;

class DealStageRequirementEngine
{
    private const PRIMARY_BASIC_BUYER_FIELDS = [
        'first_name',
        'last_name',
        'phone',
        'email',
        'nationality',
        'dob',
        'residency_status',
        'language',
    ];

    private const PRIMARY_ALL_PROPERTY_FIELDS = [
        'area_id',
        'property_type_id',
        'bedrooms',
        'unit_no',
        'unit_size',
        'developer_id',
        'developer_name',
        'developer_phone',
    ];
        private int $currentTargetStageOrder = 0;
    public function __construct(private readonly DealStageValidator $validator)
    {
    }
        private function getRequiredFieldsForStage(int $targetOrder, Deal $deal): array
        {
            $required = [];

            if ($targetOrder === 1) {
                $required = ['deal_name', 'source'];
                return $required;
            }

            if ($targetOrder >= 2 && $targetOrder <= 5) {
                foreach (self::PRIMARY_BASIC_BUYER_FIELDS as $field) {
                    $required[] = "buyer_{$field}";
                }

                $required[] = 'buyer_document_passport';
                $required[] = 'buyer_document_national_id';
            }

            if ($targetOrder >= 2) {
                $required[] = 'at_least_one_property';
            }

            // ✅ تصحيح: إضافة الحقول لكل property موجودة
            if ($targetOrder >= 2) {
                $properties = $deal->properties;
                
                if ($properties->isEmpty()) {
                    // إذا لم توجد خصائص، نضيف للحقل الافتراضي property_0
                    $required = array_merge($required, [
                        'property_0_area_id',
                        'property_0_property_type_id',
                    ]);
                } else {
                    // ✅ إضافة الحقول لكل property موجودة
                    foreach ($properties as $index => $property) {
                        $required[] = "property_{$index}_area_id";
                        $required[] = "property_{$index}_property_type_id";
                        
                        // إضافة budget fields فقط للمراحل المبكرة (order 2)
                        if ($targetOrder == 2) {
                            $required[] = "property_{$index}_budget_from";
                            $required[] = "property_{$index}_budget_to";
                        }
                        
                        // إضافة باقي الحقل للمراحل المتقدمة
                        if ($targetOrder >= 3) {
                            $required[] = "property_{$index}_bedrooms";
                            $required[] = "property_{$index}_unit_no";
                            $required[] = "property_{$index}_unit_size";
                            $required[] = "property_{$index}_developer_id";
                            $required[] = "property_{$index}_developer_name";
                            $required[] = "property_{$index}_developer_phone";
                            $required[] = "property_{$index}_purchase_price";
                        }
                    }
                }
            }

            if ($targetOrder >= 3) {
                $required[] = 'property_document_payment_proof';
                $required[] = 'property_document_spa';
            }

            if ($targetOrder >= 5) {
                $required[] = 'deal_total_amount';
                $required[] = 'buyer_document_kyc';
            }

            // ✅ إزالة budget fields للمراحل المتقدمة (بعد order 2)
            if ($targetOrder > 2) {
                $required = array_filter($required, function($field) {
                    return !str_contains($field, 'budget_');
                });
            }

            return array_values(array_unique($required));
        }
    public function validateStageTransition(Deal $deal, int $targetStageId, array $context = []): array
    {
        // Always reload fresh state so same-request updates/uploads are reflected.
        // loadMissing can keep stale already-loaded relations and cause false 422.
        $deal->unsetRelation('parties');
        $deal->unsetRelation('documents');
        $deal->unsetRelation('properties');
        $deal->load(['parties', 'documents', 'properties']);
        $targetStage = Stage::find($targetStageId);
        $currentStage = Stage::find($deal->stage_id);

        if (!$targetStage || $targetStage->deal_type !== 'primary') {
            return $this->errorResult('Invalid target stage');
        }
        $targetOrder = (int) $targetStage->order;
        $this->currentTargetStageOrder = $targetOrder;
        if ($currentStage && (int) $targetStage->order <= (int) $currentStage->order) {
            return $this->successResult($deal, [
                'current_stage_order' => (int) ($currentStage?->order ?? 0),
                'target_stage_order' => (int) $targetStage->order,
                'note' => 'backward_or_same_stage_transition',
            ]);
        }

        $targetOrder = (int) $targetStage->order;
        $newPaymentProofUploads = max(0, (int) ($context['new_payment_proof_uploads'] ?? 0));
        $evaluationByStage = [];
        $missingFields = [];

        if ($targetOrder === 6) {
            if ($this->isEmptyValue($deal->lost_reason)) {
                $missingFields[] = 'lost_reason';
                $evaluationByStage[] = $this->buildStageEvaluation($targetStage, ['lost_reason']);
            }
        } else {
            for ($order = 1; $order <= min($targetOrder, 5); $order++) {
                $stage = Stage::where('deal_type', 'primary')
                    ->where('stage_type', 'deal')
                    ->where('order', $order)
                    ->first();
                if (!$stage) {
                    continue;
                }
                $stageMissing = $this->validatePrimaryStageByOrder($deal, $order, $newPaymentProofUploads);
                if (!empty($stageMissing)) {
                    $evaluationByStage[] = $this->buildStageEvaluation($stage, $stageMissing);
                    $missingFields = array_merge($missingFields, $stageMissing);
                }
            }
        }

        $missingFields = $this->canonicalizeMissingFields($missingFields);
        $missingByStage = $this->canonicalizeMissingByStage($evaluationByStage);
        $valid = empty($missingFields);
$requiredFields = $this->getRequiredFieldsForStage($targetOrder, $deal);
        return [
            'valid' => $valid,
            'message' => $valid ? 'Validation passed' : 'Missing required fields',
            'missing_fields' => $missingFields,
            'grouped_by_stage' => $missingByStage,
            'missing_by_stage' => $missingByStage,
            'missing_fields_grouped' => $this->validator->getMissingFieldsGroupedForUI($missingFields),
            'missing_fields_grouped_by_stage' => $this->validator->getMissingFieldsGroupedByStageForUI($missingByStage),
            'grouped_missing' => [
                'sections' => $this->validator->getMissingFieldsGroupedForUI($missingFields)['sections'] ?? [],
                'by_stage' => $this->validator->getMissingFieldsGroupedByStageForUI($missingByStage)['stages'] ?? [],
            ],
              'required_fields' => $requiredFields,
            'debug' => [
                'current_stage_order' => (int) ($currentStage?->order ?? 0),
                'target_stage_order' => $targetOrder,
                'payment_proof_count' => $this->countPropertyDocuments($deal, 'payment_proof'),
                'spa_document_count' => $this->countPropertyDocuments($deal, 'spa_document'),
                'property_count' => $deal->properties->count(),
                'new_payment_proof_uploads' => $newPaymentProofUploads,
                'evaluation' => $missingByStage,
            ],
            'deal_type' => 'primary',
            'has_listing_id' => !is_null($deal->listing_id),
        ];
    }

    private function validatePrimaryStageByOrder(Deal $deal, int $order, int $newPaymentProofUploads = 0): array
    {
        $missing = [];

        if ($order === 1) {
            if ($this->isEmptyValue($deal->deal_name)) $missing[] = 'deal_name';
            if ($this->isEmptyValue($deal->source)) $missing[] = 'source';
            return $missing;
        }

        if ($order >= 2 && $order <= 5) {
            $missing = array_merge($missing, $this->validateBuyerBasic($deal));
            $missing = array_merge($missing, $this->validateBuyerDocument($deal, 'passport'));
            $missing = array_merge($missing, $this->validateBuyerDocument($deal, 'national_id'));
        }

        if ($order === 2) {
            $missing = array_merge($missing, $this->validateAtLeastOneProperty($deal));
            $missing = array_merge($missing, $this->validatePropertyFields($deal, [
                'area_id', 'property_type_id', 'bedrooms', 'budget_from', 'budget_to','developer_id','developer_name','developer_phone'
            ]));
            return array_values(array_unique($missing));
        }

        if ($order === 3) {
            $missing = array_merge($missing, $this->validateAtLeastOneProperty($deal));
            $missing = array_merge($missing, $this->validatePropertyFields($deal, array_merge(
                self::PRIMARY_ALL_PROPERTY_FIELDS, ['purchase_price']
            )));
            if ($this->countPropertyDocuments($deal, 'payment_proof') < 1) $missing[] = 'property_document_payment_proof';
            if ($this->countPropertyDocuments($deal, 'spa_document') < 1) $missing[] = 'property_document_spa';
            return array_values(array_unique($missing));
        }

        if ($order === 4) {
            $missing = array_merge($missing, $this->validateAtLeastOneProperty($deal));
            $missing = array_merge($missing, $this->validatePropertyFields($deal, array_merge(
                self::PRIMARY_ALL_PROPERTY_FIELDS, ['purchase_price']
            )));
            // SPA requires one additional new payment proof upload in this transition.
            if ($newPaymentProofUploads < 1) $missing[] = 'property_document_payment_proof';
            if ($this->countPropertyDocuments($deal, 'spa_document') < 1) $missing[] = 'property_document_spa';
            return array_values(array_unique($missing));
        }

        if ($order === 5) {
            $missing = array_merge($missing, $this->validateAtLeastOneProperty($deal));
            $missing = array_merge($missing, $this->validatePropertyFields($deal, array_merge(
                self::PRIMARY_ALL_PROPERTY_FIELDS, ['purchase_price']
            )));
            if ($this->isEmptyValue($deal->deal_total_amount)) $missing[] = 'deal_total_amount';
            // Won requires adding one more payment proof in this transition.
            if ($newPaymentProofUploads < 1) $missing[] = 'property_document_payment_proof';
            if ($this->countPropertyDocuments($deal, 'spa_document') < 1) $missing[] = 'property_document_spa';
            $missing = array_merge($missing, $this->validateBuyerDocument($deal, 'kyc'));
            return array_values(array_unique($missing));
        }

        return array_values(array_unique($missing));
    }

    private function validateBuyerBasic(Deal $deal): array
    {
        $missing = [];
        $buyer = $deal->parties->first(fn ($party) => $party->party_type === 'buyer');
        if (!$buyer) {
            $missing[] = 'buyer_party';
            foreach (self::PRIMARY_BASIC_BUYER_FIELDS as $field) $missing[] = "buyer_{$field}";
            return $missing;
        }
        foreach (self::PRIMARY_BASIC_BUYER_FIELDS as $field) {
            $dbField = $field === 'dob' ? 'date_of_birth' : $field;
            if ($this->isEmptyValue($buyer->{$dbField} ?? null)) $missing[] = "buyer_{$field}";
        }
        return $missing;
    }

    private function validateBuyerDocument(Deal $deal, string $docType): array
    {
        $buyer = $deal->parties->first(fn ($party) => $party->party_type === 'buyer');
        if (!$buyer) return ["buyer_document_{$docType}"];
        $hasDoc = $deal->documents
            ->where('deal_party_id', $buyer->id)
            ->where('document_type', $docType)
            ->isNotEmpty();
        return $hasDoc ? [] : ["buyer_document_{$docType}"];
    }

    private function validateAtLeastOneProperty(Deal $deal): array
    {
        return $deal->properties->isEmpty() ? ['at_least_one_property'] : [];
    }

    private function validatePropertyFields(Deal $deal, array $requiredFields): array
        {
            if ($deal->properties->isEmpty()) {
            
                $missing = [];
            
                foreach ($requiredFields as $field) {
                    $missing[] = "property_0_{$field}";
                }
            
                return $missing;
            }
            $missing = [];

            // أنواع العقارات التي لا تحتاج bedrooms
            $typesWithoutBedrooms = [35,36,24,31];

            foreach ($deal->properties as $index => $property) {

                $fieldsToValidate = $requiredFields;

                // إزالة bedrooms للأراضي/القطع
                if (in_array($property->property_type_id, $typesWithoutBedrooms)) {

                    $fieldsToValidate = array_filter(
                        $fieldsToValidate,
                        fn ($field) => $field !== 'bedrooms'
                    );
                }

                if ($this->currentTargetStageOrder > 2){
                    $fieldsToValidate = array_filter(
                        $fieldsToValidate,
                        fn ($field) => !in_array($field, [
                            'budget_from',
                            'budget_to'
                        ])
                    );
                }

                foreach ($fieldsToValidate as $field) {

                    if ($this->isEmptyValue($property->{$field} ?? null)) {

                        $missing[] = "property_{$index}_{$field}";
                    }
                }
            }

            return $missing;
        }

    private function countPropertyDocuments(Deal $deal, string $type): int
    {
        $field = $type === 'spa_document' ? 'spa_document' : 'payment_proof';
        $count = 0;
        foreach ($deal->properties as $property) {
            $docs = $property->{$field} ?? [];
            if (is_string($docs)) {
                $decoded = json_decode($docs, true);
                $docs = is_array($decoded) ? $decoded : [];
            }
            if (!is_array($docs)) continue;
            foreach ($docs as $doc) {
                if (!$doc) continue;
                if (is_array($doc)) {
                    $hasIdentity = !empty($doc['path']) || !empty($doc['file_path']) || !empty($doc['url']) || !empty($doc['file_url']) || !empty($doc['original_name']) || !empty($doc['file_name']);
                    if ($hasIdentity) $count++;
                } elseif (is_object($doc)) {
                    $hasIdentity = !empty($doc->path) || !empty($doc->file_path) || !empty($doc->url) || !empty($doc->file_url) || !empty($doc->original_name) || !empty($doc->file_name);
                    if ($hasIdentity) $count++;
                }
            }
        }
        return $count;
    }

    private function isEmptyValue($value): bool
    {
        if (is_null($value)) return true;
        if (is_string($value)) return trim($value) === '';
        return false;
    }

    private function buildStageEvaluation(Stage $stage, array $stageMissing): array
    {
        return [
            'stage_order' => (int) $stage->order,
            'stage_id' => $stage->id,
            'stage_name' => $stage->name,
            'missing_fields' => array_values(array_unique($stageMissing)),
        ];
    }

    private function successResult(Deal $deal, array $debug): array
    {
        $requiredFields = $this->getRequiredFieldsForStage($targetOrder, $deal);

        return [
            'valid' => true,
            'message' => 'Validation passed',
            'missing_fields' => [],
            'grouped_by_stage' => [],
            'missing_by_stage' => [],
            'missing_fields_grouped' => ['sections' => []],
            'missing_fields_grouped_by_stage' => ['stages' => []],
            'grouped_missing' => ['sections' => [], 'by_stage' => []],
            'debug' => $debug,
            'deal_type' => 'primary',
            'has_listing_id' => !is_null($deal->listing_id),
             'required_fields' => $requiredFields,
        ];
    }

    private function errorResult(string $message): array
    {
        return [
            'valid' => false,
            'message' => $message,
            'missing_fields' => [],
            'grouped_by_stage' => [],
            'missing_by_stage' => [],
            'missing_fields_grouped' => ['sections' => []],
            'missing_fields_grouped_by_stage' => ['stages' => []],
            'grouped_missing' => ['sections' => [], 'by_stage' => []],
            'debug' => ['error' => $message],
            'deal_type' => 'primary',
            'has_listing_id' => false,
             'required_fields' => [], 
        ];
    }

    private function canonicalizeMissingByStage(array $missingByStage): array
    {
        return array_values(array_map(function ($stage) {
            $fields = $this->canonicalizeMissingFields($stage['missing_fields'] ?? []);
            return [
                'stage_order' => (int) ($stage['stage_order'] ?? 0),
                'stage_id' => $stage['stage_id'] ?? null,
                'stage_name' => $stage['stage_name'] ?? '',
                'missing_fields' => $fields,
            ];
        }, $missingByStage));
    }

    private function canonicalizeMissingFields(array $fields): array
    {
        $mapped = array_map(function ($field) {
            return is_string($field) ? trim($field) : $field;
        }, $fields);
        return array_values(array_unique($mapped));
    }
}
