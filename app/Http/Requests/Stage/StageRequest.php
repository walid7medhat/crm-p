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
                   Rule::unique('stages')->where(function ($query) {
                    return $query->where('stage_type', $this->stage_type)
                                 ->where('deal_type', $this->deal_type);
                })->ignore($stageId)
            ],
            'order' => 'nullable|integer|min:0',
            'color' => 'nullable|string|max:255',
            'stage_type'=>'nullable',
            'deal_type'=>'nullable',
            'auto_revert' => 'boolean',
            'revert_after_hours' => 'nullable|integer|min:1',
            'notify_before_minutes' => 'nullable|integer|min:1',
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