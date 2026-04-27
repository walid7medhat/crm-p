<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeProfile extends Model
{
    //
     protected $table = 'employee_profiles';
    
      
    protected $fillable = [
        'user_id','employee_name', 'employee_code', 'designation_id', 'department_id', 
        'company_branch_id', 'joining_date', 'probation_end_date', 
        'contract_end_date', 'contract_joining_date',  
        'gratuity_termination',  
        'emirates_id_number',
        
        'father_name', 'mother_name', 'religion',
        'emergency_contact_name', 'emergency_email', 'emergency_phone',
        'address_inside_uae', 'address_outside_uae', 'home_country_phone',
        
        'sponsor', 'visa_quota', 'vehicle', 'visa_validity',
        
        // Passport & Labor Card
        'passport_number', 'passport_expiry_date',
        'labor_card_number', 'labor_card_expiry_date',
        'iloe_expiry_date',
        
        // Bank Details
        'bank_account_holder_name', 'bank_name', 'bank_account_number',
        'branch_location', 'swift_code', 'iban_number', 'account_holder_name',  
        
        // Insurance Details
        'insurance_policy_type', 'insurance_policy_number', 'insurance_provider',
        'insurance_start_date', 'insurance_expiry_date',
        
        // Other
        'emissary_id_number', 'emissary_id_pad', 'notification_provider',
        'employment_status', 'certificate_name',
    ];
    
    protected $casts = [
        'joining_date' => 'date',
        'probation_end_date' => 'date',
        'contract_end_date' => 'date',
        'contract_joining_date' => 'date',
        'gratuity_termination' => 'date',
        'visa_validity' => 'date',
        'insurance_start_date' => 'date',
        'insurance_expiry_date' => 'date',
        'passport_expiry_date' => 'date',
        'labor_card_expiry_date' => 'date',
        'iloe_expiry_date' => 'date',
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function designation()
    {
        return $this->belongsTo(Designation::class);
    }
    
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

     public function companyBranch()
    {
        return $this->belongsTo(CompanyBranch::class);
    }

    
    public function documents()
    {
        return $this->hasMany(EmployeeDocument::class);
    }
    
    // Helper methods to get documents by type
    public function getDocumentsByType($type)
    {
        return $this->documents()->where('document_type', $type)->get();
    }
    
    public function getEmiratesIdDocuments()
    {
        return $this->getDocumentsByType('emirates_id');
    }
    
    public function getLaborCardDocuments()
    {
        return $this->getDocumentsByType('labor_card');
    }
    
    public function getPassportDocuments()
    {
        return $this->getDocumentsByType('passport');
    }
    
    public function getVisaDocuments()
    {
        return $this->getDocumentsByType('visa');
    }
    
    public function getAttestedCertificates()
    {
        return $this->getDocumentsByType('attested_certificate');
    }
    
    public static function generateEmployeeCode(): string
    {
        $latest = self::latest('id')->first();
        $number = $latest ? intval(substr($latest->employee_code, -4)) + 1 : 1;
        return 'EMP-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
}
