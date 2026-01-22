<?php

namespace App\Http\Requests\Stage;

use Illuminate\Foundation\Http\FormRequest;

class StageReorderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stages' => 'required|array|min:1',
            'stages.*.id' => 'required|exists:stages,id',
            'stages.*.order' => 'required|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'stages.required' => 'Stages array is required',
            'stages.*.id.exists' => 'Invalid stage ID',
            'stages.*.order.required' => 'Stage order is required',
        ];
    }
}