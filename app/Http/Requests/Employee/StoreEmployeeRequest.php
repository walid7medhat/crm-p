<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
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
        return [
            // ========== USER BASIC INFO ==========
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8',
            'role_id' => 'required|exists:roles,id',
            'parent_id' => 'nullable|exists:users,id',
            'status' => 'nullable|in:active,in_active,blocked',
            
            // ========== EMPLOYEE BASIC INFO ==========
            'designation_id' => 'nullable|exists:designations,id',
            'joining_date' => 'nullable|date',
            'contract_end_date' => 'nullable|date|after:joining_date',
            'emirates_id_number' => 'nullable|string|unique:employee_profiles,emirates_id_number',
            'certificate_name' => 'nullable|string|max:255',
            'employment_status' => 'nullable|in:active,on_leave,terminated,suspended',
            
            // ========== BANK DETAILS ==========
            'bank_account_holder_name' => 'nullable|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:50',
            'branch_location' => 'nullable|string|max:255',
            'swift_code' => 'nullable|string|max:20',
            'iban_number' => 'nullable|string|max:34',
            
            // ========== INSURANCE DETAILS ==========
            'insurance_policy_type' => 'nullable|string|in:general,life,health,motor',
            'insurance_policy_number' => 'nullable|string|max:100',
            'insurance_provider' => 'nullable|string|max:255',
            'insurance_start_date' => 'nullable|date',
            'insurance_expiry_date' => 'nullable|date|after:insurance_start_date',
            
            // ========== EMISSARY ID ==========
            'emissary_id_number' => 'nullable|string|max:50',
            'emissary_id_pad' => 'nullable|string|max:50',
            
            // ========== NOTIFICATION ==========
            'notification_provider' => 'nullable|string|max:255',
            
            // ========== AVATAR ==========
            'avatar' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
            
            // ========== DOCUMENTS (Multiple files per type) ==========
            'documents' => 'nullable|array',
            
            'documents.emirates_id' => 'nullable|array',
            'documents.emirates_id.*' => 'file|mimes:jpg,jpeg,png,pdf|max:51200',
            
            'documents.labor_card' => 'nullable|array',
            'documents.labor_card.*' => 'file|mimes:jpg,jpeg,png,pdf|max:51200',
            
            'documents.passport' => 'nullable|array',
            'documents.passport.*' => 'file|mimes:jpg,jpeg,png,pdf|max:51200',
            
            'documents.visa' => 'nullable|array',
            'documents.visa.*' => 'file|mimes:jpg,jpeg,png,pdf|max:51200',
            
            'documents.attested_certificate' => 'nullable|array',
            'documents.attested_certificate.*' => 'file|mimes:jpg,jpeg,png,pdf|max:51200',
            
            'documents.other' => 'nullable|array',
            'documents.other.*' => 'file|mimes:jpg,jpeg,png,pdf|max:51200',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            // User messages
            'name.required' => 'Employee name is required',
            'email.required' => 'Email address is required',
            'email.email' => 'Please enter a valid email address',
            'email.unique' => 'This email is already registered',
            'password.required' => 'Password is required',
            'password.min' => 'Password must be at least 8 characters',
            'role_id.required' => 'Please select a role for the employee',
            'role_id.exists' => 'Selected role is invalid',
            
            // Employee messages
            'designation_id.exists' => 'Selected designation is invalid',
            'contract_end_date.after' => 'Contract end date must be after joining date',
            'emirates_id_number.unique' => 'This Emirates ID is already registered',
            'employment_status.in' => 'Employment status must be active, on_leave, terminated, or suspended',
            
            // Insurance messages
            'insurance_policy_type.in' => 'Insurance policy type must be general, life, health, or motor',
            'insurance_expiry_date.after' => 'Insurance expiry date must be after start date',
            
            // Document messages
            'documents.*.*.file' => 'Uploaded item must be a valid file',
            'documents.*.*.mimes' => 'Documents must be of type: jpg, jpeg, png, pdf',
            'documents.*.*.max' => 'Document size must not exceed 50MB',
            
            // Avatar messages
            'avatar.mimes' => 'Avatar must be of type: jpg, jpeg, png',
            'avatar.max' => 'Avatar size must not exceed 2MB',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Clean phone number (remove spaces and special chars)
        if ($this->has('phone')) {
            $this->merge([
                'phone' => preg_replace('/[^0-9+]/', '', $this->phone),
            ]);
        }
        
        // Convert empty strings to null
        $fields = [
            'parent_id', 'designation_id', 'emirates_id_number', 'certificate_name',
            'bank_account_holder_name', 'bank_name', 'bank_account_number',
            'branch_location', 'swift_code', 'iban_number', 'insurance_policy_number',
            'insurance_provider', 'emissary_id_number', 'emissary_id_pad',
            'notification_provider'
        ];
        
        foreach ($fields as $field) {
            if ($this->has($field) && empty($this->$field)) {
                $this->merge([$field => null]);
            }
        }
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'full name',
            'email' => 'email address',
            'phone' => 'phone number',
            'password' => 'password',
            'role_id' => 'role',
            'parent_id' => 'manager/supervisor',
            'designation_id' => 'job designation',
            'joining_date' => 'joining date',
            'contract_end_date' => 'contract end date',
            'emirates_id_number' => 'Emirates ID number',
            'employment_status' => 'employment status',
            'bank_account_holder_name' => 'bank account holder name',
            'bank_name' => 'bank name',
            'bank_account_number' => 'bank account number',
            'iban_number' => 'IBAN number',
            'insurance_policy_type' => 'insurance policy type',
            'insurance_provider' => 'insurance provider',
            'documents.emirates_id' => 'Emirates ID documents',
            'documents.labor_card' => 'Labor card documents',
            'documents.passport' => 'Passport documents',
            'documents.visa' => 'Visa documents',
            'documents.attested_certificate' => 'Attested certificates',
        ];
    }
}