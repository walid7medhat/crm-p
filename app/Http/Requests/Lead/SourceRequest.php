<?php

namespace App\Http\Requests\Lead;

use Illuminate\Foundation\Http\FormRequest;

class SourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:sources,name,' . $this->route('sources')?->id,
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The source name is required.',
            'name.unique' => 'This source name already exists.',
        ];
    }
}