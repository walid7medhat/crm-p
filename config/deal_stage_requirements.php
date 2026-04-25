<?php

return [
    'party_field_map' => [
        'dob' => 'date_of_birth',
    ],

    'requirements' => [
        'primary' => [
            2 => [
                'fields' => ['source', 'deal_name', 'responsible_person_id'],
                'parties' => [
                    'buyer' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'city', 'language'],
                ],
                'documents' => [
                    'buyer' => ['passport','national_id'],
                ],
            ],
            3 => [
                'fields' => ['source', 'deal_name', 'unit_no', 'property_type_id',  'responsible_person_id', 'bedrooms', 'area_id', 'unit_size','developer_id', 'deal_total_amount', 'deal_commission'],
                'parties' => [
                    'buyer' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'city', 'language', 'deal_total_amount', 'deal_commission'],
                ],
                'documents' => [
                    'buyer' => ['national_id', 'passport','payment_proof'],
                ],
            ],
            4 => [
                'fields' => ['source', 'deal_name', 'unit_no', 'property_type_id',  'responsible_person_id', 'bedrooms', 'area_id', 'unit_size','developer_id', 'deal_total_amount', 'deal_commission'],
                'parties' => [
                    'buyer' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'city', 'language'],
                ],
                'documents' => [
                    'buyer' => ['payment_proof', 'national_id', 'passport', 'kyc'],
                ],
            ],
            5 => [
                // , 'agent_share', 'company_share'
                'fields' => ['source', 'deal_name', 'unit_no', 'property_type_id',  'responsible_person_id', 'bedrooms', 'area_id','developer_id', 'unit_size', 'deal_total_amount', 'deal_commission'],
                'parties' => [
                    'buyer' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'city', 'language', 'amount'],
                ],
                'documents' => [
                    'buyer' => ['payment_proof', 'national_id', 'passport', 'kyc'],
                ],
            ],
            6 => [
                'fields' => ['lost_reason'],
                'parties' => [],
                'documents' => [],
            ],
        ],

        'secondary' => [
            2 => [
                'fields' => ['source', 'deal_name', 'unit_no', 'property_type_id', 'subcommunity_id'],
                'parties' => [
                    'buyer' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'city', 'language'],
                    'seller' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'city', 'language'],
                ],
                'documents' => [
                    'buyer' => ['national_id', 'passport', 'kyc'],
                    'seller' => ['national_id', 'passport'],
                ],
            ],
            3 => [
                'fields' => ['source', 'deal_name', 'unit_no', 'property_type_id',  'bedrooms', 'area_id','developer_id'],
                'parties' => [
                    'buyer' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'city', 'language'],
                    'seller' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'city', 'language'],
                ],
                'documents' => [
                    'buyer' => ['national_id', 'passport', 'kyc'],
                    'seller' => ['national_id', 'passport'],
                ],
            ],
            4 => [
                'fields' => ['source', 'deal_name', 'unit_no', 'property_type_id',  'bedrooms', 'area_id', 'unit_size','developer_id'],
                'parties' => [
                    'buyer' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'city', 'language'],
                    'seller' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'city', 'language'],
                ],
                'documents' => [
                    'buyer' => ['national_id', 'passport', 'kyc'],
                    'seller' => ['national_id', 'passport', 'title_deed'],
                ],
            ],
            5 => [
                'fields' => ['source', 'deal_name', 'unit_no', 'property_type_id', 'subcommunity_id'],
                'parties' => [
                    'buyer' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'city', 'language'],
                    'seller' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'city', 'language'],
                ],
                'documents' => [
                    'buyer' => ['national_id', 'passport', 'kyc', 'payment_proof'],
                    'seller' => ['national_id', 'passport', 'title_deed', 'noc'],
                ],
            ],
            6 => [
                // , 'agent_share', 'company_share'
                'fields' => ['source', 'deal_name', 'unit_no', 'property_type_id',  'deal_total_amount', 'deal_commission'],
                'parties' => [
                    'buyer' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'city', 'language', 'amount'],
                    'seller' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'city', 'language'],
                ],
                'documents' => [
                    'buyer' => ['national_id', 'passport', 'kyc', 'payment_proof'],
                    'seller' => ['national_id', 'passport', 'title_deed', 'noc'],
                ],
            ],
            8 => [
                'fields' => ['lost_reason'],
                'parties' => [],
                'documents' => [],
            ],
        ],

        'rental' => [
            2 => [
                'fields' => ['source', 'deal_name', 'unit_no', 'property_type_id',  'responsible_person_id'],
                'parties' => [
                    'tenant' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'residency_status', 'city', 'language'],
                    'landlord' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'city', 'language'],
                ],
                'documents' => [
                    'tenant' => ['passport', 'visa'],
                    'landlord' => ['passport', 'national_id'],
                ],
            ],
            3 => [
                'fields' => ['source', 'deal_name', 'unit_no', 'property_type_id', 'subcommunity_id'],
                'parties' => [
                    'tenant' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'residency_status', 'city', 'language'],
                    'landlord' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'city', 'language'],
                ],
                'documents' => [
                    'tenant' => ['passport', 'visa', 'kyc'],
                    'landlord' => ['passport', 'national_id', 'title_deed'],
                ],
            ],
            4 => [
                'fields' => ['source', 'deal_name', 'unit_no', 'property_type_id', 'subcommunity_id'],
                'parties' => [
                    'tenant' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'residency_status', 'city', 'language'],
                    'landlord' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'city', 'language'],
                ],
                'documents' => [
                    'tenant' => ['passport', 'visa', 'kyc', 'ejari'],
                    'landlord' => ['passport', 'national_id', 'title_deed'],
                ],
            ],
            5 => [
                
                'fields' => ['source', 'deal_name', 'unit_no', 'property_type_id',  'responsible_person_id', 'bedrooms', 'area_id', 'unit_size','developer_id'],
                'parties' => [
                    'tenant' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'residency_status', 'city', 'language'],
                    'landlord' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'city', 'language'],
                ],
                'documents' => [
                    'tenant' => ['passport', 'visa', 'kyc', 'ejari', 'tenancy_contract', 'move_in_form'],
                    'landlord' => ['passport', 'national_id', 'title_deed'],
                ],
            ],
            6 => [
                // , 'agent_share', 'company_share'
                'fields' => ['source', 'deal_name', 'unit_no', 'property_type_id',  'deal_total_amount', 'deal_commission'],
                'parties' => [
                    'tenant' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'residency_status', 'city', 'language', 'amount'],
                    'landlord' => ['first_name', 'last_name', 'phone', 'email', 'nationality', 'dob', 'residency_status', 'city', 'language'],
                ],
                'documents' => [
                    'tenant' => ['passport', 'visa', 'kyc', 'ejari', 'tenancy_contract', 'move_in_form', 'payment_proof'],
                    'landlord' => ['passport', 'national_id', 'title_deed'],
                ],
            ],
            8 => [
                'fields' => ['lost_reason'],
                'parties' => [],
                'documents' => [],
            ],
        ],
    ],
];

