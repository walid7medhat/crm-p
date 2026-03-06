<?php

namespace App\Http\Requests\Deal;

use Illuminate\Foundation\Http\FormRequest;

class ConvertLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'deal_type' => 'required|in:primary,secondary,rental',
            'unit_no' => 'required|string',
            'source' => 'required|string',
            'deal_name' => 'required|string',
            'property_type_id' => 'nullable|exists:property_types,id',
            'bedrooms' => 'nullable|string',
            'unit_size' => 'nullable|numeric',
            'project_id' => 'nullable|exists:projects,id',
            'area_id' => 'nullable|exists:areas,id',
            'developer_id' => 'nullable|exists:developers,id',
            'deal_total_amount' => 'nullable|numeric',
            'responsible_person_id' => 'nullable|exists:users,id',
        ];

        if ($this->deal_type === 'rental') {
            $rules['client_name'] = 'required|string';
            $rules['client_phone'] = 'required|string';
            $rules['client_email'] = 'required|email';
            $rules['tenant_first_name'] = 'required|string';
            $rules['tenant_last_name'] = 'required|string';
            $rules['landlord_first_name'] = 'required|string';
            $rules['landlord_last_name'] = 'required|string';
        }

        if ($this->deal_type === 'primary') {
            $rules['buyer_first_name'] = 'required|string';
            $rules['buyer_last_name'] = 'required|string';
            $rules['buyer_phone'] = 'required|string';
            $rules['buyer_email'] = 'required|email';
            $rules['amount'] = 'required|numeric';
        }

        if ($this->deal_type === 'secondary') {
            $rules['buyer_first_name'] = 'required|string';
            $rules['buyer_last_name'] = 'required|string';
            $rules['buyer_phone'] = 'required|string';
            $rules['buyer_email'] = 'required|email';
            $rules['seller_first_name'] = 'required|string';
            $rules['seller_last_name'] = 'required|string';
            $rules['seller_phone'] = 'required|string';
            $rules['seller_email'] = 'required|email';
            $rules['amount'] = 'required|numeric';
        }

        return $rules;
    }
}