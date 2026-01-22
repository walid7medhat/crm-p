<?php

namespace App\Http\Requests\Listing;

use Illuminate\Foundation\Http\FormRequest;

class PropertyTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_id' => 'required|exists:property_types,id',
            'name' => 'required|string|max:255|unique:property_types,name,' . $this->route('property_type')?->id,
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The property type name is required.',
            'name.unique' => 'This property type name already exists.',
        ];
    }
}