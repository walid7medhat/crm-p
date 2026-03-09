<?php

namespace App\Http\Requests\Deal;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePartialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source' => 'nullable|string',
            'deal_name' => 'nullable|string',
            'unit_no' => 'nullable|string',
            'property_type_id' => 'nullable|integer',
            'subcommunity_id' => 'nullable|integer',
            'area_id' => 'nullable|integer',
            'responsible_person_id' => 'nullable|integer|exists:users,id',
            'bedrooms' => 'nullable|string',
            'unit_size' => 'nullable|numeric',
            'deal_total_amount' => 'nullable|numeric',
            'deal_commission' => 'nullable|numeric',
            'agent_share' => 'nullable|numeric',
            'company_share' => 'nullable|numeric',
            'currency' => 'nullable|string',
            'buyer_first_name' => 'nullable|string',
            'buyer_last_name' => 'nullable|string',
            'buyer_phone' => 'nullable|string',
            'buyer_email' => 'nullable|email',
            'buyer_nationality' => 'nullable|string',
            'buyer_dob' => 'nullable|date',
            'buyer_residency_status' => 'nullable|string',
            'buyer_city' => 'nullable|string',
            'buyer_country' => 'nullable|string',
            'buyer_language' => 'nullable|string',
            'buyer_amount' => 'nullable|numeric',
            'seller_first_name' => 'nullable|string',
            'seller_last_name' => 'nullable|string',
            'seller_phone' => 'nullable|string',
            'seller_email' => 'nullable|email',
            'seller_nationality' => 'nullable|string',
            'seller_dob' => 'nullable|date',
            'seller_residency_status' => 'nullable|string',
            'seller_city' => 'nullable|string',
            'seller_country' => 'nullable|string',
            'seller_language' => 'nullable|string',
            'tenant_first_name' => 'nullable|string',
            'tenant_last_name' => 'nullable|string',
            'tenant_phone' => 'nullable|string',
            'tenant_email' => 'nullable|email',
            'tenant_nationality' => 'nullable|string',
            'tenant_dob' => 'nullable|date',
            'tenant_residency_status' => 'nullable|string',
            'tenant_city' => 'nullable|string',
            'tenant_country' => 'nullable|string',
            'tenant_language' => 'nullable|string',
            'tenant_amount' => 'nullable|numeric',
            'landlord_first_name' => 'nullable|string',
            'landlord_last_name' => 'nullable|string',
            'landlord_phone' => 'nullable|string',
            'landlord_email' => 'nullable|email',
            'landlord_nationality' => 'nullable|string',
            'landlord_dob' => 'nullable|date',
            'landlord_residency_status' => 'nullable|string',
            'landlord_city' => 'nullable|string',
            'landlord_country' => 'nullable|string',
            'landlord_language' => 'nullable|string',
        ];
    }
}
