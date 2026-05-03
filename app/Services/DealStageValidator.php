<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\Stage;
use Illuminate\Support\Facades\Log;

class DealStageValidator
{
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
        
        if (!$currentStage || !$targetStage) {
            return ['valid' => true];
        }

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

        // Load relationships
        $deal->loadMissing(['parties', 'documents', 'properties']);
        
        $parties = [];
        foreach ($deal->parties as $party) {
            $parties[$party->party_type] = $party;
        }

        // Check if lost stage
        $isLostStage = false;
        if ($dealType === 'primary' && $targetStage->order == 6) {
            $isLostStage = true;
        } elseif (($dealType === 'secondary' || $dealType === 'rental') && $targetStage->order == 8) {
            $isLostStage = true;
        }
        
        if ($isLostStage) {
            $requirements = $this->stageRequirements[$dealType][$targetStage->order] ?? null;
            if ($requirements) {
                $stageMissing = [];
                foreach ($requirements['fields'] ?? [] as $field) {
                    if (empty($deal->$field)) {
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
            for ($order = $startOrder; $order <= $endOrder; $order++) {
                $stage = $allStages->firstWhere('order', $order);
                if (!$stage) continue;

                $requirements = $this->stageRequirements[$dealType][$order] ?? null;
                if (!$requirements) continue;

                $stageMissing = [];

                // Check basic fields
                foreach ($requirements['fields'] ?? [] as $field) {
                    if (empty($deal->$field)) {
                        $stageMissing[] = $field;
                        $missingFields[] = $field;
                    }
                }

                // Check parties
                foreach ($requirements['parties'] ?? [] as $partyType => $fields) {
                    $party = $parties[$partyType] ?? null;
                    
                    if (!$party) {
                        $stageMissing[] = "{$partyType}_party";
                        $missingFields[] = "{$partyType}_party";
                        foreach ($fields as $field) {
                            $stageMissing[] = "{$partyType}_{$field}";
                            $missingFields[] = "{$partyType}_{$field}";
                        }
                        continue;
                    }
                    
                    foreach ($fields as $field) {
                        $modelField = $this->partyFieldMap[$field] ?? $field;
                        if (empty($party->$modelField)) {
                            $stageMissing[] = "{$partyType}_{$field}";
                            $missingFields[] = "{$partyType}_{$field}";
                        }
                    }
                }

                // Check party documents
                foreach ($requirements['documents'] ?? [] as $partyType => $docs) {
                    $party = $parties[$partyType] ?? null;
                    $residencyStatus = $party?->residency_status ?? null;
                    $requiredDocs = $this->getRequiredDocumentsByResidency($residencyStatus);
                    
                    foreach ($docs as $docType) {
                        if (!in_array($docType, $requiredDocs)) continue;
                        
                        $hasDoc = false;
                        if ($party) {
                            $hasDoc = $deal->documents()
                                ->where('deal_party_id', $party->id)
                                ->where('document_type', $docType)
                                ->exists();
                        }
                        
                        if (!$hasDoc) {
                            $key = "{$partyType}_document_{$docType}";
                            $stageMissing[] = $key;
                            $missingFields[] = $key;
                        }
                    }
                }

                // ========== CHECK MULTI PROPERTIES ==========
                $properties = $deal->properties ?? collect();
                
                // Check if at least one property exists
                if (($requirements['requires_properties'] ?? false) && $properties->isEmpty()) {
                    $stageMissing[] = 'at_least_one_property';
                    $missingFields[] = 'at_least_one_property';
                }
                
                // Check property fields (first property must be complete)
                foreach ($requirements['properties'] ?? [] as $field => $isRequired) {
                    if ($isRequired) {
                        $firstProperty = $properties->first();
                        if (!$firstProperty || empty($firstProperty->$field)) {
                            $stageMissing[] = "property_{$field}";
                            $missingFields[] = "property_{$field}";
                        }
                    }
                }
                
                // Check property documents
                foreach ($requirements['property_documents'] ?? [] as $docType) {
                    $hasDoc = false;
                    foreach ($properties as $property) {
                        if ($docType === 'payment_proof' && !empty($property->payment_proof)) {
                            $hasDoc = true;
                            break;
                        }
                        if ($docType === 'spa' && !empty($property->spa_document)) {
                            $hasDoc = true;
                            break;
                        }
                        if ($docType === 'contract' && !empty($property->contract_document)) {
                            $hasDoc = true;
                            break;
                        }
                        if ($docType === 'ejari' && !empty($property->ejari_document)) {
                            $hasDoc = true;
                            break;
                        }
                    }
                    if (!$hasDoc) {
                        $key = "property_document_{$docType}";
                        $stageMissing[] = $key;
                        $missingFields[] = $key;
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

        return [
            'valid' => empty($missingFields),
            'missing_fields' => array_values(array_unique($missingFields)),
            'missing_by_stage' => $missingByStage,
        ];
    }

    protected function getRequiredDocumentsByResidency($residencyStatus): array
    {
        $status = $residencyStatus ? strtolower($residencyStatus) : null;
        
        if ($status === 'resident') {
            return ['passport', 'national_id'];
        }
        
        return ['passport'];
    }

    public function getMissingFieldsGroupedForUI(array $missingFields): array
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
            'Upload Property Documents',
            'Deal Financials',
            'Other',
        ];
        
        $bySection = [];
        
        foreach ($missingFields as $key) {
            if (str_ends_with($key, '_party')) continue;
            
            // Property documents
            if (str_contains($key, 'property_document_')) {
                $docType = str_replace('property_document_', '', $key);
                $section = 'Upload Property Documents';
                $bySection[$section][] = [
                    'key' => $key,
                    'label' => 'Property ' . ucfirst(str_replace('_', ' ', $docType)),
                    'type' => 'file'
                ];
            }
            // Property fields
            elseif (str_starts_with($key, 'property_')) {
                $field = str_replace('property_', '', $key);
                $section = 'Property Details';
                $label = $this->humanizeFieldKey($field);
                $bySection[$section][] = [
                    'key' => $key,
                    'label' => $label,
                    'type' => 'text'
                ];
            }
            // At least one property
            elseif ($key === 'at_least_one_property') {
                $bySection['Property Details'][] = [
                    'key' => $key,
                    'label' => 'At least one property is required',
                    'type' => 'text'
                ];
            }
            // Party documents
            elseif (str_contains($key, '_document_')) {
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
            // Regular fields
            else {
                $meta = $fieldMeta[$key] ?? [
                    'section' => 'Other',
                    'label' => $this->humanizeFieldKey($key),
                    'type' => 'text'
                ];
                $section = $meta['section'];
                if (!isset($bySection[$section])) $bySection[$section] = [];
                $bySection[$section][] = [
                    'key' => $key,
                    'label' => $meta['label'],
                    'type' => $meta['type'],
                ];
            }
        }
        
        $sections = [];
        foreach ($sectionOrder as $title) {
            if (!empty($bySection[$title])) {
                $sections[] = ['title' => $title, 'fields' => $bySection[$title]];
            }
        }
        
        if (!empty($bySection['Other'])) {
            $sections[] = ['title' => 'Other', 'fields' => $bySection['Other']];
        }
        
        return ['sections' => $sections];
    }

    protected function humanizeFieldKey(string $key): string
    {
        return ucwords(str_replace('_', ' ', $key));
    }

    protected function getFieldMeta(): array
    {
        return [
            // Property Details (for UI)
            'property_area_id' => ['section' => 'Property Details', 'label' => 'Address', 'type' => 'select'],
            'property_property_type_id' => ['section' => 'Property Details', 'label' => 'Property Type', 'type' => 'select'],
            'property_bedrooms' => ['section' => 'Property Details', 'label' => 'Bedrooms', 'type' => 'select'],
            'property_unit_no' => ['section' => 'Property Details', 'label' => 'Unit No', 'type' => 'text'],
            'property_unit_size' => ['section' => 'Property Details', 'label' => 'Unit Size', 'type' => 'text'],
            'property_developer_id' => ['section' => 'Property Details', 'label' => 'Developer', 'type' => 'select'],
            'property_developer_name' => ['section' => 'Property Details', 'label' => 'Developer Contact Name', 'type' => 'text'],
            'property_developer_phone' => ['section' => 'Property Details', 'label' => 'Developer Contact Phone', 'type' => 'text'],
            'property_budget_from' => ['section' => 'Property Details', 'label' => 'Budget From', 'type' => 'number'],
            'property_budget_to' => ['section' => 'Property Details', 'label' => 'Budget To', 'type' => 'number'],
            'property_purchase_price' => ['section' => 'Property Details', 'label' => 'Purchase Price', 'type' => 'number'],
            'property_rental_price' => ['section' => 'Property Details', 'label' => 'Rental Price', 'type' => 'number'],
            
            // Deal fields
            'source' => ['section' => 'Property Details', 'label' => 'Source', 'type' => 'text'],
            'deal_name' => ['section' => 'Property Details', 'label' => 'Deal Name', 'type' => 'text'],
            'deal_total_amount' => ['section' => 'Deal Financials', 'label' => 'Deal Total Amount', 'type' => 'number'],
            'deal_commission' => ['section' => 'Deal Financials', 'label' => 'Commission %', 'type' => 'number'],
            'lost_reason' => ['section' => 'Deal Financials', 'label' => 'Lost Reason', 'type' => 'text'],
            
            // Buyer Details
            'buyer_first_name' => ['section' => 'Buyer Details', 'label' => 'First Name', 'type' => 'text'],
            'buyer_last_name' => ['section' => 'Buyer Details', 'label' => 'Last Name', 'type' => 'text'],
            'buyer_phone' => ['section' => 'Buyer Details', 'label' => 'Phone', 'type' => 'text'],
            'buyer_email' => ['section' => 'Buyer Details', 'label' => 'Email', 'type' => 'email'],
            'buyer_nationality' => ['section' => 'Buyer Details', 'label' => 'Nationality', 'type' => 'select'],
            'buyer_dob' => ['section' => 'Buyer Details', 'label' => 'Date of Birth', 'type' => 'date'],
            'buyer_residency_status' => ['section' => 'Buyer Details', 'label' => 'Residency Status', 'type' => 'select'],
            'buyer_city' => ['section' => 'Buyer Details', 'label' => 'City', 'type' => 'text'],
            'buyer_country' => ['section' => 'Buyer Details', 'label' => 'Country', 'type' => 'select'],
            'buyer_language' => ['section' => 'Buyer Details', 'label' => 'Language', 'type' => 'select'],
            
            // Seller Details
            'seller_first_name' => ['section' => 'Seller Details', 'label' => 'First Name', 'type' => 'text'],
            'seller_last_name' => ['section' => 'Seller Details', 'label' => 'Last Name', 'type' => 'text'],
            'seller_phone' => ['section' => 'Seller Details', 'label' => 'Phone', 'type' => 'text'],
            'seller_email' => ['section' => 'Seller Details', 'label' => 'Email', 'type' => 'email'],
            'seller_nationality' => ['section' => 'Seller Details', 'label' => 'Nationality', 'type' => 'select'],
            'seller_dob' => ['section' => 'Seller Details', 'label' => 'Date of Birth', 'type' => 'date'],
            'seller_residency_status' => ['section' => 'Seller Details', 'label' => 'Residency Status', 'type' => 'select'],
            'seller_city' => ['section' => 'Seller Details', 'label' => 'City', 'type' => 'text'],
            'seller_country' => ['section' => 'Seller Details', 'label' => 'Country', 'type' => 'select'],
            'seller_language' => ['section' => 'Seller Details', 'label' => 'Language', 'type' => 'select'],
            
            // Tenant Details
            'tenant_first_name' => ['section' => 'Tenant Details', 'label' => 'First Name', 'type' => 'text'],
            'tenant_last_name' => ['section' => 'Tenant Details', 'label' => 'Last Name', 'type' => 'text'],
            'tenant_phone' => ['section' => 'Tenant Details', 'label' => 'Phone', 'type' => 'text'],
            'tenant_email' => ['section' => 'Tenant Details', 'label' => 'Email', 'type' => 'email'],
            'tenant_nationality' => ['section' => 'Tenant Details', 'label' => 'Nationality', 'type' => 'select'],
            'tenant_residency_status' => ['section' => 'Tenant Details', 'label' => 'Residency Status', 'type' => 'select'],
            'tenant_city' => ['section' => 'Tenant Details', 'label' => 'City', 'type' => 'text'],
            'tenant_country' => ['section' => 'Tenant Details', 'label' => 'Country', 'type' => 'select'],
            'tenant_language' => ['section' => 'Tenant Details', 'label' => 'Language', 'type' => 'select'],
            
            // Landlord Details
            'landlord_first_name' => ['section' => 'Landlord Details', 'label' => 'First Name', 'type' => 'text'],
            'landlord_last_name' => ['section' => 'Landlord Details', 'label' => 'Last Name', 'type' => 'text'],
            'landlord_phone' => ['section' => 'Landlord Details', 'label' => 'Phone', 'type' => 'text'],
            'landlord_email' => ['section' => 'Landlord Details', 'label' => 'Email', 'type' => 'email'],
            'landlord_nationality' => ['section' => 'Landlord Details', 'label' => 'Nationality', 'type' => 'select'],
            'landlord_dob' => ['section' => 'Landlord Details', 'label' => 'Date of Birth', 'type' => 'date'],
            'landlord_residency_status' => ['section' => 'Landlord Details', 'label' => 'Residency Status', 'type' => 'select'],
            'landlord_city' => ['section' => 'Landlord Details', 'label' => 'City', 'type' => 'text'],
            'landlord_country' => ['section' => 'Landlord Details', 'label' => 'Country', 'type' => 'select'],
            'landlord_language' => ['section' => 'Landlord Details', 'label' => 'Language', 'type' => 'select'],
        ];
    }
    /**
 * Get missing fields grouped by stage for UI
 */
public function getMissingFieldsGroupedByStageForUI(array $missingByStage): array
{
    $grouped = [];
    
    foreach ($missingByStage as $stageMissing) {
        $stageId = $stageMissing['stage_id'];
        $stageName = $stageMissing['stage_name'];
        $stageOrder = $stageMissing['stage_order'];
        $missingFields = $stageMissing['missing_fields'];
        
        // Group missing fields by section
        $groupedFields = $this->getMissingFieldsGroupedForUI($missingFields);
        
        $grouped[] = [
            'stage_id' => $stageId,
            'stage_name' => $stageName,
            'stage_order' => $stageOrder,
            'missing_fields' => $missingFields,
            'grouped_missing' => $groupedFields,
        ];
    }
    
    return $grouped;
}

/**
 * Get missing fields grouped by stage (alternative version)
 */
public function getMissingFieldsGroupedByStage($missingFieldsByStage): array
{
    return $this->getMissingFieldsGroupedByStageForUI($missingFieldsByStage);
}
}