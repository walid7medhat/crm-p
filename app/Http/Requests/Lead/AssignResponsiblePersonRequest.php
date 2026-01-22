<?php

namespace App\Http\Requests\Lead;

use Illuminate\Foundation\Http\FormRequest;

class AssignResponsiblePersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'responsible_person_id' => 'required|exists:users,id'
        ];
    }

    public function messages(): array
    {
        return [
            'responsible_person_id.required' => 'Responsible person is required',
            'responsible_person_id.exists' => 'The selected responsible person does not exist',
        ];
    }
}