<?php

namespace App\Http\Requests\Listing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeveloperRequest extends FormRequest
{
    public function authorize(): bool
    {
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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('developers')->ignore($developerId)
            ],
            'email' => [
                'nullable',
                'email',
                Rule::unique('developers')->ignore($developerId)
            ],
            'phone' => 'nullable|string|max:20',
            'avatar' => $this->isMethod('PUT') ? 'nullable|mimes:jpeg,png,jpg,gif' : 'required|mimes:jpeg,png,jpg,gif',
            // إضافة الحقول الجديدة
            'noc_fees_ready' => 'nullable|numeric|min:0|max:999999999.99',
            'noc_fees_off_plan' => 'nullable|numeric|min:0|max:999999999.99',
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
            // رسائل للحقول الجديدة
            'noc_fees_ready.numeric' => 'NOC fees for ready must be a number',
            'noc_fees_ready.min' => 'NOC fees for ready cannot be negative',
            'noc_fees_off_plan.numeric' => 'NOC fees for off-plan must be a number',
            'noc_fees_off_plan.min' => 'NOC fees for off-plan cannot be negative',
        ];
    }
}