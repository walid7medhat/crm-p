<?php

namespace App\Http\Requests\Deal;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stage_id' => 'sometimes|exists:stages,id',
            'status' => 'sometimes|in:draft,pending_approval,approved,completed,cancelled',
            'listing_id' => 'nullable|exists:listings,id',
            'deal_total_amount' => 'nullable|numeric',
            'responsible_person_id' => 'nullable|exists:users,id',
            'source' => 'nullable|string',
            'deal_name' => 'nullable|string',
            'deal_commission' => 'nullable|numeric',
            'agent_share' => 'nullable|numeric',
            'company_share' => 'nullable|numeric',
            'currency' => 'nullable|string|in:AED,USD,EUR,GBP,SAR,QAR,KWD,BHD,OMR,EGP',
            'lost_reason' => 'nullable|string',
            
            // Multi properties
            'properties' => 'sometimes|array',
            'properties.*.unit_no' => 'nullable|string',
            'properties.*.property_type_id' => 'nullable|exists:property_types,id',
            'properties.*.bedrooms' => 'nullable|string',
            'properties.*.unit_size' => 'nullable|numeric',
            'properties.*.area_id' => 'nullable|exists:areas,id',
            'properties.*.developer_id' => 'nullable|exists:developers,id',
            'properties.*.developer_name' => 'nullable|string|max:255',
            'properties.*.developer_phone' => 'nullable|string|max:255',
            'properties.*.budget_from' => 'nullable|numeric|min:0',
            'properties.*.budget_to' => 'nullable|numeric|min:0',
            'properties.*.purchase_price' => 'nullable|numeric|min:0',
            'properties.*.rental_price' => 'nullable|numeric|min:0',
        ];
    }
}