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
            'status' => ['nullable', Rule::in(['Under Construction', 'Ready'])],
            'about' => ['nullable', 'string'],
            'features' => ['nullable', 'array'],
            'features.*' => ['exists:features,id'],
            
            'main_image' => [
                $this->isMethod('POST') ? 'nullable' : 'nullable',
                'image',
                'mimes:jpeg,png,jpg,gif,webp',
                'max:5120'
            ],
            
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
            
            'keep_current_image' => ['nullable', 'boolean'],
        ];
        
        // Remove the dd() statement - it's breaking your validation
        // dd(request()->floor_plan_images);
        
        // FIX: Always add floor_plan_images validation, but make it conditional
        $rules['floor_plan_images'] = 'nullable|array';
        
        // If floor_plan_images exists as array input, validate its structure
        if ($this->has('floor_plan_images') && is_array($this->floor_plan_images)) {
            foreach (array_keys($this->floor_plan_images) as $index) {
                $rules["floor_plan_images.{$index}.file"] = 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120';
                $rules["floor_plan_images.{$index}.name"] = 'nullable|string|max:100';
            }
        }
        
        // Alternative approach: Check if any files were uploaded with floor_plan_images.*.file pattern
        foreach ($this->allFiles() as $key => $file) {
            if (preg_match('/^floor_plan_images\.\d+\.file$/', $key)) {
                $index = explode('.', $key)[1];
                $rules["floor_plan_images.{$index}.file"] = 'required|image|mimes:jpeg,png,jpg,gif|max:5120';
                $rules["floor_plan_images.{$index}.name"] = 'nullable|string|max:100';
            }
        }

        // Handling for floor plan names updates
        if ($this->has('floor_plan_names')) {
            $rules['floor_plan_names'] = 'array';
            foreach (array_keys($this->input('floor_plan_names', [])) as $key) {
                $rules["floor_plan_names.{$key}"] = 'nullable|string|max:100';
            }
        }
        
        // Add validation for delete_floor_plan_images
        if ($this->has('delete_floor_plan_images')) {
            $rules['delete_floor_plan_images'] = 'array';
            $rules['delete_floor_plan_images.*'] = 'integer|exists:floor_plan_images,id';
        }

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
            'floor_plan_images.*.file.required' => 'Floor plan image file is required',
            'floor_plan_images.*.file.image' => 'Floor plan must be an image',
            'floor_plan_images.*.file.mimes' => 'Floor plan must be a file of type: jpeg, png, jpg, gif',
            'floor_plan_images.*.file.max' => 'Floor plan image may not be greater than 5MB',
            'floor_plan_images.*.name.max' => 'Floor plan name may not be greater than 100 characters',
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
        
        // Ensure floor_plan_images is always an array if present
        if ($this->has('floor_plan_images')) {
            $this->merge([
                'floor_plan_images' => is_array($this->floor_plan_images) ? $this->floor_plan_images : [],
            ]);
        }
    }
}