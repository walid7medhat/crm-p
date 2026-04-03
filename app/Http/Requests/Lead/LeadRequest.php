<?php

namespace App\Http\Requests\Lead;

use Illuminate\Foundation\Http\FormRequest;

class LeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'lead_name' => 'required|string|max:255',
            'lead_number' => 'string|unique:leads,lead_number',
            'stage_id' => 'required|exists:stages,id',
            
            // Contact Information
            'salutation' => 'nullable|string|max:10',
            'first_name' => 'required|string|max:255',
            'second_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date',
            
            // Contact Details
            'whatsapp_number' => 'nullable|string|max:20',
          'work_phone' => [
                'required',
                'max:20',
                'regex:/^\+?[0-9]+$/'
            ],
            'work_phone_2' => [
                'nullable',
                'max:20',
                'regex:/^\+?[0-9]+$/'
            ],
            'secondary_email'=>'nullable|email',
            'email' => 'nullable|email',
            'website' => 'nullable|url',
            'messenger' => 'nullable|string|max:255',
            'facebook' => 'nullable|string|max:255',
            
            // Company Information
            'company_name' => 'nullable|string|max:255',
            'position' => 'nullable|string|max:255',
            
            // Property Information
            'interested_in' => 'nullable|string|max:255',
            'bedrooms' => 'nullable|integer|min:0',
            'purpose_buying' => 'nullable|string|max:255',
            'nationality' => 'nullable|string|max:255',
            'citizenship_program' => 'nullable|string|max:255',
            
            // Lead Source
            'lead_source' => 'required|string|max:255',
            'source_information' => 'nullable|string|max:255',
            'lead_branch_source' => 'nullable|string|max:255',
            'ad_id' => 'nullable|string|max:255',
            'available_to_everyone' => 'boolean',
            
            // Status
            'status_lead' => 'nullable|string|max:255',
            'status_unit' => 'nullable|string|max:255',
            'status_project' => 'nullable|string|max:255',
            'lists' => 'nullable|string|max:255',
            'unqualified_reason' => 'nullable|string|max:255',
            'why_lost_lead' => 'nullable|string|max:255',
            
            // Additional
            'address' => 'nullable|string',
            'comment' => 'nullable|string',
            'additional_services' => 'nullable|string',
            
            // Sales & Management
            // 'sales_id' => 'nullable|exists:users,id', // Sales person (optional)
            'responsible_person_id' => 'required|exists:users,id', // Manager (required)
            
            // Relationships
            'participants' => 'nullable|array',
            'participants.*.name' => 'required|string|max:255',
            'participants.*.phone' => 'nullable|string|max:20',
            'participants.*.email' => 'nullable|email',
            'participants.*.type' => 'required|in:contact,company',
            
            'observers' => 'nullable|array',
            'observers.*' => 'exists:users,id',
            
            'budget' => 'nullable|numeric|min:0|max:999999999.99',
            'budget_from' => 'nullable|numeric|min:0|max:999999999.99',
            'budget_to' => 'nullable|numeric|min:0|max:999999999.99',
            'lead_type'=>'nullable',
            'property_status'=>'nullable',
            'currency' => 'nullable',
             
            'area_id' => 'nullable|exists:areas,id',
            'property_type_id' => 'nullable|exists:property_types,id',

            'status_lead' => 'nullable|string|max:50',  
            'available_date' => 'nullable|date',
            'branch' => 'nullable|string|max:100',
            'why_lost_lead' => 'nullable|string|max:255',
        ];

        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['lead_number'] = 'string|unique:leads,lead_number,' . $this->route('lead')->id;
        }

        return $rules;
    }

    protected function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $from = $this->input('budget_from');
            $to = $this->input('budget_to');
            if ($from !== null && $from !== '' && $to !== null && $to !== '' && (float) $to < (float) $from) {
                $validator->errors()->add('budget_to', 'The budget to must be greater than or equal to budget from.');
            }
        });
    }

    public function attributes(): array
{
    return [
        'work_phone' => 'Primary Phone',
        'work_phone_2' => 'Secondary Phone',
        'email' => 'Primary Email',
        'secondary_email' => 'Secondary Email',
    ];
}

    public function messages(): array
    {
        return [
            'responsible_person_id.required' => 'The responsible manager is required.',
            'responsible_person_id.exists' => 'The selected responsible manager does not exist.',
            'work_phone.regex' => 'Primary Phone number must contain digits only.',
            'work_phone_2.regex' => 'Secondary Phone number must contain digits only.',
        ];
    }
}