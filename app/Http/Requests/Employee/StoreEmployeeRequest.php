<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // ========== USER BASIC INFO ==========
            'name' => 'required|string|max:255',
            'employee_name'=>'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8',
            'role_id' => 'required|exists:roles,id',
            'parent_id' => 'nullable|exists:users,id',
            'status' => 'nullable|in:active,in_active,blocked',
            
            // ========== EMPLOYEE BASIC INFO ==========
            'designation_id' => 'nullable|exists:designations,id',
            'department_id' => 'nullable|exists:departments,id',
            'company_branch_id' => 'nullable|exists:company_branches,id', // NEW
            'joining_date' => 'nullable|date',
            'contract_end_date' => 'nullable|date|after:joining_date',
            'emirates_id_number' => 'nullable|string|unique:employee_profiles,emirates_id_number',
            'certificate_name' => 'nullable|string|max:255',
            'employment_status' => 'nullable|in:active,on_leave,terminated,suspended',
            
            // ========== PASSPORT DETAILS (NEW) ==========
            'passport_number' => 'nullable|string|max:255',
            'passport_expiry_date' => 'nullable|date',
            
            // ========== LABOR CARD DETAILS (NEW) ==========
            'labor_card_number' => 'nullable|string|max:255',
            'labor_card_expiry_date' => 'nullable|date',
            
            // ========== ILOE DETAILS (NEW) ==========
            'iloe_expiry_date' => 'nullable|date',
            
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
            
            // ========== DOCUMENTS ==========
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

                // NEW: Personal Information
                'father_name' => 'nullable|string|max:255',
                'mother_name' => 'nullable|string|max:255',
                'religion' => 'nullable|string|max:100',
                'emergency_contact_name' => 'nullable|string|max:255',
                'emergency_email' => 'nullable|email',
                'emergency_phone' => 'nullable|string|max:20',
                'address_inside_uae' => 'nullable|string',
                'address_outside_uae' => 'nullable|string',
                'home_country_phone' => 'nullable|string|max:20',
                
                // NEW: Company Details
                'sponsor' => 'nullable|string|max:255',
                'visa_quota' => 'nullable|string|max:255',
                'vehicle' => 'nullable|string|max:255',
                'probation_end_date' => 'nullable|date',
                'visa_validity' => 'nullable|date',
                'contract_joining_date' => 'nullable|date',
                'gratuity_termination' => 'nullable|date',
                
                // NEW: Salary Info (in users table)
                'nationality' => 'nullable|string|max:100',
                'salary_type' => 'nullable|in:daily,monthly,yearly',
                'salary_amount' => 'nullable|numeric|min:0',
                'personal_phone' => 'nullable|string|max:20',
                'home_country_phone_number' => 'nullable|string|max:20',
                
       
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Employee name is required',
            'email.required' => 'Email address is required',
            'email.email' => 'Please enter a valid email address',
            'email.unique' => 'This email is already registered',
            'password.required' => 'Password is required',
            'password.min' => 'Password must be at least 8 characters',
            'role_id.required' => 'Please select a role for the employee',
            'company_branch_id.exists' => 'Selected branch is invalid',
            'passport_expiry_date.date' => 'Passport expiry date must be a valid date',
            'labor_card_expiry_date.date' => 'Labor card expiry date must be a valid date',
            'iloe_expiry_date.date' => 'ILOE expiry date must be a valid date',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge([
                'phone' => preg_replace('/[^0-9+]/', '', $this->phone),
            ]);
        }
        
        $fields = [
            'parent_id', 'designation_id', 'company_branch_id', 'emirates_id_number', 
            'certificate_name', 'passport_number', 'labor_card_number',
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
            'company_branch_id' => 'company branch',
            'joining_date' => 'joining date',
            'contract_end_date' => 'contract end date',
            'emirates_id_number' => 'Emirates ID number',
            'employment_status' => 'employment status',
            'passport_number' => 'passport number',
            'passport_expiry_date' => 'passport expiry date',
            'labor_card_number' => 'labor card number',
            'labor_card_expiry_date' => 'labor card expiry date',
            'iloe_expiry_date' => 'ILOE expiry date',
        ];
    }
}