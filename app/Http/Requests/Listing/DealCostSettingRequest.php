<?php
// app/Http/Requests/DealCostSettingRequest.php

namespace App\Http\Requests\Listing;

use Illuminate\Foundation\Http\FormRequest;

class DealCostSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dari_admin_fee' => 'nullable|numeric|min:0|max:999999999.99',
            'adgm_admin_fee' => 'nullable|numeric|min:0|max:999999999.99',
        ];
    }

    public function messages(): array
    {
        return [
            'dari_admin_fee.numeric' => 'Dari Admin Fee must be a number',
            'dari_admin_fee.min' => 'Dari Admin Fee cannot be negative',
            'adgm_admin_fee.numeric' => 'ADGM Admin Fee must be a number',
            'adgm_admin_fee.min' => 'ADGM Admin Fee cannot be negative',
        ];
    }

    public function attributes(): array
    {
        return [
            'dari_admin_fee' => 'Dari Admin Fee',
            'adgm_admin_fee' => 'ADGM Admin Fee',
        ];
    }
}