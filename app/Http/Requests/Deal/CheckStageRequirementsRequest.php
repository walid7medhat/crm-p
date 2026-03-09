<?php

namespace App\Http\Requests\Deal;

use Illuminate\Foundation\Http\FormRequest;

class CheckStageRequirementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'deal_id' => 'required|exists:deals,id',
            'target_stage_id' => 'required|exists:stages,id',
            'deal_type' => 'required|in:primary,secondary,rental'
        ];
    }
}
