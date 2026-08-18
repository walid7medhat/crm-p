<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\AssetAssignment;
use App\Models\AssetHistory;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AssetController extends Controller
{
    // ==================== Asset Types CRUD ====================
    
    public function getAssetTypes(Request $request)
    {
        try {
            $types = AssetType::query();
             if ($request->has('search')) {
                $types->where('name', 'like', '%' . $request->search . '%');
            }
            $types=$types->orderBy('id')->get()
                ->unique(fn ($type) => mb_strtolower(trim((string) $type->name)))
                ->values();
            return ApiResponse::success($types, 'Asset types retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve asset types: ' . $e->getMessage());
        }
    }
    
    public function storeAssetType(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:asset_types,name',
        ]);
        
        try {
            $type = AssetType::create(['name' => $request->name]);
            return ApiResponse::success($type, 'Asset type created successfully', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create asset type: ' . $e->getMessage());
        }
    }
    
    public function updateAssetType(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:asset_types,name,' . $id,
        ]);
        
        try {
            $type = AssetType::findOrFail($id);
            $type->update(['name' => $request->name]);
            return ApiResponse::success($type, 'Asset type updated successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update asset type: ' . $e->getMessage());
        }
    }
    
    public function destroyAssetType($id)
    {
        try {
            $type = AssetType::findOrFail($id);
            
            if ($type->assets()->count() > 0) {
                return ApiResponse::error('Cannot delete: This asset type has associated assets', 422);
            }
            
            $type->delete();
            return ApiResponse::success(null, 'Asset type deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete asset type: ' . $e->getMessage());
        }
    }
    
    // ==================== Assets CRUD ====================
    
    public function index(Request $request)
    {
        try {
            $query = Asset::with(['assetType', 'branch', 'currentAssignment.user', 'currentUser','department']);
            
            if ($request->has('asset_type_id')) {
                $query->where('asset_type_id', $request->asset_type_id);
            }
            
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }
            if ($request->has('department_id')) {
                $query->where('department_id', $request->department_id);
            }
            
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('asset_code', 'like', "%{$search}%")
                      ->orWhere('name', 'like', "%{$search}%")
                      ->orWhere('serial_number', 'like', "%{$search}%")
                      ->orWhereHas('currentAssignment.user', function ($uq) use ($search) {
                          $uq->where('name', 'like', "%{$search}%");
                      });
                });
            }

            if ($request->filled('user_id')) {
                $query->whereHas('currentAssignment', function ($q) use ($request) {
                    $q->where('user_id', $request->user_id)->where('status', 'active');
                });
            }

            if ($request->filled('purchase_date_from')) {
                $query->whereDate('purchase_date', '>=', $request->purchase_date_from);
            }

            if ($request->filled('purchase_date_to')) {
                $query->whereDate('purchase_date', '<=', $request->purchase_date_to);
            }

            if ($request->filled('warranty_status')) {
                if ($request->warranty_status === 'expired') {
                    $query->whereNotNull('warranty_date')->whereDate('warranty_date', '<', now());
                } elseif ($request->warranty_status === 'active') {
                    $query->whereNotNull('warranty_date')->whereDate('warranty_date', '>=', now());
                } elseif ($request->warranty_status === 'expiring_soon') {
                    $query->whereNotNull('warranty_date')
                        ->whereDate('warranty_date', '>=', now())
                        ->whereDate('warranty_date', '<=', now()->addDays(30));
                }
            }
            
            $assets = $query->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 15);
            
            return ApiResponse::success($assets, 'Assets retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve assets: ' . $e->getMessage());
        }
    }
    
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'asset_type_id' => 'required|exists:asset_types,id',
            'serial_number' => 'nullable|string',
            'model_number' => 'nullable|string',
            'unit_price' => 'nullable|numeric|min:0',
            'quantity' => 'nullable|integer|min:1',
            'condition' => 'nullable|in:new,used,working,damaged,maintenance',
            'branch_id' => 'nullable|exists:company_branches,id',
            'department_id' => 'nullable|exists:departments,id',
        ]);
        
        try {
            DB::beginTransaction();
            
            $asset = Asset::create([
                'asset_code' => Asset::generateAssetCode(),
                'name' => $request->name,
                'asset_type_id' => $request->asset_type_id,
                'serial_number' => $request->serial_number,
                'model_number' => $request->model_number,
                'rdp_number' => $request->rdp_number,
                'description' => $request->description,
                'remarks' => $request->remarks,
                'purchase_date' => $request->purchase_date,
                'warranty_date' => $request->warranty_date,
                'unit_price' => $request->unit_price,
                'supplier_name' => $request->supplier_name,
                'quantity' => $request->quantity ?? 1,
                'condition' => $request->condition ?? 'new',
                'status' => 'available',
                'branch_id' => $request->branch_id,
                'department_id' => $request->department_id,
            ]);
            
            DB::commit();
            
            return ApiResponse::success($asset->load('assetType'), 'Asset created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to create asset: ' . $e->getMessage());
        }
    }
    
    public function show($id)
    {
        try {
            $asset = Asset::with([
                'assetType',
                'branch',
                'department',
                'currentAssignment',
                'currentAssignment.user',
                'currentUser',
                'assignments' => function($q) {
                    $q->with('user', 'assignedBy')->latest();
                },
                'histories' => function($q) {
                    $q->with('user', 'performedBy')->latest();
                }
            ])->findOrFail($id);
            
            return ApiResponse::success($asset, 'Asset retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Asset not found', 404);
        }
    }
    
    public function update(Request $request, $id)
        {
            $request->validate([
                'name' => 'sometimes|string|max:255',
                'asset_type_id' => 'sometimes|exists:asset_types,id',
                'serial_number' => 'nullable|string',
                'model_number' => 'nullable|string',
                'rdp_number' => 'nullable|string', // ADD
                'description' => 'nullable|string',
                'remarks' => 'nullable|string',
                'purchase_date' => 'nullable|date',
                'warranty_date' => 'nullable|date',
                'unit_price' => 'nullable|numeric|min:0',
                'supplier_name' => 'nullable|string',
                'quantity' => 'nullable|integer|min:1',
                'condition' => 'nullable|in:new,used,working,damaged,maintenance',
                'status' => 'nullable|in:available,assigned,maintenance,disposed',
                'branch_id' => 'nullable|exists:company_branches,id',
                'department_id' => 'nullable|exists:departments,id',
            ]);

            try {
                $asset = Asset::findOrFail($id);

                $asset->update($request->only([
                    'name',
                    'asset_type_id',
                    'serial_number',
                    'model_number',
                    'rdp_number',
                    'description',
                    'remarks',
                    'purchase_date',
                    'warranty_date',
                    'unit_price',
                    'supplier_name',
                    'quantity',
                    'condition',
                    'status',
                    'branch_id',
                    'department_id',
                ]));

                return ApiResponse::success(
                    $asset->fresh()->load([
                        'assetType',
                        'branch',
                        'currentAssignment.user',
                        'currentUser',
                    ]),
                    'Asset updated successfully'
                );
            } catch (\Exception $e) {
                return ApiResponse::error(
                    'Failed to update asset: ' . $e->getMessage()
                );
            }
        }
    
    public function destroy($id)
    {
        try {
            $asset = Asset::findOrFail($id);
            
            if ($asset->status === 'assigned') {
                return ApiResponse::error('Cannot delete: Asset is currently assigned to an employee', 422);
            }
            
            $asset->delete();
            return ApiResponse::success(null, 'Asset deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete asset: ' . $e->getMessage());
        }
    }
    
    // ==================== Asset Assignment ====================
    
    public function assignAsset(Request $request, $id)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'handover_date' => 'required|date',
            'notes' => 'nullable|string',
            'return_date' => 'nullable|date',
        ]);
        
        try {
            DB::beginTransaction();
            
            $asset = Asset::findOrFail($id);
            
            if (!$asset->isAvailable()) {
                return ApiResponse::error('Asset is not available for assignment', 422);
            }
            
            $assignment = $asset->assignTo(
                $request->user_id,
                $request->handover_date,
                $request->notes
            );
             $assignment->update([
                'return_date' => $request->return_date,
            ]);
            
            // Add to history
            AssetHistory::create([
                'asset_id' => $asset->id,
                'user_id' => $request->user_id,
                'action' => 'assigned',
                'details' => "Assigned to user ID: {$request->user_id} on {$request->handover_date}",
                'performed_by' => Auth::id(),
            ]);
            
            DB::commit();
            
            return ApiResponse::success($assignment->load(['asset', 'user', 'assignedBy']), 'Asset assigned successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to assign asset: ' . $e->getMessage());
        }
    }
    public function updateAssignment(Request $request, $id)
{
    $request->validate([
        'handover_date' => 'nullable|date',
        'return_date' => 'nullable|date',
        'notes' => 'nullable|string',
    ]);

    try {
        $assignment = AssetAssignment::findOrFail($id);


        if (!$assignment) {
            return ApiResponse::error(
                'Asset does not have an active assignment',
                422
            );
        }

        $assignment->update([
            'handover_date' => $request->handover_date,
            'return_date' => $request->return_date,
            'notes' => $request->notes,
        ]);

        return ApiResponse::success(
            $assignment->fresh()->load([
                'asset',
                'user',
                'assignedBy'
            ]),
            'Assignment updated successfully'
        );

    } catch (\Exception $e) {
        return ApiResponse::error(
            'Failed to update assignment: ' . $e->getMessage()
        );
    }
}
    
    public function returnAsset(Request $request, $id){
        $request->validate([
            'return_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);
        
        try {
            DB::beginTransaction();
            
            $asset = Asset::findOrFail($id);
            
            $currentUser = $asset->currentUser()->first();
            
            $asset->returnAsset($request->return_date, $request->notes);
            
            // Add to history
            AssetHistory::create([
                'asset_id' => $asset->id,
                'user_id' => $currentUser?->id,
                'action' => 'returned',
                'details' => "Returned on {$request->return_date}. Notes: {$request->notes}",
                'performed_by' => Auth::id(),
            ]);
            
            DB::commit();
            
            return ApiResponse::success($asset->load('currentAssignment'), 'Asset returned successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to return asset: ' . $e->getMessage());
        }
    }

    public function transferAsset(Request $request, $id)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'handover_date' => 'required|date',
            'return_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $asset = Asset::findOrFail($id);
            $currentAssignment = $asset->currentAssignment;

            if ($currentAssignment) {
                $currentAssignment->update([
                    'return_date' => $request->handover_date,
                    'status' => 'returned',
                    'notes' => $request->notes,
                ]);

                AssetHistory::create([
                    'asset_id' => $asset->id,
                    'user_id' => $currentAssignment->user_id,
                    'action' => 'transferred',
                    'details' => "Transferred from user ID {$currentAssignment->user_id} to user ID {$request->user_id}",
                    'performed_by' => Auth::id(),
                ]);
            }

            $assignment = $asset->assignments()->create([
                'user_id' => $request->user_id,
                'assigned_by' => Auth::id(),
                'handover_date' => $request->handover_date,
                'notes' => $request->notes,
                'status' => 'active',
                 'return_date' => $request->handover_date,
            ]);

            $asset->update(['status' => 'assigned']);

            DB::commit();

            return ApiResponse::success($assignment->load(['asset', 'user', 'assignedBy']), 'Asset transferred successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to transfer asset: ' . $e->getMessage());
        }
    }

    public function markMaintenance(Request $request, $id){
        try {
            $asset = Asset::findOrFail($id);
            $asset->update([
                'status' => 'maintenance',
                'condition' => 'maintenance',
            ]);

            AssetHistory::create([
                'asset_id' => $asset->id,
                'user_id' => $asset->currentAssignment?->user_id,
                'action' => 'maintenance',
                'details' => $request->input('notes', 'Marked under maintenance'),
                'performed_by' => Auth::id(),
            ]);

            return ApiResponse::success($asset->fresh()->load('assetType'), 'Asset marked under maintenance');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update asset: ' . $e->getMessage());
        }
    }
    
    public function getAssetHistory($id)
    {
        try {
            $asset = Asset::findOrFail($id);
            $history = $asset->histories()->with(['user', 'performedBy'])->latest()->get();
            
            return ApiResponse::success($history, 'Asset history retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve history: ' . $e->getMessage());
        }
    }
    
    public function getEmployeeAssets($userId)
    {
        try {
            $assets = Asset::whereHas('currentAssignment', function($q) use ($userId) {
                $q->where('user_id', $userId)->where('status', 'active');
            })->with('assetType')->get();
            
            return ApiResponse::success($assets, 'Employee assets retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve employee assets: ' . $e->getMessage());
        }
    }
    
    // ==================== Statistics ====================
    
    public function statistics()
    {
        try {
            $stats = [
                'total_assets' => Asset::count(),
                'available' => Asset::where('status', 'available')->count(),
                'assigned' => Asset::where('status', 'assigned')->count(),
                'maintenance' => Asset::where('status', 'maintenance')->count(),
                'lost_assets' => Asset::where(function ($q) {
                    $q->where('status', 'disposed')->orWhere('condition', 'damaged');
                })->count(),
                'disposed' => Asset::where('status', 'disposed')->count(),
                'by_type' => Asset::select('asset_types.name', DB::raw('count(assets.id) as count'))
                    ->join('asset_types', 'assets.asset_type_id', '=', 'asset_types.id')
                    ->groupBy('asset_types.name')
                    ->get(),
                'by_condition' => Asset::select('condition', DB::raw('count(*) as count'))
                    ->groupBy('condition')
                    ->get(),
                
            ];
            
            return ApiResponse::success($stats, 'Statistics retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve statistics: ' . $e->getMessage());
        }
    }
}