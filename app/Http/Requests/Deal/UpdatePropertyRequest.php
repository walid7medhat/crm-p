<?php

namespace App\Http\Requests\Deal;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePropertyRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'unit_no' => 'nullable|string',
            'property_type_id' => 'nullable|exists:property_types,id',
            'bedrooms' => 'nullable|string',
            'unit_size' => 'nullable|numeric',
            'area_id' => 'nullable|exists:areas,id',
            'developer_id' => 'nullable|exists:developers,id',
            'developer_name' => 'nullable|string|max:255',
            'developer_phone' => 'nullable|string|max:255',
            'budget_from' => 'nullable|numeric|min:0',
            'budget_to' => 'nullable|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'commission' => 'nullable|numeric|min:0|max:100',
            'payment_proof' => 'nullable|array',
            'payment_proof.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'spa_document' => 'nullable|array',
            'spa_document.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ];
    }
}