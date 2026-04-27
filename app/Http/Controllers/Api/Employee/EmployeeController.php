<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Http\Resources\Employee\EmployeeResource;
use App\Models\User;
use App\Models\Designation;
use App\Models\EmployeeProfile;
use App\Models\EmployeeDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class EmployeeController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:employees-list', ['only' => ['index', 'show']]);
        $this->middleware('permission:employees-create', ['only' => ['store']]);
        $this->middleware('permission:employees-edit', ['only' => ['update']]);
        $this->middleware('permission:employees-delete', ['only' => ['destroy']]);
    }

    /**
     * Get all employees
     * GET /api/employees
     */
    public function index(Request $request)
    {
        try {
            $query = User::with([
                'roles',
                'parent',
                'employeeProfile.designation',
                'employeeProfile.department',
                'employeeProfile.documents'
            ])->whereHas('employeeProfile');
            
            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            // Filter by employment status
            if ($request->has('employment_status')) {
                $query->whereHas('employeeProfile', function($q) use ($request) {
                    $q->where('employment_status', $request->employment_status);
                });
            }
            
            // Filter by designation
            if ($request->has('designation_id')) {
                $query->whereHas('employeeProfile', function($q) use ($request) {
                    $q->where('designation_id', $request->designation_id);
                });
            }
            
            // Filter by role
            if ($request->has('role_id')) {
                $query->whereHas('roles', function($q) use ($request) {
                    $q->where('id', $request->role_id);
                });
            }
            
            // Search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhereHas('employeeProfile', function($sub) use ($search) {
                          $sub->where('employee_code', 'like', "%{$search}%")
                               ->orWhere('emirates_id_number', 'like', "%{$search}%")
                               ->orWhere('employee_name', 'like', "%{$search}%")
                               ;
                      });
                });
            }
            
            $employees = $query->orderBy('created_at', 'desc')
                               ->paginate($request->per_page ?? 15);
            
            return ApiResponse::success(
                EmployeeResource::collection($employees),
                'Employees retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve employees: ' . $e->getMessage());
        }
    }

    /**
     * Create new employee with all data
     * POST /api/employees
     */
    public function store(StoreEmployeeRequest $request)
    {
        try {
            DB::beginTransaction();
            
            // 1. Create User
            $userData = [
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'personal_phone' => $request->personal_phone,  // NEW
                'home_country_phone_number' => $request->home_country_phone_number,  // NEW
                'nationality' => $request->nationality,  // NEW
                'salary_type' => $request->salary_type,  // NEW
                'salary_amount' => $request->salary_amount,  // NEW
                'password' => Hash::make($request->password),
                'parent_id' => $request->parent_id ?? Auth::id(),
                'added_by' => Auth::id(),
                'status' => $request->status ?? 'active',
            ];
            
            if ($request->hasFile('avatar')) {
                $userData['avatar'] = $request->file('avatar')->store('users/avatars', 'public');
            }
            
            $user = User::create($userData);
            
            // Assign role
            if ($request->has('role_id')) {
                $role = Role::find($request->role_id);
                if ($role) $user->assignRole($role);
            }
            
            // 2. Create Employee Profile
            $employeeData = [
                'user_id' => $user->id,
                'employee_name'=>$request->employee_name,
                'employee_code' => EmployeeProfile::generateEmployeeCode(),
                'designation_id' => $request->designation_id,
                'department_id' => $request->department_id,
                'company_branch_id' => $request->company_branch_id, // NEW
                'joining_date' => $request->joining_date,
                'contract_end_date' => $request->contract_end_date,
                'emirates_id_number' => $request->emirates_id_number,
                
                'passport_number' => $request->passport_number,
                'passport_expiry_date' => $request->passport_expiry_date,
                'labor_card_number' => $request->labor_card_number,
                'labor_card_expiry_date' => $request->labor_card_expiry_date,
                'iloe_expiry_date' => $request->iloe_expiry_date,
                
                'bank_account_holder_name' => $request->bank_account_holder_name,
                'bank_name' => $request->bank_name,
                'bank_account_number' => $request->bank_account_number,
                'branch_location' => $request->branch_location,
                'swift_code' => $request->swift_code,
                'iban_number' => $request->iban_number,
                'insurance_policy_type' => $request->insurance_policy_type,
                'insurance_policy_number' => $request->insurance_policy_number,
                'insurance_provider' => $request->insurance_provider,
                'insurance_start_date' => $request->insurance_start_date,
                'insurance_expiry_date' => $request->insurance_expiry_date,
                'emissary_id_number' => $request->emissary_id_number,
                'emissary_id_pad' => $request->emissary_id_pad,
                'notification_provider' => $request->notification_provider,
                'certificate_name' => $request->certificate_name,
                'employment_status' => $request->employment_status ?? 'active',

                  
                // NEW: Personal Info
                'father_name' => $request->father_name,
                'mother_name' => $request->mother_name,
                'religion' => $request->religion,
                'emergency_contact_name' => $request->emergency_contact_name,
                'emergency_email' => $request->emergency_email,
                'emergency_phone' => $request->emergency_phone,
                'address_inside_uae' => $request->address_inside_uae,
                'address_outside_uae' => $request->address_outside_uae,
                'home_country_phone' => $request->home_country_phone,
                
                // NEW: Company Details
                'sponsor' => $request->sponsor,
                'visa_quota' => $request->visa_quota,
                'vehicle' => $request->vehicle,
                'probation_end_date' => $request->probation_end_date,
                'visa_validity' => $request->visa_validity,
                'contract_joining_date' => $request->contract_joining_date,
                'gratuity_termination' => $request->gratuity_termination,

            ];


            $employeeProfile = EmployeeProfile::create($employeeData);
            
            // 3. Upload Documents
            $documentTypes = ['emirates_id', 'labor_card', 'passport', 'visa', 'attested_certificate', 'other'];
            
            foreach ($documentTypes as $documentType) {
                if ($request->hasFile("documents.{$documentType}")) {
                    $files = $request->file("documents.{$documentType}");
                    if (!is_array($files)) $files = [$files];
                    
                    foreach ($files as $index => $file) {
                        $path = $file->store("employees/{$user->id}/{$documentType}", 'public');
                        
                        EmployeeDocument::create([
                            'employee_profile_id' => $employeeProfile->id,
                            'document_type' => $documentType,
                            'document_name' => $request->input("documents.{$documentType}_names.{$index}") ?? $file->getClientOriginalName(),
                            'file_path' => $path,
                            'original_name' => $file->getClientOriginalName(),
                            'file_size' => round($file->getSize() / 1024, 2),
                            'mime_type' => $file->getMimeType(),
                            'sort_order' => $index,
                        ]);
                    }
                }
            }
            
            DB::commit();
            
            $user->load(['roles', 'parent', 'employeeProfile.designation', 'employeeProfile.documents']);
            
            return ApiResponse::success(new EmployeeResource($user), 'Employee created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to create employee: ' . $e->getMessage());
        }
    }

    /**
     * Get single employee
     * GET /api/employees/{id}
     */
    public function show($id)
    {
        try {
            $user = User::with([
                'roles',
                'parent',
                'children',
                'employeeProfile.designation',
                'employeeProfile.department',
                'employeeProfile.documents'
            ])->findOrFail($id);
            
            if (!$user->employeeProfile) {
                return ApiResponse::error('This user is not an employee', 404);
            }
            
            return ApiResponse::success(new EmployeeResource($user), 'Employee retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Employee not found', 404);
        }
    }

    /**
     * Update employee
     * PUT /api/employees/{id}
     */
  public function update(UpdateEmployeeRequest $request, $id)
{
    try {
        DB::beginTransaction();
        
        $user = User::findOrFail($id);
        
        // Update User - أضف الحقول الجديدة هنا
        $userData = $request->only([
            'name', 'email', 'phone', 'parent_id', 'status',
            'personal_phone', 'home_country_phone_number',  // NEW
            'nationality',  // NEW
            'salary_type', 'salary_amount'  // NEW
        ]);
        
        if ($request->filled('password')) {
            $userData['password'] = Hash::make($request->password);
        }
        
        if ($request->hasFile('avatar')) {
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $userData['avatar'] = $request->file('avatar')->store('users/avatars', 'public');
        }
        
        $user->update($userData);
        
        // Update role
        if ($request->has('role_id')) {
            $role = Role::find($request->role_id);
            if ($role) $user->syncRoles([$role->name]);
        }
        
        // Update Employee Profile - أضف الحقول الجديدة هنا
        if ($user->employeeProfile) {
            $employeeData = $request->only([
                'employee_name',
                // Existing fields
                'designation_id',
                'department_id',
                'company_branch_id', 
                'joining_date',
                'contract_end_date',
                'emirates_id_number',
                
                'passport_number',
                'passport_expiry_date',
                'labor_card_number',
                'labor_card_expiry_date',
                'iloe_expiry_date',
                
                'bank_account_holder_name',
                'bank_name',
                'bank_account_number',
                'branch_location',
                'swift_code',
                'iban_number',
                
                'insurance_policy_type',
                'insurance_policy_number',
                'insurance_provider',
                'insurance_start_date',
                'insurance_expiry_date',
                
                'emissary_id_number',
                'emissary_id_pad',
                'notification_provider',
                'certificate_name',
                'employment_status',
                
                // NEW: Personal Information
                'father_name',
                'mother_name',
                'religion',
                'emergency_contact_name',
                'emergency_email',
                'emergency_phone',
                'address_inside_uae',
                'address_outside_uae',
                'home_country_phone',
                
                // NEW: Company Details
                'sponsor',
                'visa_quota',
                'vehicle',
                'probation_end_date',
                'visa_validity',
                'contract_joining_date',
                'gratuity_termination',
            ]);
            
            $user->employeeProfile->update($employeeData);
        }
        
        DB::commit();
        
        // Load relationships with new fields
        $user->load([
            'roles', 
            'parent', 
            'employeeProfile.designation', 
            'employeeProfile.department',
            'employeeProfile.companyBranch',
            'employeeProfile.documents'
        ]);
        
        return ApiResponse::success(new EmployeeResource($user), 'Employee updated successfully');
    } catch (\Exception $e) {
        DB::rollBack();
        return ApiResponse::error('Failed to update employee: ' . $e->getMessage());
    }
}

    /**
     * Delete employee (soft delete)
     * DELETE /api/employees/{id}
     */
    public function destroy($id)
    {
        try {
            $user = User::findOrFail($id);
            
            // Prevent deleting self
            if ($user->id === Auth::id()) {
                return ApiResponse::error('You cannot delete your own account', 422);
            }
            
            // Delete documents from storage
            if ($user->employeeProfile && $user->employeeProfile->documents) {
                foreach ($user->employeeProfile->documents as $doc) {
                    if ($doc->file_path && Storage::disk('public')->exists($doc->file_path)) {
                        Storage::disk('public')->delete($doc->file_path);
                    }
                }
            }
            
            // Delete avatar
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            
            $user->delete(); // Soft delete
            
            return ApiResponse::success(null, 'Employee deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete employee: ' . $e->getMessage());
        }
    }

    /**
     * Get employee statistics
     * GET /api/employees/statistics
     */
    public function getStatistics()
    {
        try {
            $stats = [
                'total_employees' => User::whereHas('employeeProfile')->count(),
                'by_employment_status' => [
                    'active' => EmployeeProfile::where('employment_status', 'active')->count(),
                    'on_leave' => EmployeeProfile::where('employment_status', 'on_leave')->count(),
                    'terminated' => EmployeeProfile::where('employment_status', 'terminated')->count(),
                    'suspended' => EmployeeProfile::where('employment_status', 'suspended')->count(),
                ],
                'by_user_status' => [
                    'active' => User::whereHas('employeeProfile')->where('status', 'active')->count(),
                    'in_active' => User::whereHas('employeeProfile')->where('status', 'in_active')->count(),
                    'blocked' => User::whereHas('employeeProfile')->where('status', 'blocked')->count(),
                ],
                'by_designation' => Designation::withCount('employeeProfiles')
                    ->having('employee_profiles_count', '>', 0)
                    ->get()
                    ->map(fn($d) => [
                        'designation' => $d->name,
                        'count' => $d->employee_profiles_count
                    ]),
                'contracts_expiring_soon' => EmployeeProfile::where('contract_end_date', '<=', now()->addDays(30))
                    ->where('contract_end_date', '>=', now())
                    ->count(),
            ];
            
            return ApiResponse::success($stats, 'Employee statistics retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve statistics: ' . $e->getMessage());
        }
    }

    /**
     * Get employee documents
     * GET /api/employees/{id}/documents
     */
    public function getDocuments($id)
    {
        try {
            $user = User::findOrFail($id);
            
            if (!$user->employeeProfile) {
                return ApiResponse::error('Employee not found', 404);
            }
            
            $documents = $user->employeeProfile->documents()
                ->orderBy('document_type')
                ->orderBy('sort_order')
                ->get()
                ->groupBy('document_type');
            
            return ApiResponse::success($documents, 'Employee documents retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve documents: ' . $e->getMessage());
        }
    }

    /**
     * Delete specific document
     * DELETE /api/employees/documents/{documentId}
     */
    public function deleteDocument($documentId)
    {
        try {
            $document = EmployeeDocument::findOrFail($documentId);
            
            // Check permission (can only delete documents of employees under your hierarchy)
            $user = $document->employeeProfile->user;
            
            if (!Auth::user()->hasRole('super_admin') && Auth::id() !== $user->parent_id) {
                return ApiResponse::error('Access denied', 403);
            }
            
            // Delete file from storage
            if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
                Storage::disk('public')->delete($document->file_path);
            }
            
            $document->delete();
            
            return ApiResponse::success(null, 'Document deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete document: ' . $e->getMessage());
        }
    }
}