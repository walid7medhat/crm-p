<?php

namespace App\Http\Requests\Listing;

use Illuminate\Foundation\Http\FormRequest;

class LayoutTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:layout_types,name,' . $this->route('layout_type')?->id,
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The layout Type name is required.',
            'name.unique' => 'This layout Type name already exists.',
        ];
    }
}