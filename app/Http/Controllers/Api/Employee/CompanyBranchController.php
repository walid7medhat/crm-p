<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\CompanyBranch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanyBranchController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:branches-list', ['only' => ['index', 'show']]);
        $this->middleware('permission:branches-create', ['only' => ['store']]);
        $this->middleware('permission:branches-edit', ['only' => ['update']]);
        $this->middleware('permission:branches-delete', ['only' => ['destroy']]);
    }

    /**
     * Get all company branches
     * GET /api/company-branches
     */
    public function index(Request $request)
    {
        try {
            $query = CompanyBranch::query();
            
            // Search filter
            if ($request->has('search')) {
                $query->where(function($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->search . '%')
                      ->orWhere('code', 'like', '%' . $request->search . '%')
                      ->orWhere('address', 'like', '%' . $request->search . '%');
                });
            }
            
            // Active filter
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }
            
          
            
            $branches = $query->orderBy('name')
                              ->orderBy('code')
                              ->paginate($request->per_page ?? 15);
            
            return ApiResponse::success($branches, 'Branches retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve branches: ' . $e->getMessage());
        }
    }

    /**
     * Get single company branch
     * GET /api/company-branches/{id}
     */
    public function show($id)
    {
        try {
            $branch = CompanyBranch::withCount('employeeProfiles')->findOrFail($id);
            
            return ApiResponse::success($branch, 'Branch retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Branch not found', 404);
        }
    }

    /**
     * Create new company branch
     * POST /api/company-branches
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:100|unique:company_branches,code',
                'address' => 'nullable|string|max:500',
                'phone' => 'nullable|string|max:255',
                'email' => 'nullable|email|max:255',
                'is_active' => 'boolean',
            ]);
            
            $branch = CompanyBranch::create([
                'name' => $validated['name'],
                'code' => $validated['code'],
                'address' => $validated['address'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);
            
            return ApiResponse::success($branch, 'Branch created successfully', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create branch: ' . $e->getMessage());
        }
    }

    /**
     * Update company branch
     * PUT /api/company-branches/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $branch = CompanyBranch::findOrFail($id);
            
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:100|unique:company_branches,code,' . $id,
                'address' => 'nullable|string|max:500',
                'phone' => 'nullable|string|max:255',
                'email' => 'nullable|email|max:255',
                'is_active' => 'boolean',
            ]);
            
            $branch->update([
                'name' => $validated['name'],
                'code' => $validated['code'],
                'address' => $validated['address'] ?? $branch->address,
                'phone' => $validated['phone'] ?? $branch->phone,
                'email' => $validated['email'] ?? $branch->email,
                'is_active' => $validated['is_active'] ?? $branch->is_active,
            ]);
            
            return ApiResponse::success($branch, 'Branch updated successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update branch: ' . $e->getMessage());
        }
    }

    /**
     * Delete company branch
     * DELETE /api/company-branches/{id}
     */
    public function destroy($id)
    {
        try {
            $branch = CompanyBranch::findOrFail($id);
            
            // Check if branch has employees
            if ($branch->employeeProfiles()->exists()) {
                return ApiResponse::error(
                    'Cannot delete branch because it has assigned employees. Please reassign employees first.',
                    422
                );
            }
            
            $branch->delete();
            
            return ApiResponse::success(null, 'Branch deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete branch: ' . $e->getMessage());
        }
    }

    /**
     * Get all employees under a branch
     * GET /api/company-branches/{id}/employees
     */
    public function getEmployees($id)
    {
        try {
            $branch = CompanyBranch::findOrFail($id);
            
            $employees = $branch->employeeProfiles()
                ->with('user')
                ->get()
                ->map(function ($profile) {
                    return [
                        'id' => $profile->user->id,
                        'name' => $profile->user->name,
                        'email' => $profile->user->email,
                        'phone' => $profile->user->phone,
                        'employee_code' => $profile->employee_code,
                        'employment_status' => $profile->employment_status,
                        'designation_id' => $profile->designation_id,
                    ];
                });
            
            return ApiResponse::success([
                'branch' => $branch->only(['id', 'name', 'code', 'city', 'country']),
                'employees_count' => $employees->count(),
                'employees' => $employees
            ], 'Employees under this branch retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve employees: ' . $e->getMessage());
        }
    }

    /**
     * Toggle branch status (active/inactive)
     * PATCH /api/company-branches/{id}/toggle-status
     */
    public function toggleStatus($id)
    {
        try {
            $branch = CompanyBranch::findOrFail($id);
            $branch->is_active = !$branch->is_active;
            $branch->save();
            
            return ApiResponse::success(
                ['is_active' => $branch->is_active],
                'Branch status updated successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update status: ' . $e->getMessage());
        }
    }

    /**
     * Bulk delete branches
     * POST /api/company-branches/bulk-delete
     */
    public function bulkDelete(Request $request)
    {
        try {
            $validated = $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:company_branches,id'
            ]);
            
            $ids = $validated['ids'];
            
            // Check if any branch has employees
            $hasEmployees = CompanyBranch::whereIn('id', $ids)
                ->whereHas('employeeProfiles')
                ->exists();
            
            if ($hasEmployees) {
                return ApiResponse::error(
                    'Cannot delete branches that have assigned employees',
                    422
                );
            }
            
            $deleted = CompanyBranch::whereIn('id', $ids)->delete();
            
            return ApiResponse::success(
                ['deleted_count' => $deleted],
                'Branches deleted successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete branches: ' . $e->getMessage());
        }
    }

    /**
     * Get all cities (for dropdown)
     * GET /api/company-branches/cities/list
     */
    public function getCities()
    {
        try {
            $cities = CompanyBranch::where('is_active', true)
                ->distinct()
                ->pluck('city')
                ->filter()
                ->values();
            
            return ApiResponse::success($cities, 'Cities retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve cities: ' . $e->getMessage());
        }
    }

    /**
     * Get branch statistics
     * GET /api/company-branches/statistics/summary
     */
    public function getStatistics()
    {
        try {
            $statistics = [
                'total_branches' => CompanyBranch::count(),
                'active_branches' => CompanyBranch::where('is_active', true)->count(),
                'inactive_branches' => CompanyBranch::where('is_active', false)->count(),
                'branches_with_employees' => CompanyBranch::has('employeeProfiles')->count(),
                'total_employees' => \App\Models\EmployeeProfile::count(),
            ];
            
            return ApiResponse::success($statistics, 'Statistics retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve statistics: ' . $e->getMessage());
        }
    }
}