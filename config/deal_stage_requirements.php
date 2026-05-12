<?php

return [
    'party_field_map' => [
        'dob' => 'date_of_birth',
    ],

    'requirements' => [
        'primary' => [
            // ===================== EOI STAGE (order 2) =====================
            2 => [
                'fields' => ['source', 'deal_name'],
                'parties' => [
                    'buyer' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'language'],
                ],
                'documents' => [
                    'buyer' => ['passport', 'national_id'],
                ],
                'requires_properties' => true,
                'properties' => [
                    'area_id' => true,           // Address
                    'property_type_id' => true,  // Type
                    'bedrooms' => true,          // Bedrooms
                    'budget_from' => true,       // Budget From (لكل Property)
                    'budget_to' => true,         // Budget To (لكل Property)
                     'developer_id' => true,
                     'developer_name'=>true,
                    'developer_phone'=>true,
                ],
                 'property_documents' => ['eoi'],
            ],
            
            // ===================== BOOKING STAGE (order 3) =====================
            3 => [
                'fields' => ['source', 'deal_name'],
                'parties' => [
                    'buyer' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'language'],
                ],
                'documents' => [],
                'requires_properties' => true,
                'properties' => [
                    'area_id' => true,
                    'property_type_id' => true,
                    'bedrooms' => true,
                    'unit_no' => true,
                    'unit_size' => false,
                    'developer_id' => true,
                     'developer_name'=>true,
                    'developer_phone'=>true,
                    'purchase_price' => true,     
               
                ],
                 'property_documents' => ['payment_proof', 'booking'],
            ],
            
            // ===================== SPA STAGE (order 4) =====================
            4 => [
                'fields' => ['source', 'deal_name'],
                'parties' => [
                    'buyer' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'language'],
                ],
                'documents' => [
                    'buyer' => ['national_id', 'passport', 'kyc'],
                ],
                'requires_properties' => true,
                'properties' => [
                    'area_id' => true,
                    'property_type_id' => true,
                    'bedrooms' => true,
                    'unit_no' => true,
                    'unit_size' => true,
                    'developer_id' => true,
                     'developer_name'=>true,
                    'developer_phone'=>true,
                    'purchase_price' => true,
             
                ],
                'property_documents' => ['payment_proof', 'spa','booking','eoi'],
            ],
            
            // ===================== WON STAGE (order 5) =====================
            5 => [
                'fields' => ['source', 'deal_name', 'deal_total_amount', 'deal_commission'],
                'parties' => [
                    'buyer' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'language'],
                ],
                'documents' => [
                    'buyer' => ['national_id', 'passport', 'kyc'],
                ],
                'requires_properties' => true,
                'properties' => [
                    'area_id' => true,
                    'property_type_id' => true,
                    'bedrooms' => true,
                    'unit_no' => true,
                    'unit_size' => true,
                    'developer_id' => true,
                     'developer_name'=>true,
                    'developer_phone'=>true,
                    'purchase_price' => true,
           
                ],
                'property_documents' => ['payment_proof', 'spa','eoi','booking'],
            ],
            
            // ===================== LOST STAGE (order 6) =====================
            6 => [
                'fields' => ['lost_reason'],
                'parties' => [],
                'documents' => [],
                'requires_properties' => false,
            ],
        ],

        'secondary' => [
            2 => [
                'fields' => ['source', 'deal_name'],
                'parties' => [
                    'buyer' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'language'],
                    'seller' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status',  'language'],
                ],
                'documents' => [
                    'buyer' => ['national_id', 'passport', 'kyc'],
                    'seller' => ['national_id', 'passport'],
                ],
                'requires_properties' => true,
                'properties' => [
                    'area_id' => true,
                    'property_type_id' => true,
                    'unit_no' => true,
                ],
            ],
            3 => [
                'fields' => ['source', 'deal_name'],
                'parties' => [
                    'buyer' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'language'],
                    'seller' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'language'],
                ],
                'documents' => [
                    'buyer' => ['national_id', 'passport', 'kyc'],
                    'seller' => ['national_id', 'passport'],
                ],
                'requires_properties' => true,
                'properties' => [
                    'area_id' => true,
                    'property_type_id' => true,
                    'unit_no' => true,
                    'bedrooms' => true,
                    'unit_size' => true,
                    'purchase_price' => true,
                    'developer_name'=>true,
                    'developer_phone'=>true,
                ],
                'property_documents' => ['payment_proof'],
            ],
            4 => [
                'fields' => ['source', 'deal_name'],
                'parties' => [
                    'buyer' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'language'],
                    'seller' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'language'],
                ],
                'documents' => [
                    'buyer' => ['national_id', 'passport', 'kyc'],
                    'seller' => ['national_id', 'passport', 'title_deed'],
                ],
                'requires_properties' => true,
                'properties' => [
                    'area_id' => true,
                    'property_type_id' => true,
                    'unit_no' => true,
                    'bedrooms' => true,
                    'unit_size' => true,
                    'purchase_price' => true,
                       'developer_name'=>true,
                    'developer_phone'=>true,
                ],
                'property_documents' => ['payment_proof', 'spa'],
            ],
            5 => [
                'fields' => ['source', 'deal_name', 'deal_total_amount', 'deal_commission'],
                'parties' => [
                    'buyer' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'language'],
                    'seller' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'language'],
                ],
                'documents' => [
                    'buyer' => ['national_id', 'passport', 'kyc', 'payment_proof'],
                    'seller' => ['national_id', 'passport', 'title_deed', 'noc'],
                ],
                'requires_properties' => true,
                'properties' => [
                    'area_id' => true,
                    'property_type_id' => true,
                    'unit_no' => true,
                    'bedrooms' => true,
                    'unit_size' => true,
                    'purchase_price' => true,
                    'developer_name'=>true,
                    'developer_phone'=>true,
                ],
                'property_documents' => ['payment_proof', 'spa'],
            ],
            8 => [
                'fields' => ['lost_reason'],
                'parties' => [],
                'documents' => [],
                'requires_properties' => false,
            ],
        ],

        'rental' => [
            2 => [
                'fields' => ['source', 'deal_name'],
                'parties' => [
                    'tenant' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'residency_status', 'language'],
                    'landlord' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'language'],
                ],
                'documents' => [
                    'tenant' => ['passport'],
                    'landlord' => ['passport', 'national_id'],
                ],
                'requires_properties' => true,
                'properties' => [
                    'area_id' => true,
                    'property_type_id' => true,
                    'unit_no' => true,
                    'budget_from' => true,
                    'budget_to' => true,
                ],
            ],
            3 => [
                'fields' => ['source', 'deal_name'],
                'parties' => [
                    'tenant' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'residency_status', 'language'],
                    'landlord' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'language'],
                ],
                'documents' => [
                    'tenant' => ['passport', 'kyc'],
                    'landlord' => ['passport', 'national_id', 'title_deed'],
                ],
                'requires_properties' => true,
                'properties' => [
                    'area_id' => true,
                    'property_type_id' => true,
                    'unit_no' => true,
                    'bedrooms' => true,
                    'purchase_price' => true,
                ],
            ],
            4 => [
                'fields' => ['source', 'deal_name'],
                'parties' => [
                    'tenant' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'residency_status', 'language'],
                    'landlord' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'language'],
                ],
                'documents' => [
                    'tenant' => ['passport', 'kyc', 'ejari'],
                    'landlord' => ['passport', 'national_id', 'title_deed'],
                ],
                'requires_properties' => true,
                'properties' => [
                    'area_id' => true,
                    'property_type_id' => true,
                    'unit_no' => true,
                    'bedrooms' => true,
                    'purchase_price' => true,
                ],
                'property_documents' => ['contract', 'ejari'],
            ],
            5 => [
                'fields' => ['source', 'deal_name', 'deal_total_amount', 'deal_commission'],
                'parties' => [
                    'tenant' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'residency_status', 'language'],
                    'landlord' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'language'],
                ],
                'documents' => [
                    'tenant' => ['passport', 'kyc', 'ejari', 'tenancy_contract', 'move_in_form', 'payment_proof'],
                    'landlord' => ['passport', 'national_id', 'title_deed'],
                ],
                'requires_properties' => true,
                'properties' => [
                    'area_id' => true,
                    'property_type_id' => true,
                    'unit_no' => true,
                    'bedrooms' => true,
                    'unit_size' => false,
                    'purchase_price' => true,
                ],
                'property_documents' => ['contract', 'ejari'],
            ],
            8 => [
                'fields' => ['lost_reason'],
                'parties' => [],
                'documents' => [],
                'requires_properties' => false,
            ],
        ],
    ],
];