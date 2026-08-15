<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\Designation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DesignationController extends Controller
{
    public function __construct()
    {
        $hrRead = 'role_or_permission:super_admin|admin|hr|hr-view|designations-list';

        $this->middleware($hrRead, ['only' => ['index', 'show']]);
        $this->middleware('permission:designations-create', ['only' => ['store']]);
        $this->middleware('permission:designations-edit', ['only' => ['update']]);
        $this->middleware('permission:designations-delete', ['only' => ['destroy']]);
    }

    /**
     * Get all designations
     * GET /api/designations
     */
    public function index(Request $request)
{
    try {
        $query = Designation::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->boolean('all')) {
            $designations = $query->orderBy('id')->orderBy('name')->get();
            return ApiResponse::success($designations, 'Designations retrieved successfully');
        }

        $designations = $query->orderBy('id')
                              ->orderBy('name')
                              ->paginate($request->per_page ?? 50);

        return ApiResponse::success($designations, 'Designations retrieved successfully');
    } catch (\Exception $e) {
        return ApiResponse::error('Failed to retrieve designations: ' . $e->getMessage());
    }
}
    /**
     * Get single designation
     * GET /api/designations/{id}
     */
    public function show($id)
    {
        try {
            $designation = Designation::withCount('employeeProfiles')->findOrFail($id);
            
            return ApiResponse::success($designation, 'Designation retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Designation not found', 404);
        }
    }

    /**
     * Create new designation
     * POST /api/designations
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:designations,name',
                'description' => 'nullable|string',
               
            ]);
            
            $designation = Designation::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
            
               
            ]);
            
            return ApiResponse::success($designation, 'Designation created successfully', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create designation: ' . $e->getMessage());
        }
    }

    /**
     * Update designation
     * PUT /api/designations/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $designation = Designation::findOrFail($id);
            
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:designations,name,' . $id,
                'description' => 'nullable|string',
               
            ]);
            
            $designation->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? $designation->description,
     
            ]);
            
            return ApiResponse::success($designation, 'Designation updated successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update designation: ' . $e->getMessage());
        }
    }

    /**
     * Delete designation
     * DELETE /api/designations/{id}
     */
    public function destroy($id)
    {
        try {
            $designation = Designation::findOrFail($id);
            
            // Check if designation has employees
            if ($designation->employeeProfiles()->exists()) {
                return ApiResponse::error(
                    'Cannot delete designation because it has assigned employees. Please reassign employees first.',
                    422
                );
            }
            
            $designation->delete();
            
            return ApiResponse::success(null, 'Designation deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete designation: ' . $e->getMessage());
        }
    }

    /**
     * Get all employees under a designation
     * GET /api/designations/{id}/employees
     */
    public function getEmployees($id)
    {
        try {
            $designation = Designation::findOrFail($id);
            
            $employees = $designation->employeeProfiles()
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
                'designation' => $designation->only(['id', 'name', 'description']),
                'employees_count' => $employees->count(),
                'employees' => $employees
            ], 'Employees under this designation retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve employees: ' . $e->getMessage());
        }
    }

    /**
     * Toggle designation status (active/inactive)
     * PATCH /api/designations/{id}/toggle-status
     */
    public function toggleStatus($id)
    {
        try {
            $designation = Designation::findOrFail($id);
            $designation->is_active = !$designation->is_active;
            $designation->save();
            
            return ApiResponse::success(
                ['is_active' => $designation->is_active],
                'Designation status updated successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update status: ' . $e->getMessage());
        }
    }

    /**
     * Bulk delete designations
     * POST /api/designations/bulk-delete
     */
    public function bulkDelete(Request $request)
    {
        try {
            $validated = $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:designations,id'
            ]);
            
            $ids = $validated['ids'];
            
            // Check if any designation has employees
            $hasEmployees = Designation::whereIn('id', $ids)
                ->whereHas('employeeProfiles')
                ->exists();
            
            if ($hasEmployees) {
                return ApiResponse::error(
                    'Cannot delete designations that have assigned employees',
                    422
                );
            }
            
            $deleted = Designation::whereIn('id', $ids)->delete();
            
            return ApiResponse::success(
                ['deleted_count' => $deleted],
                'Designations deleted successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete designations: ' . $e->getMessage());
        }
    }
}