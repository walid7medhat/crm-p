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
use Illuminate\Http\Request;
use App\Models\Lead;
use App\Models\User;
class StageController extends Controller
{
     public function __construct()
    {
        $this->middleware('permission:stages-list', ['only' => []]);
        $this->middleware('permission:stages-create', ['only' => ['store']]);
        $this->middleware('permission:stages-edit', ['only' => ['update', 'reorder']]);
        $this->middleware('permission:stages-delete', ['only' => ['destroy']]);
    }
    /**
     * Get all stages
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $stages = Stage::query();
            if($request->stage_type){
                $stages->where('stage_type',$request->stage_type);
            }else{
                 $stages->where('stage_type','lead');
            }
            
            if($request->deal_type){
                $stages->where('deal_type',$request->deal_type);
            }
            $stages=$stages->orderBy('order')->get();
            return ApiResponse::success(
                new StageCollection($stages),
                'Stages retrieved successfully'
            );
        } catch (\Exception $e) {
            dd($e->getMessage());
             \Log::info('Failed to retrieve stages: ' . $e->getMessage(), [
                'stack' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return ApiResponse::error('Failed to retrieve stages: ' . $e->getMessage());
        }
    }

    /**
     * Create a new stage
     */
    public function store(StageRequest $request): JsonResponse
    {
        try {
            
            $data = $request->validated();

                if (!isset($data['order'])) {
                    $lastOrder = Stage::max('order'); 
                    $data['order'] = $lastOrder ? $lastOrder + 1 : 1;
                }
                
                $stage = Stage::create($data + [
                    'added_by' => auth()->id(),
                ]);

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

public function getStagesWithLeads(Request $request): JsonResponse
{
    try {
        $user = auth()->user();

        $leadsQuery = Lead::with([
            'stage', 
            'addedBy', 
            'responsiblePerson', 
            'participants',
            'observers.user'
        ]);

        // ================= الصلاحيات =================
        if ($user->hasRole('super_admin')) {
        } 
        elseif ($user->hasAnyRole(['manager', 'team_lead','admin'])) {
            $subordinatesIds = $user->getAllSubordinatesIds();

            $leadsQuery->where(function ($q) use ($subordinatesIds, $user) {
                $q->whereIn('responsible_person_id', array_merge($subordinatesIds, [$user->id]))
                  ->orWhereIn('added_by', $subordinatesIds);
            });
            if($user->hasAnyRole(['manager', 'team_lead'])){
                $leadsQuery->whereNull('revert');
            }
            
        } 
        else {
            $leadsQuery->where(function ($q) use ($user) {
                $q->where('responsible_person_id', $user->id)
                  ->orWhere('added_by', $user->id);
            })->whereNull('revert');
        }

        // ================= Lead filters only (stage_id filters both stages and leads when present) =================
        $leadsQuery->where(function ($q) use ($request) {
          if ($request->filled('changed_by') ) {
                  $leadsQuery->whereHas('histories',function($query) use($request){
                            $query->where('changes->action', 'stage_changed')->where('user_id',$request->changed_by);
                  });
              
                }
            if ($request->filled('responsible_person_id')) {
                $q->where('responsible_person_id', $request->responsible_person_id);
            }

            if ($request->filled('stage_id')) {
                $q->where('stage_id', $request->stage_id);
            }
            if ($request->filled('email')) {
                $q->where('email', $request->email);
            }
            if ($request->filled('work_phone')) {
                $q->where('work_phone', $request->work_phone);
            }

            if ($request->filled('added_by')) {
                $q->where('added_by', $request->added_by);
            }

            if ($request->filled('created_from')) {
                $q->whereDate('created_at', '>=', $request->created_from);
            }

            if ($request->filled('created_to')) {
                $q->whereDate('created_at', '<=', $request->created_to);
            }

            if ($request->filled('created_at')) {
                $q->whereDate('created_at',  $request->created_at);
            }
            if ($request->filled('source')) {
                $q->where('lead_source', $request->source);
            }
            if ($request->filled('bedrooms')) {
                $q->where('bedrooms', $request->bedrooms);
            }
            
            if ($request->filled('source_information')) {
                $q->where('source_information', 'like', "%{$request->source_information}%");
            }
             if ($request->filled('closed')) {
                 $closed = Stage::orderBy('order', 'desc')->first();
                $q->where('stage_id', $closed->id);
            }
            if ($request->filled('lead_name')) {
                $q->where('lead_name', 'like', "%{$request->lead_name}%");
            }
            if ($request->filled('first_name')) {
                $q->where('first_name', 'like', "%{$request->first_name}%");
            }
             if ($request->filled('lead_branch_source')) {
                 $branch_team=User::where('id',$request->lead_branch_source)->first();
                 $team=$branch_team->getAllSubordinatesIds();
                $q->whereIn('responsible_person_id',$team);
            }
            if ($request->filled('team_id')) {
                $teamLead = User::find($request->team_id);
                if ($teamLead) {
                    $teamMemberIds = $teamLead->getAllSubordinatesIds();
                    // Include the team lead themselves
                    $teamMemberIds[] = $teamLead->id;
                    $q->whereIn('responsible_person_id', $teamMemberIds);
                }
            }
            if ($request->filled('search')) {
                $search = $request->search;
                $q->where(function ($s) use ($search) {
                    $s->where('lead_name', 'like', "%{$search}%")
                      ->orWhere('lead_number', 'like', "%{$search}%");
                });
            }
        });

        $leads = $leadsQuery
            ->orderBy('created_at', 'desc')
            ->get();

        // ================= Stages: all by default, or only stage_id when in request =================
        $stagesQuery = Stage::where('stage_type','lead')->orderBy('order');
        if ($request->filled('stage_id')) {
            $stagesQuery->where('id', $request->stage_id);
        }
        $stages = $stagesQuery->get();

        // Group filtered leads by stage_id for lookup
        $leadsByStageId = $leads->groupBy('stage_id');

        // ================= Build response: every stage with its filtered leads =================
        $stagesWithLeads = $stages->map(function ($stage) use ($leadsByStageId) {
            $stageLeads = $leadsByStageId->get($stage->id, collect());

            return [
                'id' => $stage->id,
                'name' => $stage->name,
                'order' => $stage->order,
                'color' => $stage->color,
                'lead_count' => $stageLeads->count(),
                'leads' => LeadResource::collection($stageLeads),
                'created_at' => $stage->created_at?->toISOString(),
                'updated_at' => $stage->updated_at?->toISOString(),
            ];
        })->values();

        return ApiResponse::success($stagesWithLeads, 'Stages with filtered leads');

    } catch (\Exception $e) {
        return ApiResponse::error($e->getMessage());
    }
}
public function getTeamsWithLeads(Request $request): JsonResponse
{
    try {
        $user = auth()->user();
        
        if (!$user->hasAnyRole(['super_admin', 'admin', 'manager'])) {
            return ApiResponse::error('Unauthorized', 403);
        }

       
        $teams = User::whereHas('children', function($query) {
            // users who have at least one child/subordinate
        })
        ->whereIn('id',$user->getAllSubordinatesIds())
        ->where('id','!=',auth()->user()->id)
        ->withCount('children') 
        ->get()
        ->map(function($user) {
            $allSubordinates = $user->getAllSubordinatesIds();
            $teamSize = count($allSubordinates) - 1; 
            
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'team_size' => $teamSize,
                'role' => $user->roles->pluck('name')->first()
            ];
        });

        return ApiResponse::success($teams, 'Teams fetched successfully');

    } catch (\Exception $e) {
        return ApiResponse::error($e->getMessage());
    }
}

public function getLeadBranchSource()
{
    $branches = User::role('admin')
        ->whereHas('parent', function ($q) {
            $q->whereNull('parent_id');
        })        
        ->select('name', 'id')
        ->get();

    return ApiResponse::success($branches, 'Lead Branches');
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