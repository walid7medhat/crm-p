<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DepartmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:departments-list', ['only' => ['index', 'show']]);
        $this->middleware('permission:departments-create', ['only' => ['store']]);
        $this->middleware('permission:departments-edit', ['only' => ['update']]);
        $this->middleware('permission:departments-delete', ['only' => ['destroy']]);
    }

    /**
     * Get all departments
     * GET /api/departments
     */
    public function index(Request $request)
    {
        try {
            $query = Department::query();
            
            // Search filter
            if ($request->has('search')) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }
            
            // Active filter
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }
            
            $departments = $query->orderBy('id')
                                  ->orderBy('name')
                                  ->paginate($request->per_page ?? 15);
            
            return ApiResponse::success($departments, 'departments retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve departments: ' . $e->getMessage());
        }
    }

    /**
     * Get single department
     * GET /api/departments/{id}
     */
    public function show($id)
    {
        try {
            $department = Department::withCount('employeeProfiles')->findOrFail($id);
            
            return ApiResponse::success($department, 'department retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('department not found', 404);
        }
    }

    /**
     * Create new department
     * POST /api/departments
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:departments,name',
               
            ]);
            
            $department = Department::create([
                'name' => $validated['name'],
            
               
            ]);
            
            return ApiResponse::success($department, 'department created successfully', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create department: ' . $e->getMessage());
        }
    }

    /**
     * Update department
     * PUT /api/departments/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $department = Department::findOrFail($id);
            
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:departments,name,' . $id,
               
            ]);
            
            $department->update([
                'name' => $validated['name'],
     
            ]);
            
            return ApiResponse::success($department, 'department updated successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update department: ' . $e->getMessage());
        }
    }

    /**
     * Delete department
     * DELETE /api/departments/{id}
     */
    public function destroy($id)
    {
        try {
            $department = Department::findOrFail($id);
            
            // Check if department has employees
            if ($department->employeeProfiles()->exists()) {
                return ApiResponse::error(
                    'Cannot delete department because it has assigned employees. Please reassign employees first.',
                    422
                );
            }
            
            $department->delete();
            
            return ApiResponse::success(null, 'department deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete department: ' . $e->getMessage());
        }
    }

    /**
     * Get all employees under a department
     * GET /api/departments/{id}/employees
     */
    public function getEmployees($id)
    {
        try {
            $department = Department::findOrFail($id);
            
            $employees = $department->employeeProfiles()
                ->with('user')
                ->get()
                ->map(function ($profile) {
                    return [
                        'id' => $profile->user->id,
                        'name' => $profile->user->name,
                        'email' => $profile->user->email,
                        'employee_code' => $profile->employee_code,
                        'employment_status' => $profile->employment_status,
                    ];
                });
            
            return ApiResponse::success([
                'department' => $department->only(['id', 'name', 'description']),
                'employees_count' => $employees->count(),
                'employees' => $employees
            ], 'Employees under this department retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve employees: ' . $e->getMessage());
        }
    }

    /**
     * Toggle department status (active/inactive)
     * PATCH /api/departments/{id}/toggle-status
     */
    public function toggleStatus($id)
    {
        try {
            $department = Department::findOrFail($id);
            $department->is_active = !$department->is_active;
            $department->save();
            
            return ApiResponse::success(
                ['is_active' => $department->is_active],
                'department status updated successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update status: ' . $e->getMessage());
        }
    }

    /**
     * Bulk delete departments
     * POST /api/departments/bulk-delete
     */
    public function bulkDelete(Request $request)
    {
        try {
            $validated = $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:departments,id'
            ]);
            
            $ids = $validated['ids'];
            
            // Check if any department has employees
            $hasEmployees = Department::whereIn('id', $ids)
                ->whereHas('employeeProfiles')
                ->exists();
            
            if ($hasEmployees) {
                return ApiResponse::error(
                    'Cannot delete departments that have assigned employees',
                    422
                );
            }
            
            $deleted = Department::whereIn('id', $ids)->delete();
            
            return ApiResponse::success(
                ['deleted_count' => $deleted],
                'departments deleted successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete departments: ' . $e->getMessage());
        }
    }
}