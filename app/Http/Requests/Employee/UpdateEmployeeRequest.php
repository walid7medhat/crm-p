<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('id');
        
        return [
            'name' => 'sometimes|string|max:255',
            'employee_name'=>'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8',
            'role_id' => 'sometimes|exists:roles,id',
            'parent_id' => 'nullable|exists:users,id',
            'status' => 'sometimes|in:active,in_active,blocked',
            
            'designation_id' => 'nullable|exists:designations,id',
            'department_id' => 'nullable|exists:departments,id',
            'company_branch_id' => 'nullable|exists:company_branches,id', // NEW
            'joining_date' => 'nullable|date',
            'contract_end_date' => 'nullable|date|after:joining_date',
            'emirates_id_number' => ['nullable', 'string', Rule::unique('employee_profiles', 'emirates_id_number')->ignore($userId, 'user_id')],
            
            // NEW FIELDS
            'passport_number' => 'nullable|string|max:255',
            'passport_expiry_date' => 'nullable|date',
            'labor_card_number' => 'nullable|string|max:255',
            'labor_card_expiry_date' => 'nullable|date',
            'iloe_expiry_date' => 'nullable|date',
            
            'bank_account_holder_name' => 'nullable|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:50',
            'branch_location' => 'nullable|string|max:255',
            'swift_code' => 'nullable|string|max:20',
            'iban_number' => 'nullable|string|max:34',
            
            'insurance_policy_type' => 'nullable',
            'insurance_policy_number' => 'nullable|string|max:100',
            'insurance_provider' => 'nullable|string|max:255',
            'insurance_start_date' => 'nullable|date',
            'insurance_expiry_date' => 'nullable|date|after:insurance_start_date',
            
            'emissary_id_number' => 'nullable|string|max:50',
            'emissary_id_pad' => 'nullable|string|max:50',
            'notification_provider' => 'nullable|string|max:255',
            'certificate_name' => 'nullable|string|max:255',
            'employment_status' => 'nullable|in:active,on_leave,terminated,suspended',
            
            'avatar' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
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
}