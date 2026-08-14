<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Helpers\ApiResponse;
use App\Notifications\NewDocumentRequestNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Notifications\DocumentRequestStatusNotification;

class DocumentRequestController extends Controller
{
    /**
     * GET /api/document-types
     */
    public function getDocumentTypes(Request $request)
    {
        try {
            $types = DocumentType::query();
            if ($request->has('search')) {
                $types->where('name', 'like', '%' . $request->search . '%');
            }
            
           $types=$types-> orderBy('id')->get();
            return ApiResponse::success($types, 'Document types retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve document types: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/document-types
     */
    public function storeDocumentType(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:document_types,name',
        ]);

        try {
            $documentType = DocumentType::create([
                'name' => $request->name,
            ]);

            return ApiResponse::success($documentType, 'Document type created successfully', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create document type: ' . $e->getMessage());
        }
    }

    /**
     * PUT /api/document-types/{id}
     */
    public function updateDocumentType(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:document_types,name,' . $id,
        ]);

        try {
            $documentType = DocumentType::findOrFail($id);
            $documentType->update([
                'name' => $request->name,
            ]);

            return ApiResponse::success($documentType, 'Document type updated successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update document type: ' . $e->getMessage());
        }
    }

    /**
     * DELETE /api/document-types/{id}
     */
    public function destroyDocumentType($id)
    {
        try {
            $documentType = DocumentType::findOrFail($id);
            
            // Check if any document requests exist
            if ($documentType->documentRequests()->count() > 0) {
                return ApiResponse::error('Cannot delete: This document type has existing requests', 422);
            }
            
            $documentType->delete();
            return ApiResponse::success(null, 'Document type deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete document type: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/document-requests
     */
    public function store(Request $request)
    {
        $request->validate([
            'document_type_id' => 'required|exists:document_types,id',
            'description' => 'nullable|string',
        ]);
        
        try {
            DB::beginTransaction();
            
            $documentRequest = DocumentRequest::create([
                'user_id' =>$request->user_id?? Auth::id(),
                'document_type_id' => $request->document_type_id,
                'description' => $request->description,
                'status' => 'pending',
                'requested_date' => now(),
            ]);
            
            $hrUsers = User::role(['hr', 'super_admin'])->get();
            
            foreach ($hrUsers as $hrUser) {
                $hrUser->notify(new NewDocumentRequestNotification($documentRequest));
            }
            
            DB::commit();
            
            return ApiResponse::success(
                $documentRequest->load(['user', 'documentType']),
                'Document request submitted successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to submit request: ' . $e->getMessage());
        }
    }

    /**
     * PUT /api/document-requests/{id}
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'document_type_id' => 'sometimes|exists:document_types,id',
            'description' => 'nullable|string',
        ]);
        
        try {
            $documentRequest = DocumentRequest::findOrFail($id);
            $user = Auth::user();
            
            if ($documentRequest->user_id !== $user->id && !$user->hasRole('super_admin')) {
                return ApiResponse::error('You can only edit your own requests', 403);
            }
            
            if ($documentRequest->status !== 'pending') {
                return ApiResponse::error('You cannot edit a request that has already been processed', 422);
            }
            
            $documentRequest->update([
                'document_type_id' => $request->document_type_id ?? $documentRequest->document_type_id,
                'description' => $request->description ?? $documentRequest->description,
            ]);
            
            return ApiResponse::success(
                $documentRequest->load(['user', 'documentType']),
                'Document request updated successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update request: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/document-requests/{id}/approve
     */
    public function approve(Request $request, $id)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);
        
        try {
            DB::beginTransaction();
            
            $documentRequest = DocumentRequest::findOrFail($id);
            
            if ($documentRequest->status !== 'pending') {
                return ApiResponse::error('This request has already been processed', 422);
            }
            
            $file = $request->file('file');
            $path = $file->store("document_requests/{$documentRequest->user_id}", 'public');
            
            $documentRequest->update([
                'hr_user_id' => Auth::id(),
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'file_size' => round($file->getSize() / 1024, 2) . ' KB',
                'status' => 'approved',
                'approved_date' => now(),
            ]);
                    $documentRequest->user?->notify(new DocumentRequestStatusNotification($documentRequest, 'approved'));

            DB::commit();
            
            return ApiResponse::success(
                $documentRequest->load(['user', 'hrUser', 'documentType']),
                'Document request approved successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to approve request: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/document-requests/{id}/reject
     */
    public function reject(Request $request, $id)
    {
        $request->validate([
            'rejection_reason' => 'required|string|min:5|max:500',
        ]);
        
        try {
            $documentRequest = DocumentRequest::findOrFail($id);
            
            if ($documentRequest->status !== 'pending') {
                return ApiResponse::error('This request has already been processed', 422);
            }
            
            $documentRequest->update([
                'hr_user_id' => Auth::id(),
                'status' => 'rejected',
                'rejection_reason' => $request->rejection_reason,
                'rejected_date' => now(),
            ]);
                    $documentRequest->user?->notify(new DocumentRequestStatusNotification($documentRequest, 'rejected'));

            return ApiResponse::success(
                $documentRequest->load(['user', 'hrUser', 'documentType']),
                'Document request rejected successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to reject request: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/document-requests
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $query = DocumentRequest::with(['user', 'hrUser', 'documentType']);
            
            if (!$user->hasRole('super_admin') && !$user->hasRole('hr')) {
                $query->where('user_id', $user->id);
            }
            
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            if ($request->has('document_type_id')) {
                $query->where('document_type_id', $request->document_type_id);
            }
            
            $documentRequests = $query->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 15);
             $documentRequests->getCollection()->transform(function ($item) {
                    if ($item->file_path) {
                        $item->file_url = asset('storage/' . $item->file_path);
                    }
                    return $item;
                });
            return ApiResponse::success($documentRequests, 'Document requests retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve document requests: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/document-requests/{id}
     */
    public function show($id)
    {
        try {
            $user = Auth::user();
            $documentRequest = DocumentRequest::with(['user', 'hrUser', 'documentType'])->findOrFail($id);
            
            if (!$user->hasRole('super_admin') && !$user->hasRole('hr') && $documentRequest->user_id !== $user->id) {
                return ApiResponse::error('Access denied', 403);
            }
            
            if ($documentRequest->file_path) {
                $documentRequest->file_url = asset('storage/' . $documentRequest->file_path);
            }
            
            return ApiResponse::success($documentRequest, 'Document request retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Document request not found', 404);
        }
    }

    /**
     * DELETE /api/document-requests/{id}
     */
    public function destroy($id)
    {
        try {
            $user = Auth::user();
            $documentRequest = DocumentRequest::findOrFail($id);
            
            if ($documentRequest->user_id === $user->id && $documentRequest->status === 'pending') {
                $documentRequest->delete();
                return ApiResponse::success(null, 'Document request cancelled successfully');
            }
            
            if ($user->hasRole('super_admin') || $user->hasRole('hr')) {
                if ($documentRequest->file_path && Storage::disk('public')->exists($documentRequest->file_path)) {
                    Storage::disk('public')->delete($documentRequest->file_path);
                }
                $documentRequest->delete();
                return ApiResponse::success(null, 'Document request deleted successfully');
            }
            
            return ApiResponse::error('Access denied', 403);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete request: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/document-requests/statistics
     */
    public function statistics()
    {
        try {
            $user = Auth::user();
            
            if ($user->hasRole('super_admin') || $user->hasRole('hr')) {
                $stats = [
                    'total' => DocumentRequest::count(),
                    'pending' => DocumentRequest::where('status', 'pending')->count(),
                    'approved' => DocumentRequest::where('status', 'approved')->count(),
                    'rejected' => DocumentRequest::where('status', 'rejected')->count(),
                    'by_type' => DocumentRequest::select('document_types.name', DB::raw('count(*) as count'))
                        ->join('document_types', 'document_requests.document_type_id', '=', 'document_types.id')
                        ->groupBy('document_types.name')
                        ->get(),
                ];
            } else {
                $stats = [
                    'total' => DocumentRequest::where('user_id', $user->id)->count(),
                    'pending' => DocumentRequest::where('user_id', $user->id)->where('status', 'pending')->count(),
                    'approved' => DocumentRequest::where('user_id', $user->id)->where('status', 'approved')->count(),
                    'rejected' => DocumentRequest::where('user_id', $user->id)->where('status', 'rejected')->count(),
                ];
            }
            
            return ApiResponse::success($stats, 'Statistics retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve statistics: ' . $e->getMessage());
        }
    }

   

}