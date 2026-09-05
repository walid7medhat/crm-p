<?php

namespace App\Http\Resources\Employee;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'personal_phone' => $this->personal_phone,  // NEW
            'home_country_phone_number' => $this->home_country_phone_number,  // NEW
            'nationality' => $this->nationality,  // NEW
            'avatar' => $this->avatar ? asset('storage/' . $this->avatar) : null,
            'status' => $this->status,
            'biometric_code' => $this->biometric_code,
            
            // NEW: Salary Information
            'salary' => [
                'type' => $this->salary_type,
                'amount' => $this->salary_amount,
            ],
            
            'role' => $this->roles->first() ? [
                'id' => $this->roles->first()->id,
                'name' => $this->roles->first()->name
            ] : null,
            
            'parent' => $this->parent ? [
                'id' => $this->parent->id,
                'name' => $this->parent->name
            ] : null,
            
            'employee_profile' => $this->employeeProfile ? [
                
                'id' => $this->employeeProfile->id,
                'employee_name'=>$this->employeeProfile->employee_name,
                'employee_code' => $this->employeeProfile->employee_code,
                'joining_date' => $this->employeeProfile->joining_date,
                'probation_end_date' => $this->employeeProfile->probation_end_date,  // NEW
                'contract_joining_date' => $this->employeeProfile->contract_joining_date,  // NEW
                'contract_end_date' => $this->employeeProfile->contract_end_date,
                'gratuity_termination' => $this->employeeProfile->gratuity_termination,  // NEW
                'emirates_id_number' => $this->employeeProfile->emirates_id_number,
                'emirates_id_expiry_date' => $this->employeeProfile->emirates_id_expiry_date,
                'certificate_name' => $this->employeeProfile->certificate_name,
                'employment_status' => $this->employeeProfile->employment_status,
                
                // NEW: Personal Information
                'father_name' => $this->employeeProfile->father_name,
                'mother_name' => $this->employeeProfile->mother_name,
                'religion' => $this->employeeProfile->religion,
                'emergency_contact' => [
                    'name' => $this->employeeProfile->emergency_contact_name,
                    'email' => $this->employeeProfile->emergency_email,
                    'phone' => $this->employeeProfile->emergency_phone,
                    'relation' => $this->employeeProfile->emergency_contact_relation,
                ],
                'health_disclosure' => $this->employeeProfile->health_disclosure,
                'addresses' => [
                    'inside_uae' => $this->employeeProfile->address_inside_uae,
                    'outside_uae' => $this->employeeProfile->address_outside_uae,
                    'home_country_phone' => $this->employeeProfile->home_country_phone,
                ],
                
                // NEW: Company Details
                'sponsor' => $this->employeeProfile->sponsor,
                'visa_quota' => $this->employeeProfile->visa_quota,
                'vehicle' => $this->employeeProfile->vehicle,
                'visa_validity' => $this->employeeProfile->visa_validity,
               
                
                // Passport & Labor Card
                'passport_number' => $this->employeeProfile->passport_number,
                'passport_expiry_date' => $this->employeeProfile->passport_expiry_date,
                'labor_card_number' => $this->employeeProfile->labor_card_number,
                'labor_card_expiry_date' => $this->employeeProfile->labor_card_expiry_date,
                'iloe_expiry_date' => $this->employeeProfile->iloe_expiry_date,
                
                // Branch (Company Branch)
                'company_branch_id' => $this->employeeProfile->company_branch_id,
                'branch_name' => $this->employeeProfile->companyBranch?->name,
                'branch_code' => $this->employeeProfile->companyBranch?->code,
                
                'designation' => $this->employeeProfile->designation ? [
                    'id' => $this->employeeProfile->designation->id,
                    'name' => $this->employeeProfile->designation->name,
                    'description' => $this->employeeProfile->designation->description,
                ] : null,
                
                'department' => $this->employeeProfile->department ? [
                    'id' => $this->employeeProfile->department->id,
                    'name' => $this->employeeProfile->department->name,
                ] : null,
                
                'bank_details' => [
                    'bank_account_holder_name' => $this->employeeProfile->bank_account_holder_name ,
                    'bank_name' => $this->employeeProfile->bank_name,
                    'account_number' => $this->employeeProfile->bank_account_number,
                    'branch_location' => $this->employeeProfile->branch_location,
                    'swift_code' => $this->employeeProfile->swift_code,
                    'iban_number' => $this->employeeProfile->iban_number,
                ],
                
                'insurance_details' => [
                    'policy_type' => $this->employeeProfile->insurance_policy_type,
                    'policy_number' => $this->employeeProfile->insurance_policy_number,
                    'provider' => $this->employeeProfile->insurance_provider,
                    'start_date' => $this->employeeProfile->insurance_start_date,
                    'expiry_date' => $this->employeeProfile->insurance_expiry_date,
                ],
                
                'emissary_id' => [
                    'number' => $this->employeeProfile->emissary_id_number,
                    'pad' => $this->employeeProfile->emissary_id_pad,
                ],
                
                'notification_provider' => $this->employeeProfile->notification_provider,
                
                'documents' => $this->groupDocumentsByType($this->employeeProfile->documents),
                
            ] : null,
            
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
    
    private function groupDocumentsByType($documents)
    {
        if (!$documents) return [];
        
        $grouped = [];
        foreach ($documents as $doc) {
            $type = $doc->document_type;
            if (!isset($grouped[$type])) {
                $grouped[$type] = [];
            }
            $grouped[$type][] = [
                'id' => $doc->id,
                'name' => $doc->document_name,
                'document_type' => $doc->document_type,
                'original_name' => $doc->original_name,
                'file_url' => $doc->file_path ? asset('storage/' . $doc->file_path) : null,  // Fixed: changed from file_url to file_path
                'file_size' => $doc->file_size,
                'mime_type' => $doc->mime_type,
                'sort_order' => $doc->sort_order,
            ];
        }
        return $grouped;
    }
}