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
            'unit_no' => 'sometimes|string',
            'property_type_id' => 'nullable|exists:property_types,id',
            'bedrooms' => 'nullable|string',
            'unit_size' => 'nullable|numeric',
            'project_id' => 'nullable|exists:projects,id',
            'area_id' => 'nullable|exists:areas,id',
            'listing_id' => 'nullable|exists:listings,id',
            'developer_id' => 'nullable|exists:developers,id',
            'deal_total_amount' => 'nullable|numeric',
            'responsible_person_id' => 'nullable|exists:users,id',
            'source' => 'nullable|string',
            'deal_name' => 'nullable|string',
        ];
    }
}