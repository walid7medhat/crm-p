<?php

namespace App\Http\Requests\Listing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class FeatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        // For public routes (index, show) - always true
        // For protected routes - will be handled by middleware
        return true;
    }

    public function rules(): array
{
    $featureId = null;

    if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
        $feature = $this->route('feature');
        $featureId = $feature ? $feature->id : null;
    }

    $rules = [
        'name' => 
        [
            'required',
            'string','max:255',
             Rule::unique('features')->ignore($featureId)
        ],
        
        'avatar' =>$this->isMethod('PUT')? 'nullable|mimes:jpeg,png,jpg,gif':'required|mimes:jpeg,png,jpg,gif',
    ];

    return $rules;
}

    public function messages(): array
    {
        return [
            'name.required' => 'Name is required',
            
            'avatar.image' => 'Icon must be an image file',
            'avatar.mimes' => 'Icon must be jpeg, png, jpg, or gif',
            'avatar.max' => 'Icon size must be less than 2MB',
        ];
    }
}