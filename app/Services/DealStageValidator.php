<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\Stage;
use Illuminate\Support\Facades\Log;

class DealStageValidator
{
    protected array $stageRequirements = [
        'primary' => [
            1 => [
                'fields' => [
                    'source', 'deal_name', 'unit_no', 'property_type_id', 
                    'subcommunity_id', 'responsible_person_id'
                ],
                'parties' => [
                    'buyer' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'city', 'language']
                ],
                'documents' => [
                    'buyer' => ['passport', 'kyc']
                ]
            ],
            2 => [
                'fields' => [
                    'source', 'deal_name', 'unit_no', 'property_type_id', 
                    'subcommunity_id', 'responsible_person_id', 'bedrooms', 'area_id'
                ],
                'parties' => [
                    'buyer' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'city', 'language']
                ],
                'documents' => [
                    'buyer' => ['national_id', 'passport', 'kyc']
                ]
            ],
            3 => [
                'fields' => [
                    'source', 'deal_name', 'unit_no', 'property_type_id', 
                    'subcommunity_id', 'responsible_person_id', 'bedrooms', 'area_id', 'unit_size'
                ],
                'parties' => [
                    'buyer' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'city', 'language']
                ],
                'documents' => [
                    'buyer' => ['payment_proof']
                ]
            ],
            4 => [
                'fields' => [
                    'source', 'deal_name', 'unit_no', 'property_type_id', 
                    'subcommunity_id', 'responsible_person_id', 'bedrooms', 'area_id', 
                    'unit_size', 'deal_total_amount', 'deal_commission', 'agent_share', 'company_share'
                ],
                'parties' => [
                    'buyer' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'city', 'language', 'amount']
                ]
            ]
        ],
        'secondary' => [
            1 => [
                'fields' => [
                    'source', 'deal_name', 'unit_no', 'property_type_id', 'subcommunity_id',
                    // 'responsible_person_id'
                ],
                'parties' => [
                    'buyer' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'city', 'language'],
                    'seller' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'city', 'language']
                ],
                'documents' => [
                    'buyer' => ['national_id', 'passport', 'kyc'],
                    'seller' => ['national_id', 'passport']
                ]
            ],
            2 => [
                'fields' => [
                    'source', 'deal_name', 'unit_no', 'property_type_id', 'subcommunity_id', 
                    // 'responsible_person_id',
                    'bedrooms', 'area_id'
                ],
                'parties' => [
                    'buyer' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'city', 'language'],
                    'seller' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'city', 'language']
                ],
                'documents' => [
                    'buyer' => ['national_id', 'passport', 'kyc'],
                    'seller' => ['national_id', 'passport']
                ]
            ],
            3 => [
                'fields' => [
                    'source', 'deal_name', 'unit_no', 'property_type_id', 'subcommunity_id', 
                    // 'responsible_person_id',
                    'bedrooms', 'area_id', 'unit_size'
                ],
                'parties' => [
                    'buyer' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'city', 'language'],
                    'seller' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'city', 'language']
                ],
                'documents' => [
                    'buyer' => ['national_id', 'passport', 'kyc'],
                    'seller' => ['national_id', 'passport', 'title_deed']
                ]
            ],
            4 => [
                'fields' => [
                    'source', 'deal_name', 'unit_no', 'property_type_id', 'subcommunity_id', 
                    // 'responsible_person_id', 'bedrooms', 'area_id', 'unit_size'
                ],
                'parties' => [
                    'buyer' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'city', 'language'],
                    'seller' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'city', 'language']
                ],
                'documents' => [
                    'buyer' => ['national_id', 'passport', 'kyc', 'payment_proof'],
                    'seller' => ['national_id', 'passport', 'title_deed', 'noc']
                ]
            ],
            5 => [
                'fields' => [
                    'source', 'deal_name', 'unit_no', 'property_type_id', 'subcommunity_id', 
                    // 'responsible_person_id', 'bedrooms', 'area_id', 'unit_size',
                    'deal_total_amount', 'deal_commission', 'agent_share', 'company_share'
                ],
                'parties' => [
                    'buyer' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'city', 'language', 'amount'],
                    'seller' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'city', 'language']
                ],
                'documents' => [
                    'buyer' => ['national_id', 'passport', 'kyc', 'payment_proof'],
                    'seller' => ['national_id', 'passport', 'title_deed', 'noc']
                ]
            ]
        ],
        'rental' => [
            1 => [
                'fields' => [
                    'source', 'deal_name', 'unit_no', 'property_type_id', 'subcommunity_id', 'responsible_person_id'
                ],
                'parties' => [
                    'tenant' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'residency_status', 'city', 'language'],
                    'landlord' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'city', 'language']
                ],
                'documents' => [
                    'tenant' => ['passport', 'visa'],
                    'landlord' => ['passport', 'national_id']
                ]
            ],
            2 => [
                'fields' => [
                    'source', 'deal_name', 'unit_no', 'property_type_id', 'subcommunity_id', 
                    // 'responsible_person_id', 'bedrooms', 'area_id'
                ],
                'parties' => [
                    'tenant' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'residency_status', 'city', 'language'],
                    'landlord' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'city', 'language']
                ],
                'documents' => [
                    'tenant' => ['passport', 'visa', 'kyc'],
                    'landlord' => ['passport', 'national_id', 'title_deed']
                ]
            ],
            3 => [
                'fields' => [
                    'source', 'deal_name', 'unit_no', 'property_type_id', 'subcommunity_id', 
                    // 'responsible_person_id', 'bedrooms', 'area_id', 'unit_size'
                ],
                'parties' => [
                    'tenant' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'residency_status', 'city', 'language'],
                    'landlord' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'city', 'language']
                ],
                'documents' => [
                    'tenant' => ['passport', 'visa', 'kyc', 'ejari'],
                    'landlord' => ['passport', 'national_id', 'title_deed']
                ]
            ],
            4 => [
                'fields' => [
                    'source', 'deal_name', 'unit_no', 'property_type_id', 'subcommunity_id', 
                    'responsible_person_id', 'bedrooms', 'area_id', 'unit_size'
                ],
                'parties' => [
                    'tenant' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'residency_status', 'city', 'language'],
                    'landlord' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'city', 'language']
                ],
                'documents' => [
                    'tenant' => ['passport', 'visa', 'kyc', 'ejari', 'tenancy_contract', 'move_in_form'],
                    'landlord' => ['passport', 'national_id', 'title_deed']
                ]
            ],
            5 => [
                'fields' => [
                    'source', 'deal_name', 'unit_no', 'property_type_id', 'subcommunity_id', 
                    // 'responsible_person_id', 'bedrooms', 'area_id', 'unit_size',
                    'deal_total_amount', 'deal_commission', 'agent_share', 'company_share'
                ],
                'parties' => [
                    'tenant' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'residency_status', 'city', 'language', 'amount'],
                    'landlord' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'city', 'language']
                ],
                'documents' => [
                    'tenant' => ['passport', 'visa', 'kyc', 'ejari', 'tenancy_contract', 'move_in_form', 'payment_proof'],
                    'landlord' => ['passport', 'national_id', 'title_deed']
                ]
            ]
        ]
    ];

    public function validate(Deal $deal, int $targetStageId, string $dealType): array
    {
        $currentStage = Stage::find($deal->stage_id);
        $targetStage = Stage::find($targetStageId);
        
        \Log::info('Starting validation', [
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
        $allStages = Stage::where('deal_type', $dealType)
            ->where('stage_type', 'deal')
            ->orderBy('order')
            ->get();

        $startOrder = $currentStage->order + 1;
        $endOrder = $targetStage->order;

        // تجهيز الـ parties للوصول السريع
        $parties = [];
        foreach ($deal->parties as $party) {
            $parties[$party->party_type] = $party;
        }
        
        \Log::info('Loaded parties', ['count' => count($parties), 'types' => array_keys($parties)]);

        for ($order = $startOrder; $order <= $endOrder; $order++) {
            $stage = $allStages->firstWhere('order', $order);
            if (!$stage) continue;

            // استخدم order كمفتاح وليس stage->id
            $requirements = $this->stageRequirements[$dealType][$order] ?? null;
            
            \Log::info("Checking stage order {$order}", [
                'has_requirements' => !is_null($requirements),
                'requirements' => $requirements
            ]);
            
            if (!$requirements) continue;

            // تحقق من الحقول الأساسية
            foreach ($requirements['fields'] ?? [] as $field) {
                $value = $deal->$field ?? null;
                \Log::info("Checking field {$field}", ['value' => $value, 'empty' => empty($value)]);
                
                if (empty($value)) {
                    $missingFields[] = $field;
                }
            }

            // تحقق من الـ parties
            foreach ($requirements['parties'] ?? [] as $partyType => $fields) {
                $party = $parties[$partyType] ?? null;
                
                \Log::info("Checking party {$partyType}", ['exists' => !is_null($party)]);

                if (!$party) {
                    $missingFields[] = "{$partyType}_party";
                    continue;
                }

                foreach ($fields as $field) {
                    $value = $party->$field ?? null;
                    \Log::info("Checking party field {$partyType}_{$field}", ['value' => $value, 'empty' => empty($value)]);
                    
                    if (empty($value)) {
                        $missingFields[] = "{$partyType}_{$field}";
                    }
                }
            }

            // تحقق من المستندات (اختياري)
            foreach ($requirements['documents'] ?? [] as $partyType => $docs) {
                $party = $parties[$partyType] ?? null;
                if (!$party) continue;

                foreach ($docs as $docType) {
                    $hasDoc = $deal->documents()
                        ->where('deal_party_id', $party->id)
                        ->where('document_type', $docType)
                        ->exists();

                    if (!$hasDoc) {
                        $missingFields[] = "{$partyType}_document_{$docType}";
                    }
                }
            }
        }

        $result = [
            'valid' => empty($missingFields),
            'missing_fields' => array_unique($missingFields)
        ];

        \Log::info('Validation result', $result);

        return $result;
    }

public function getRequiredFieldsForStage(Deal $deal, int $stageId, string $dealType): array
{
    $stage = Stage::find($stageId);
    if (!$stage) {
        \Log::warning('Stage not found', ['stage_id' => $stageId]);
        return [];
    }
    
    \Log::info('Getting required fields', [
        'stage_id' => $stageId,
        'stage_order' => $stage->order,
        'deal_type' => $dealType
    ]);
    
    // استخدم order كمفتاح
    $requirements = $this->stageRequirements[$dealType][$stage->order] ?? null;
    
    \Log::info('Requirements found', [
        'has_requirements' => !is_null($requirements),
        'requirements' => $requirements
    ]);
    
    if (!$requirements) return [];
    
    $requiredFields = [];
    
    // حقول الـ deal
    foreach ($requirements['fields'] ?? [] as $field) {
        $requiredFields[] = $field;
    }
    
    // حقول الـ parties
    foreach ($requirements['parties'] ?? [] as $partyType => $fields) {
        foreach ($fields as $field) {
            $requiredFields[] = "{$partyType}_{$field}";
        }
    }
    
    // حقول المستندات - تعديل هنا
    foreach ($requirements['documents'] ?? [] as $partyType => $docs) {
        // بدل ما نضيف كل document type، نضيف حقل واحد للمستندات
        $requiredFields[] = "{$partyType}_documents";
    }
    
    \Log::info('Required fields result', ['fields' => $requiredFields]);
    
    return $requiredFields;
}
}