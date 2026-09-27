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
                 'property_documents' => ['payment_proof', 'booking','eoi'],
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
            // ===================== SECURITY DEPOSIT STAGE (order 2) =====================
            2 => [
                'fields' => ['source', 'deal_name'],
                'parties' => [
                    'buyer' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'language'],
                    'seller' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status',  'language'],
                ],
                'documents' => [
                    'buyer' => ['national_id', 'passport', 'security_deposit'],
                    'seller' => ['national_id', 'passport', 'security_deposit'],
                ],
                'requires_properties' => true,
                'properties' => [
                    'area_id' => true,
                    'property_type_id' => true,
                    'unit_no' => true,
                ],
            ],
            // ===================== MOU STAGE (order 3) =====================
            3 => [
                'fields' => ['source', 'deal_name'],
                'parties' => [
                    'buyer' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'language'],
                    'seller' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'language'],
                ],
                'documents' => [
                    'buyer' => ['national_id', 'passport','security_deposit'],
                    'seller' => ['national_id', 'passport','security_deposit'],
                ],
                'requires_properties' => true,
                'properties' => [
                    'area_id' => true,
                    'property_type_id' => true,
                    'unit_no' => true,
                    'bedrooms' => true,
                    'unit_size' => true,
                    'purchase_price' => true,
                ],
                // MOU + title deed are REQUIRED starting at MOU stage.
                'property_documents' => ['mou', 'title_deed'],
            ],
            // ===================== NOC STAGE (order 4) =====================
            4 => [
                'fields' => ['source', 'deal_name'],
                'parties' => [
                    'buyer' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'language'],
                    'seller' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'language'],
                ],
                'documents' => [
                    'buyer' => ['national_id', 'passport','security_deposit'],
                    'seller' => ['national_id', 'passport','security_deposit'],
                ],
                'requires_properties' => true,
                'properties' => [
                    'area_id' => true,
                    'property_type_id' => true,
                    'unit_no' => true,
                    'bedrooms' => true,
                    'unit_size' => true,
                    'purchase_price' => true,
                ],
                // MOU + NOC + title deed are REQUIRED at NOC stage (cumulative).
                'property_documents' => ['mou', 'noc', 'title_deed'],
            ],
            5 => [
                'fields' => ['source', 'deal_name', 'deal_total_amount', 'deal_commission'],
                'parties' => [
                    'buyer' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'language'],
                    'seller' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'language'],
                ],
                'documents' => [
                    'buyer' => ['national_id', 'passport', 'payment_proof','security_deposit'],
                    'seller' => ['national_id', 'passport','security_deposit'],
                ],
                'requires_properties' => true,
                'properties' => [
                    'area_id' => true,
                    'property_type_id' => true,
                    'unit_no' => true,
                    'bedrooms' => true,
                    'unit_size' => true,
                    'purchase_price' => true,
                ],
                // MOU + NOC + title deed are REQUIRED at Won stage (cumulative).
                'property_documents' => ['mou', 'noc', 'title_deed'],
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
                    'landlord' => ['passport', 'national_id'],
                ],
                'requires_properties' => true,
                'properties' => [
                    'area_id' => true,
                    'property_type_id' => true,
                    'unit_no' => true,
                    'bedrooms' => true,
                    'rental_price' => true,
                ],
                // Title deed REQUIRED starting at MOU-equivalent stage.
                'property_documents' => ['title_deed'],
            ],
            // ===================== INTERNAL CONTRACT SIGNED (order 4) =====================
            // Contract document introduced here. Ejari isn't issued yet — it belongs to the
            // next stage ("Ejari / Tawtheq Issued") — so it must NOT be required this early.
            4 => [
                'fields' => ['source', 'deal_name'],
                'parties' => [
                    'tenant' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'residency_status', 'language'],
                    'landlord' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'language'],
                ],
                'documents' => [
                    'tenant' => ['passport', 'kyc'],
                    'landlord' => ['passport', 'national_id'],
                ],
                'requires_properties' => true,
                'properties' => [
                    'area_id' => true,
                    'property_type_id' => true,
                    'unit_no' => true,
                    'bedrooms' => true,
                    'rental_price' => true,
                ],
                'property_documents' => ['contract', 'title_deed'],
            ],
            // ===================== EJARI / TAWTHEQ ISSUED (order 5) =====================
            // NOT the Won stage — the real "Deal Won" stage for rental is order 7 (rental has
            // two extra stages after this one: "Tenant moved in" at 6, then "Deal Won" at 7).
            // Ejari document introduced here.
            5 => [
                'fields' => ['source', 'deal_name'],
                'parties' => [
                    'tenant' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'residency_status', 'language'],
                    'landlord' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'language'],
                ],
                'documents' => [
                    'tenant' => ['passport', 'kyc', 'ejari'],
                    'landlord' => ['passport', 'national_id'],
                ],
                'requires_properties' => true,
                'properties' => [
                    'area_id' => true,
                    'property_type_id' => true,
                    'unit_no' => true,
                    'bedrooms' => true,
                    'rental_price' => true,
                ],
                'property_documents' => ['contract', 'ejari', 'title_deed'],
            ],
            // ===================== TENANT MOVED IN (order 6) =====================
            6 => [
                'fields' => ['source', 'deal_name'],
                'parties' => [
                    'tenant' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'residency_status', 'language'],
                    'landlord' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'language'],
                ],
                'documents' => [
                    'tenant' => ['passport', 'kyc', 'ejari', 'tenancy_contract', 'move_in_form', 'payment_proof'],
                    'landlord' => ['passport', 'national_id'],
                ],
                'requires_properties' => true,
                'properties' => [
                    'area_id' => true,
                    'property_type_id' => true,
                    'unit_no' => true,
                    'bedrooms' => true,
                    'unit_size' => false,
                    'rental_price' => true,
                ],
                'property_documents' => ['contract', 'ejari', 'title_deed'],
            ],
            // ===================== DEAL WON (order 7) =====================
            7 => [
                'fields' => ['source', 'deal_name', 'deal_total_amount', 'deal_commission'],
                'parties' => [
                    'tenant' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'residency_status', 'language'],
                    'landlord' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'language'],
                ],
                'documents' => [
                    'tenant' => ['passport', 'kyc', 'ejari', 'tenancy_contract', 'move_in_form', 'payment_proof'],
                    'landlord' => ['passport', 'national_id'],
                ],
                'requires_properties' => true,
                'properties' => [
                    'area_id' => true,
                    'property_type_id' => true,
                    'unit_no' => true,
                    'bedrooms' => true,
                    'unit_size' => false,
                    'rental_price' => true,
                ],
                'property_documents' => ['contract', 'ejari', 'title_deed'],
            ],
            // ===================== DEAL LOST (order 8) =====================
            8 => [
                'fields' => ['lost_reason'],
                'parties' => [],
                'documents' => [],
                'requires_properties' => false,
            ],
        ],
    ],
];