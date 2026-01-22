<?php

namespace App\Http\Requests\Listing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class DeveloperRequest extends FormRequest
{
    public function authorize(): bool
    {
        // For public routes (index, show) - always true
        // For protected routes - will be handled by middleware
        return true;
    }

    public function rules(): array
{
    $developerId = null;

    if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
        $developer = $this->route('developer');
        $developerId = $developer ? $developer->id : null;
    }

    $rules = [
        'name' => 
        [
            'required',
            'string','max:255',
             Rule::unique('developers')->ignore($developerId)
        ],
        'email' => [
            'nullable',
            'email',
            Rule::unique('developers')->ignore($developerId)
        ],
        'phone' => 'nullable|string|max:20',
        'avatar' =>$this->isMethod('PUT')? 'nullable|mimes:jpeg,png,jpg,gif':'required|mimes:jpeg,png,jpg,gif',
    ];

    return $rules;
}

    public function messages(): array
    {
        return [
            'name.required' => 'Name is required',
            'email.required' => 'Email is required',
            'email.email' => 'Please enter a valid email',
            'email.unique' => 'This email is already taken',
            'avatar.image' => 'Avatar must be an image file',
            'avatar.mimes' => 'Avatar must be jpeg, png, jpg, or gif',
            'avatar.max' => 'Avatar size must be less than 2MB',
        ];
    }
}