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
            'avatar' => $this->avatar ? asset('storage/' . $this->avatar) : null,
            'status' => $this->status,
            'biometric_code' => $this->biometric_code,
            
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
                'employee_code' => $this->employeeProfile->employee_code,
                'joining_date' => $this->employeeProfile->joining_date,
                'contract_end_date' => $this->employeeProfile->contract_end_date,
                'emirates_id_number' => $this->employeeProfile->emirates_id_number,
                'certificate_name' => $this->employeeProfile->certificate_name,
                'employment_status' => $this->employeeProfile->employment_status,
                
                // NEW FIELDS
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
                    'account_holder_name' => $this->employeeProfile->bank_account_holder_name,
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
                'original_name' => $doc->original_name,
                'file_url' => $doc->file_url,
                'file_size' => $doc->file_size,
                'mime_type' => $doc->mime_type,
                'sort_order' => $doc->sort_order,
            ];
        }
        return $grouped;
    }
}