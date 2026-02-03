<?php

namespace App\Http\Requests\Listing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $projectId = $this->route('project') ? $this->route('project') : null;

        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'developer_id' => ['nullable', 'exists:developers,id'],
             'area_id' => 'required|exists:areas,id',
            // 'from_price' => ['nullable', 'numeric', 'min:0'],
            // 'to_price' => ['nullable', 'numeric', 'min:0', 'gte:from_price'],
            // 'from_sqft' => ['nullable', 'numeric', 'min:0'],
            // 'to_sqft' => ['nullable', 'numeric', 'min:0', 'gte:from_sqft'],
            'status' => ['nullable', Rule::in(['Under Construction', 'Ready'])],
            'about' => ['nullable', 'string'],
            'features' => ['nullable', 'array'],
            'features.*' => ['exists:features,id'],
            
            'main_image' => [
                $this->isMethod('POST') ? 'nullable' : 'nullable',
                'image',
                'mimes:jpeg,png,jpg,gif,webp',
                'max:5120' // 5MB
            ],
            
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
            
            // Flag to keep current image
            'keep_current_image' => ['nullable', 'boolean']
        ];

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Project title is required',
            'developer_id.required' => 'Please select a developer',
            'developer_id.exists' => 'Selected developer does not exist',
            'from_price.numeric' => 'From price must be a number',
            'to_price.numeric' => 'To price must be a number',
            'to_price.gte' => 'To price must be greater than or equal to from price',
            'status.required' => 'Please select a status',
            'status.in' => 'Invalid status value',
            'main_image.required' => 'Project image is required',
            'main_image.image' => 'The file must be an image',
            'main_image.mimes' => 'The image must be a file of type: jpeg, png, jpg, gif, webp',
            'main_image.max' => 'The image may not be greater than 5MB',
            'features.*.exists' => 'Selected feature does not exist',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        // Convert empty string to null for nullable fields
        $this->merge([
            'from_price' => $this->from_price ?: null,
            'to_price' => $this->to_price ?: null,
            'from_sqft' => $this->from_sqft ?: null,
            'to_sqft' => $this->to_sqft ?: null,
            'about' => $this->about ?: null,
            'features' => $this->features ? (is_array($this->features) ? $this->features : explode(',', $this->features)) : [],
            'keep_current_image' => $this->boolean('keep_current_image'),
        ]);
    }
}