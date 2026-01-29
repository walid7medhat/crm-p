<?php

namespace App\Http\Requests\Stage;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Get the stage ID for update (if exists)
        $stageId = $this->route('stage')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('stages')->ignore($stageId)
            ],
            'order' => 'nullable|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Stage name is required',
            'name.unique' => 'Stage name already exists',
            'order.required' => 'Stage order is required',
            'order.min' => 'Order must be a positive number',
        ];
    }
}