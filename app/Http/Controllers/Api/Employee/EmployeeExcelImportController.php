<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\EmployeeProfile;
use App\Models\Department;
use App\Models\Designation;
use App\Models\CompanyBranch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class EmployeeExcelImportController extends Controller
{
    /**
     * Import employees from Excel file
     * POST /admin/employees/import-excel
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $file = $request->file('file');
        
        // Read Excel file
        $data = Excel::toArray([], $file);
        
        if (empty($data) || empty($data[0])) {
            return response()->json([
                'success' => false,
                'message' => 'File is empty or invalid format'
            ], 400);
        }
        
        $rows = $data[0];
        
        // Find headers row
        $headerRowIndex = null;
        $dataStartRow = null;
        
        for ($i = 0; $i < min(20, count($rows)); $i++) {
            $row = $rows[$i];
            if (!empty($row) && is_array($row)) {
                $rowString = implode(' ', array_filter($row));
                if (strpos($rowString, 'Employee Number') !== false || 
                    strpos($rowString, 'Agent Name') !== false) {
                    $headerRowIndex = $i;
                    $dataStartRow = $i + 1;
                    break;
                }
            }
        }
        
        if ($headerRowIndex === null) {
            $headerRowIndex = 2;
            $dataStartRow = 3;
        }
        
        $headers = $rows[$headerRowIndex];
        $columnMap = $this->mapHeadersToColumns($headers);
        
        $results = [
            'total' => 0,
            'created' => 0,
            'updated' => 0,
            'errors' => [],
            'skipped' => 0
        ];
        
        DB::beginTransaction();
        
        try {
            for ($i = $dataStartRow; $i < count($rows); $i++) {
                $row = $rows[$i];
                
                // Extract data from row
                $rowData = [];
                foreach ($columnMap as $columnName => $index) {
                    $rowData[$columnName] = isset($row[$index]) ? trim($row[$index]) : null;
                    if ($rowData[$columnName] === '') {
                        $rowData[$columnName] = null;
                    }
                }
                
                // **CRITICAL: Skip rows without employee_number OR agent_name**
                if (empty($rowData['employee_number']) && empty($rowData['agent_name'])) {
                    $results['skipped']++;
                    continue;
                }
                
                // Also skip if employee_number is null (required field)
                if (empty($rowData['employee_number'])) {
                    $results['errors'][] = "Row " . ($i + 1) . ": Skipped - Missing Employee Number";
                    $results['skipped']++;
                    continue;
                }
                
                // Skip if agent_name is null for new users (existing users can be updated by employee_number only)
                $existingUser = User::where('biometric_code', $rowData['employee_number'])->first();
                if (!$existingUser && empty($rowData['agent_name'])) {
                    $results['errors'][] = "Row " . ($i + 1) . ": Skipped - Missing Agent Name for new employee";
                    $results['skipped']++;
                    continue;
                }
                
                $rowNumber = $i + 1;
                $result = $this->processRow($rowData, $rowNumber);
                
                if ($result['success']) {
                    $results['total']++;
                    if ($result['action'] == 'created') {
                        $results['created']++;
                    } else {
                        $results['updated']++;
                    }
                } else {
                    $results['errors'][] = $result['error'];
                }
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'total_rows_processed' => $results['total'],
                    'created' => $results['created'],
                    'updated' => $results['updated'],
                    'skipped_empty' => $results['skipped'],
                    'errors' => $results['errors']
                ],
                'message' => "Import completed: {$results['created']} created, {$results['updated']} updated, {$results['skipped']} skipped"
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }
    
    /**
     * Map Excel headers to database column names
     */
    private function mapHeadersToColumns($headers)
    {
        $mapping = [
            'Company Br' => 'company_br',
            'Gender' => 'gender',
            'Employee Number' => 'employee_number',
            'Agent Name' => 'agent_name',
            'Position' => 'position',
            'Department' => 'department',
            'Personal Mobile Number' => 'personal_mobile_number',
            'Company Mobile Number' => 'company_mobile_number',
            'Reporting to' => 'reporting_to',
            'Joining Date' => 'joining_date',
            'Email' => 'email',
            'Personal Email' => 'personal_email',
            'Birthdate' => 'birthdate',
            'Age' => 'age',
            'Marital Status' => 'marital_status',
            'Emirates ID No' => 'emirates_id_no',
            'Insurance Card Expiry' => 'insurance_card_expiry',
            'Passport Number' => 'passport_number',
            'Passport Expiry' => 'passport_expiry',
            'Visa Expiration Date' => 'visa_expiration_date',
            'ILOE Expiration Date' => 'iloe_expiration_date',
            'Labour Card/Work Permit Expiration Date' => 'labour_card_work_permit_expiration_date',
            'Labor Card Number' => 'labor_card_number',
            'Visa Status' => 'visa_status',
        ];
        
        $columnMap = [];
        
        foreach ($headers as $index => $header) {
            $cleanHeader = trim($header);
            if (empty($cleanHeader)) continue;
            
            foreach ($mapping as $excelHeader => $dbColumn) {
                if (strpos($cleanHeader, $excelHeader) !== false || 
                    strcasecmp($cleanHeader, $excelHeader) === 0) {
                    $columnMap[$dbColumn] = $index;
                    break;
                }
            }
        }
        
        return $columnMap;
    }
    
    /**
     * Process single row
     */
    private function processRow($row, $rowNumber)
    {
        try {
            // 1. Find user by biometric_code (Employee Number)
            $user = User::where('biometric_code', $row['employee_number'])->first();
            
            // 2. Find manager (Reporting to)
            $parentId = null;
            if (!empty($row['reporting_to'])) {
                $manager = User::where('name', 'LIKE', '%' . $row['reporting_to'] . '%')->first();
                if ($manager) {
                    $parentId = $manager->id;
                }
            }
            
            // 3. Find designation (Position)
            $designationId = null;
            if (!empty($row['position'])) {
                $designation = Designation::where('name', 'LIKE', '%' . $row['position'] . '%')->first();
                if ($designation) {
                    $designationId = $designation->id;
                }
            }
            
            // 4. Find department
            $departmentId = null;
            if (!empty($row['department'])) {
                $department = Department::where('name', 'LIKE', '%' . $row['department'] . '%')->first();
                if ($department) {
                    $departmentId = $department->id;
                }
            }
            
            // 5. Find company branch
            $companyBranchId = null;
            if (!empty($row['company_br'])) {
                $branch = CompanyBranch::where('name', 'LIKE', '%' . $row['company_br'] . '%')->first();
                if ($branch) {
                    $companyBranchId = $branch->id;
                }
            }
            
            $action = 'updated';
            
            if ($user) {
                // UPDATE existing user
                $updateData = [];
                // if (!empty($row['agent_name'])) $updateData['name'] = $row['agent_name'];
                // if (!empty($row['personal_mobile_number'])) $updateData['phone'] = $row['personal_mobile_number'];
                // if (!empty($row['company_mobile_number'])) $updateData['company_mobile'] = $row['company_mobile_number'];
                // if (!empty($row['gender'])) $updateData['gender'] = $this->mapGender($row['gender']);
                // if (!empty($row['birthdate'])) $updateData['birth_date'] = $this->parseDate($row['birthdate']);
                // if (!empty($row['marital_status'])) $updateData['marital_status'] = $this->mapMaritalStatus($row['marital_status']);
                // if (!empty($row['personal_email'])) $updateData['personal_email'] = $row['personal_email'];
                
                // if (!empty($updateData)) {
                //     $updateData['updated_at'] = now();
                //     $user->update($updateData);
                // }
            } else {
                // CREATE new user
                $user = User::create([
                    'biometric_code' => $row['employee_number'],
                    'name' => $row['agent_name'] ?? 'Unknown',
                    'email' => $row['email'] ?? null,
                    'personal_email' => $row['personal_email'] ?? null,
                    'phone' => $row['personal_mobile_number'] ?? null,
                    'company_mobile' => $row['company_mobile_number'] ?? null,
                    'gender' => $this->mapGender($row['gender'] ?? null),
                    'birth_date' => $this->parseDate($row['birthdate'] ?? null),
                    'marital_status' => $this->mapMaritalStatus($row['marital_status'] ?? null),
                    'password' => Hash::make('123123123'),
                    'parent_id' => $parentId,
                    'status' => 'in_active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $action = 'created';
            }
            
            // 6. Update or create Employee Profile
            $profileData = [
                'employee_name' => $row['agent_name'] ?? 'Unknown',
                'employee_code' => $row['employee_number'],
                'designation_id' => $designationId,
                'department_id' => $departmentId,
                'company_branch_id' => $companyBranchId,
                'updated_at' => now(),
            ];
            
            if (!empty($row['joining_date'])) $profileData['joining_date'] = $this->parseDate($row['joining_date']);
            if (!empty($row['visa_expiration_date'])) $profileData['contract_end_date'] = $this->parseDate($row['visa_expiration_date']);
            if (!empty($row['emirates_id_no'])) $profileData['emirates_id_number'] = $row['emirates_id_no'];
            if (!empty($row['passport_number'])) $profileData['passport_number'] = $row['passport_number'];
            if (!empty($row['passport_expiry'])) $profileData['passport_expiry_date'] = $this->parseDate($row['passport_expiry']);
            if (!empty($row['iloe_expiration_date'])) $profileData['iloe_expiry_date'] = $this->parseDate($row['iloe_expiration_date']);
            if (!empty($row['labor_card_number'])) $profileData['labor_card_number'] = $row['labor_card_number'];
            if (!empty($row['labour_card_work_permit_expiration_date'])) $profileData['labor_card_expiry_date'] = $this->parseDate($row['labour_card_work_permit_expiration_date']);
            if (!empty($row['insurance_card_expiry'])) $profileData['insurance_expiry_date'] = $this->parseDate($row['insurance_card_expiry']);
            if (!empty($row['visa_status'])) $profileData['employment_status'] = $this->mapVisaStatus($row['visa_status']);
            
            EmployeeProfile::updateOrCreate(
                ['user_id' => $user->id],
                $profileData
            );
            
            return ['success' => true, 'action' => $action];
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => "Row {$rowNumber}: " . $e->getMessage()];
        }
    }
    
    /**
     * Parse date from various formats
     */
    private function parseDate($date)
    {
        if (empty($date)) {
            return null;
        }
        
        if (is_numeric($date)) {
            return Carbon::createFromDate(1900, 1, 1)->addDays($date - 2);
        }
        
        try {
            return Carbon::parse($date);
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Map gender
     */
    private function mapGender($gender)
    {
        if (empty($gender)) return null;
        
        $gender = strtolower(trim($gender));
        if (in_array($gender, ['male', 'm'])) return 'male';
        if (in_array($gender, ['female', 'f'])) return 'female';
        
        return null;
    }
    
    /**
     * Map marital status
     */
    private function mapMaritalStatus($status)
    {
        if (empty($status)) return null;
        
        $status = strtolower(trim($status));
        if (in_array($status, ['single'])) return 'single';
        if (in_array($status, ['married'])) return 'married';
        if (in_array($status, ['divorced'])) return 'divorced';
        if (in_array($status, ['widowed'])) return 'widowed';
        
        return null;
    }
    
    /**
     * Map visa status
     */
    private function mapVisaStatus($status)
    {
        if (empty($status)) return 'active';
        
        $status = strtolower(trim($status));
        $mapping = [
            'active' => 'active',
            'valid' => 'active',
            'on leave' => 'on_leave',
            'terminated' => 'terminated',
            'cancelled' => 'terminated',
            'suspended' => 'suspended',
        ];
        
        return $mapping[$status] ?? 'active';
    }
}