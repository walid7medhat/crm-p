<?php

namespace App\Http\Requests\Listing;

use Illuminate\Foundation\Http\FormRequest;

class OwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ownerId = $this->route('owner')?->id;
        return [
            'salutation' => 'nullable|string|max:20',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email' ,
            'phone_number' => 'required|string|max:20',
            'whatsapp_number' => 'nullable|string|max:20',
            'second_phone_number' => 'nullable|string|max:20',
            'nationality' => 'nullable|string|max:255',
            'residency_status' => 'nullable|in:resident,non_resident',
            'location_id' => 'nullable|exists:areas,id',
          // File validation rules
            'id_front' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120', // 5MB
            'id_back' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'visa_copy' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'passport_copy' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'additional_documents' => 'nullable|array',
            'additional_documents.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            
            'notes' => 'nullable|string',
            'avatar' =>'nullable|mimes:jpeg,png,jpg,gif',

        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'email.required' => 'Email is required.',
            'email.unique' => 'This email is already registered.',
            'phone_number.required' => 'Phone number is required.',
            'residency_status.required' => 'Residency status is required.',
            'residency_status.in' => 'Residency status must be either resident or non_resident.',
              'id_front.mimes' => 'ID front must be a JPG, PNG, or PDF file.',
            'id_front.max' => 'ID front file must not exceed 5MB.',
            'id_back.mimes' => 'ID back must be a JPG, PNG, or PDF file.',
            'id_back.max' => 'ID back file must not exceed 5MB.',
            'visa_copy.mimes' => 'Visa copy must be a JPG, PNG, or PDF file.',
            'visa_copy.max' => 'Visa copy file must not exceed 5MB.',
            'passport_copy.mimes' => 'Passport copy must be a JPG, PNG, or PDF file.',
            'passport_copy.max' => 'Passport copy file must not exceed 5MB.',
        ];
    }
}