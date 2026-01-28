<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Http\Requests\Stage\StageRequest;
use App\Http\Requests\Stage\StageReorderRequest;
use App\Http\Resources\Lead\LeadResource;
use App\Http\Resources\Stage\StageResource;
use App\Http\Resources\Stage\StageCollection;
use App\Models\Stage;
use Illuminate\Http\JsonResponse;
class StageController extends Controller
{
     public function __construct()
    {
        $this->middleware('permission:stages-list', ['only' => ['index', 'show']]);
        $this->middleware('permission:stages-create', ['only' => ['store']]);
        $this->middleware('permission:stages-edit', ['only' => ['update', 'reorder']]);
        $this->middleware('permission:stages-delete', ['only' => ['destroy']]);
    }
    /**
     * Get all stages
     */
    public function index(): JsonResponse
    {
        try {
            $stages = Stage::withCount('leads')->orderBy('order')->get();
            
            return ApiResponse::success(
                new StageCollection($stages),
                'Stages retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve stages: ' . $e->getMessage());
        }
    }

    /**
     * Create a new stage
     */
    public function store(StageRequest $request): JsonResponse
    {
        try {
            $stage = Stage::create($request->validated()+['added_by'=>auth()->user()->id]);

            return ApiResponse::success(
                new StageResource($stage),
                'Stage created successfully', 
                201
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create stage: ' . $e->getMessage());
        }
    }

    /**
     * Get a single stage
     */
    public function show(Stage $stage): JsonResponse
    {
        try {
            $stage->loadCount('leads');
            
            return ApiResponse::success(
                new StageResource($stage),
                'Stage retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve stage: ' . $e->getMessage());
        }
    }

    /**
     * Update a stage
     */
    public function update(StageRequest $request, Stage $stage): JsonResponse
    {
        try {
            $stage->update($request->validated());

            return ApiResponse::success(
                new StageResource($stage),
                'Stage updated successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update stage: ' . $e->getMessage());
        }
    }

    /**
     * Delete a stage
     */
    public function destroy(Stage $stage): JsonResponse
    {
        try {
            $stage->delete();

            return ApiResponse::success(null, 'Stage deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete stage: ' . $e->getMessage());
        }
    }

    /**
     * Reorder stages
     */
    public function reorder(StageReorderRequest $request): JsonResponse
    {
        try {
            foreach ($request->validated()['stages'] as $stageData) {
                Stage::where('id', $stageData['id'])->update(['order' => $stageData['order']]);
            }

            $stages = Stage::withCount('leads')->orderBy('order')->get();

            return ApiResponse::success(
                new StageCollection($stages),
                'Stages reordered successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to reorder stages: ' . $e->getMessage());
        }
    }

      public function getStagesWithLeads(): JsonResponse
    {
        try {
            $user = auth()->user();
            
            $stages = Stage::with(['leads' => function($query) use ($user) {
                // Apply lead visibility based on user role
                if (($user->hasRole('super_admin') )) {
                    // Admin sees all leads
                } 
                elseif ($user->hasAnyRole(['manager', 'team_lead','admin'])) {
                    $subordinatesIds = $user->getAllSubordinatesIds();
                    
                    $query->where(function($q) use ($subordinatesIds, $user) {
                        $q->whereIn('responsible_person_id', $subordinatesIds)
                          ->orWhereIn('added_by', $subordinatesIds)
                          ->orWhere('responsible_person_id', $user->id);
                    });
                }
                else {
                    $query->where(function($q) use ($user) {
                        $q->where('responsible_person_id', $user->id)
                          ->orWhere('added_by', $user->id);
                    });
                }
                
                $query->with(['responsiblePerson:id,name,email', 'addedBy:id,name'])
                      ->orderBy('created_at', 'desc');
            }])
            ->withCount('leads')
            ->orderBy('order')
            ->get();

            return ApiResponse::success(
                StageResource::collection($stages),
                'Stages with leads retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve stages with leads: ' . $e->getMessage());
        }
    }

    /**
     * Get leads by stage for Kanban board
     */
    public function getLeadsByStage(Stage $stage): JsonResponse
    {
        try {
            $user = auth()->user();
            
            $leadsQuery = $stage->leads()->with(['responsiblePerson:id,name,email', 'addedBy:id,name']);
            
            // Apply lead visibility based on user role
            if (!($user->hasRole('super_admin') || $user->hasRole('admin'))) {
                if ($user->hasRole(['manager', 'team_lead'])) {
                    $subordinatesIds = $user->getAllSubordinatesIds();
                    
                    $leadsQuery->where(function($query) use ($subordinatesIds, $user) {
                        $query->whereIn('responsible_person_id', $subordinatesIds)
                              ->orWhereIn('added_by', $subordinatesIds)
                              ->orWhere('responsible_person_id', $user->id);
                    });
                } else {
                    $leadsQuery->where(function($query) use ($user) {
                        $query->where('responsible_person_id', $user->id)
                              ->orWhere('added_by', $user->id);
                    });
                }
            }
            
            $leads = $leadsQuery->orderBy('created_at', 'desc')->get();

            return ApiResponse::success(
                LeadResource::collection($leads),
                'Leads for stage retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve leads for stage: ' . $e->getMessage());
        }
    }
}