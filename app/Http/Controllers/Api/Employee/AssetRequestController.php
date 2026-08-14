<?php

namespace App\Http\Controllers\Api\Employee;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AssetRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AssetRequestController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = AssetRequest::query()
                ->with($this->relations())
                ->orderByDesc('applied_at')
                ->orderByDesc('id');

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('department_id')) {
                $query->where('department_id', $request->department_id);
            }

            if ($request->filled('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->filled('asset_item')) {
                $query->where('asset_item', 'like', '%' . $request->asset_item . '%');
            }

            if ($request->filled('applied_date')) {
                $query->whereDate('applied_at', $request->applied_date);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('asset_item', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($uq) use ($search) {
                            $uq->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('user.employeeProfile', function ($pq) use ($search) {
                            $pq->where('employee_code', 'like', "%{$search}%")
                                ->orWhere('employee_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('department', function ($dq) use ($search) {
                            $dq->where('name', 'like', "%{$search}%");
                        });
                });
            }

            $rows = $query->paginate($request->per_page ?? 200);

            $rows->getCollection()->transform(fn ($row) => $this->transform($row));

            return ApiResponse::success($rows, 'Asset requests retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve asset requests: ' . $e->getMessage());
        }
    }

    public function show($id)
    {
        try {
            $row = AssetRequest::with($this->relations())->findOrFail($id);
            return ApiResponse::success($this->transform($row), 'Asset request retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve asset request: ' . $e->getMessage());
        }
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        try {
            $row = AssetRequest::create([
                ...$data,
                'status' => 'pending',
                'applied_at' => now(),
                'approved_by' => null,
                'rejection_reason' => null,
            ]);

            $row->load($this->relations());

            return ApiResponse::success($this->transform($row), 'Asset request created successfully', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create asset request: ' . $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $data = $this->validated($request);

        try {
            $row = AssetRequest::findOrFail($id);

            if (!$row->isPending()) {
                return ApiResponse::error('Only pending requests can be edited', 422);
            }

            $row->update($data);
            $row->load($this->relations());

            return ApiResponse::success($this->transform($row), 'Asset request updated successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update asset request: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $row = AssetRequest::findOrFail($id);
            $row->delete();
            return ApiResponse::success(null, 'Asset request deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete asset request: ' . $e->getMessage());
        }
    }

    public function approve($id)
    {
        try {
            DB::beginTransaction();

            $row = AssetRequest::findOrFail($id);

            if (!$row->isPending()) {
                return ApiResponse::error('This request cannot be approved', 422);
            }

            $row->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'rejection_reason' => null,
            ]);

            $row->load($this->relations());
            DB::commit();

            return ApiResponse::success($this->transform($row), 'Asset request approved');
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to approve asset request: ' . $e->getMessage());
        }
    }

    public function reject(Request $request, $id)
    {
        $request->validate([
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            $row = AssetRequest::findOrFail($id);

            if (!$row->isPending()) {
                return ApiResponse::error('This request cannot be rejected', 422);
            }

            $row->update([
                'status' => 'rejected',
                'approved_by' => Auth::id(),
                'rejection_reason' => $request->rejection_reason,
            ]);

            $row->load($this->relations());
            DB::commit();

            return ApiResponse::success($this->transform($row), 'Asset request rejected');
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to reject asset request: ' . $e->getMessage());
        }
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'user_id' => 'required|exists:users,id',
            'asset_item' => 'required|string|max:255',
            'company_name' => 'required|string|max:255',
            'branch_id' => 'required|exists:company_branches,id',
            'department_id' => 'required|exists:departments,id',
            'qty' => 'required|integer|min:1|max:99',
            'description' => 'nullable|string|max:2000',
        ]);
    }

    private function relations(): array
    {
        return [
            'user.roles',
            'user.employeeProfile.department',
            'user.employeeProfile.companyBranch',
            'user.employeeProfile.designation',
            'department',
            'branch',
            'approver',
        ];
    }

    private function transform(AssetRequest $row): array
    {
        $user = $row->user;
        $profile = $user?->employeeProfile;
        $avatar = $user?->avatar ? asset('storage/' . $user->avatar) : null;

        return [
            'id' => $row->id,
            'user_id' => $row->user_id,
            'asset_item' => $row->asset_item,
            'company_name' => $row->company_name,
            'branch_id' => $row->branch_id,
            'department_id' => $row->department_id,
            'qty' => $row->qty,
            'description' => $row->description,
            'status' => $row->status,
            'applied_at' => $row->applied_at?->toIso8601String(),
            'approved_by' => $row->approved_by,
            'rejection_reason' => $row->rejection_reason,
            'user' => $user ? [
                'id' => $user->id,
                'name' => User::resolveDisplayName($user),
                'avatar' => $avatar,
                'employee_code' => $profile?->employee_code,
                'department' => $profile?->department?->name,
                'role' => $user->roles->first()?->name ?? $profile?->designation?->name,
                'branch' => $profile?->companyBranch?->name,
            ] : null,
            'department' => $row->department ? [
                'id' => $row->department->id,
                'name' => $row->department->name,
            ] : null,
            'branch' => $row->branch ? [
                'id' => $row->branch->id,
                'name' => $row->branch->name,
            ] : null,
            'approver' => $row->approver ? [
                'id' => $row->approver->id,
                'name' => User::resolveDisplayName($row->approver),
            ] : null,
        ];
    }
}
