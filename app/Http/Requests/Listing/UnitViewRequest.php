<?php

namespace App\Http\Requests\Listing;

use Illuminate\Foundation\Http\FormRequest;

class UnitViewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:unit_views,name,' . $this->route('unit_view')?->id,
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The Unit View name is required.',
            'name.unique' => 'This Unit View name already exists.',
        ];
    }
}