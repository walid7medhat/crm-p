<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Http\Requests\Stage\StageRequest;
use App\Http\Requests\Stage\StageReorderRequest;
use App\Http\Resources\Lead\KanbanLeadCardResource;
use App\Http\Resources\Lead\LeadResource;
use App\Http\Resources\Stage\StageResource;
use App\Http\Resources\Stage\StageCollection;
use App\Models\Area;
use App\Models\Stage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Lead;
use App\Models\User;
use DB;
use Illuminate\Support\Facades\Schema;
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
            // dd($e->getMessage());
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
            $perPage = $request->get('per_page', 20);

                 $userRole = $user->roles()->first()?->name ?? 'sales';
        
        // ================= جلب الـ stages المسموح بها لهذا الدور =================
        $stageVisibility = DB::table('stage_visibility')
            ->where('role_name', $userRole)
            ->first();
        
        if ($stageVisibility) {
            $decoded = json_decode($stageVisibility->visible_stages, true);
            $visibleStageIds = is_array($decoded) ? array_values(array_filter($decoded)) : [];
        } else {
            $visibleStageIds = [];
        }

        // Fallback: if role visibility is empty/invalid, show all lead stages
        if (empty($visibleStageIds)) {
            $visibleStageIds = Stage::where('stage_type', 'lead')->pluck('id')->toArray();
        }

        // ================= stages =================
        $stagesQuery = Stage::where('stage_type', 'lead')
         ->where('name', '!=', 'lead pool')
            ->whereIn('id', $visibleStageIds) 
            ->orderBy('order');
            if ($request->filled('stage_id')) {
                $stagesQuery->where('id', $request->stage_id);
            }
            $stages = $stagesQuery->get();

            // ================= leads query with permissions =================
            $baseLeadsQuery = Lead::query();
            $kanbanEagerLoads = [
                'addedBy:id,name,display_name,avatar',
                'responsiblePerson:id,name,display_name,avatar',
                'propertyType:id,name',
                'area:id,name',
            ];

            // ================= permissions =================
            if ($user->hasRole('super_admin')  || auth()->user()->id ==30 || auth()->user()->id ==33) {
                // super admin sees everything
            } 
            elseif ($user->hasAnyRole(['manager', 'team_lead', 'admin'])) {
                $subordinatesIds = $user->getAllSubordinatesIds();
                $baseLeadsQuery->where(function ($q) use ($subordinatesIds, $user) {
                    $q->whereIn('responsible_person_id', array_merge($subordinatesIds, [$user->id]))
                      ->orWhereIn('added_by', $subordinatesIds);
                });
                if ($user->hasAnyRole(['manager', 'team_lead'])) {
                    $baseLeadsQuery->whereNull('revert');
                }
            } 
            else {
                $baseLeadsQuery->where(function ($q) use ($user) {
                    $q->where('responsible_person_id', $user->id)
                      ->orWhere('added_by', $user->id);
                })->whereNull('revert');
            }

            // ================= apply all filters =================
            $baseLeadsQuery->where(function ($q) use ($request) {
                if ($request->filled('changed_by')) {
                    $q->whereHas('histories', function($query) use($request) {
                        $query->where('changes->action', 'stage_changed')
                              ->where('user_id', $request->changed_by);
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
                    $q->where(function ($query) use ($request) {
                        $query->where('work_phone', $request->work_phone)
                              ->orWhere('work_phone_2', $request->work_phone);
                    });
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
                    $q->whereDate('created_at', '=', $request->created_at);
                }
                $this->applyLeadSourceFilter($q, $request);
                if ($request->filled('bedrooms')) {
                    $q->where('bedrooms', $request->bedrooms);
                }
                if ($request->filled('source_information')) {
                    $q->where('source_information', 'like', "%{$request->source_information}%");
                }
                if ($request->filled('closed')) {
                    $closed = Stage::where('stage_type', 'lead')
                        ->where('name', 'like', '%Converted%')
                        ->orderBy('order', 'desc')
                        ->first();
                    $q->where('stage_id', $closed->id);
                }
                if ($request->filled('lead_name')) {
                    $q->where('lead_name', 'like', "%{$request->lead_name}%");
                }
                if ($request->filled('first_name')) {
                    $q->where('first_name', 'like', "%{$request->first_name}%");
                }
                if ($request->filled('lead_branch_source')) {
                     $q->where('lead_branch_source', $request->lead_branch_source);
                }
                if ($request->filled('office_branch')) {
                    $officeBranches = $request->office_branch;
                    
                    // Convert to array if it's a string (coming as comma-separated)
                    if (is_string($officeBranches)) {
                        $officeBranches = explode(',', $officeBranches);
                    }
                    
                    // If it's an array and not empty
                    if (is_array($officeBranches) && count($officeBranches) > 0) {
                        $allTeamMembers = [];
                        
                        foreach ($officeBranches as $officeBranchId) {
                            // تأكد من أن $officeBranchId هو رقم صحيح
                            if (is_numeric($officeBranchId)) {
                                $branchTeam = User::find($officeBranchId);
                                if ($branchTeam) {
                                    // استدعاء الدالة على الـ Model وليس Collection
                                    $teamMembers = $branchTeam->getAllSubordinatesIds();
                                    if (is_array($teamMembers)) {
                                        $allTeamMembers = array_merge($allTeamMembers, $teamMembers);
                                    }
                                    // Also add the branch lead themselves
                                    $allTeamMembers[] = $branchTeam->id;
                                }
                            }
                        }
                        
                        // Remove duplicates
                        $allTeamMembers = array_unique($allTeamMembers);
                        
                        if (!empty($allTeamMembers)) {
                            $q->whereIn('responsible_person_id', $allTeamMembers);
                        }
                    }
                    // Handle single office_branch for backward compatibility
                    elseif (is_numeric($officeBranches)) {
                        $branchTeam = User::find($officeBranches);
                        if ($branchTeam) {
                            $teamMembers = $branchTeam->getAllSubordinatesIds();
                            if (is_array($teamMembers)) {
                                $teamMembers[] = $branchTeam->id;
                                $q->whereIn('responsible_person_id', $teamMembers);
                            }
                        }
                    }
                }
                if ($request->filled('team_id')) {
                    $teamLead = User::find($request->team_id);
                    if ($teamLead) {
                        $teamMemberIds = $teamLead->getAllSubordinatesIds();
                        $teamMemberIds[] = $teamLead->id;
                        $q->whereIn('responsible_person_id', $teamMemberIds);
                    }
                }
               if ($request->filled('status_lead')) {
                    $q->where('status_lead', $request->status_lead);
                }
               if ($request->filled('why_lost_lead')) {
                    $q->where('why_lost_lead', $request->why_lost_lead);
                }


                // Lead Type
                if ($request->filled('lead_type') && $request->lead_type !='both') {
                    $q->where(function($q) use ($request) {
                        $q->where('lead_type', $request->lead_type)
                        ->orWhere('lead_type', 'both');
                    });
                }

                // Property Status
                if ($request->filled('property_status') && $request->property_status !='both') {
                    $q->where(function($q) use ($request) {
                        $q->where('property_status', $request->property_status)
                        ->orWhere('property_status', 'both');
                    });
                }
                
                $this->applyBudgetRangeFilter($q, $request);
                
                // Filter by Property Type ID
                if ($request->filled('property_type_id')) {
                    $q->where('property_type_id', $request->property_type_id);
                }
                 if ($request->has('area_id')) {
                    $areaId = $request->area_id;
                    $area = Area::find($areaId);
                
                    if ($area) {
                        $childAreaIds = $area->getChildIdsAttribute();
                        $allAreaIds = array_merge([$areaId], $childAreaIds);
                
                        $q->where(function ($qq) use ($allAreaIds) {
                            $qq->whereIn('area_id', $allAreaIds)
                             ;
                        });
                    }
                }
                if ($request->filled('interaction_result')) {
                    $q->where('interaction_result', $request->interaction_result);
                }
                if ($request->filled('purpose_buying')) {
                    $q->where('purpose_buying', $request->purpose_buying);
                }
                if ($request->filled('assigned_from') || $request->filled('assigned_to') || $request->filled('assigned_at')) {
                    $assignedFrom = $request->assigned_from;
                    $assignedTo = $request->assigned_to;
                    $assignedDate = $request->assigned_at;
                    $q->whereHas('histories', function($query) use ($assignedFrom, $assignedTo, $assignedDate) {
                        $query->where('changes->action', 'assigned');
                        if ($assignedDate) {
                            $query->whereDate('created_at', $assignedDate);
                        } else {
                            if ($assignedFrom) {
                                $query->whereDate('created_at', '>=', $assignedFrom);
                            }
                            if ($assignedTo) {
                                $query->whereDate('created_at', '<=', $assignedTo);
                            }
                        }
                    });
                }
                if ($request->filled('search')) {
                    $search = $request->search;
                        $websitePartials = ['website', 'Allproperties.ae', 'Oiaproperties.com'];
                        $portalPartials  = ['portal', 'propertyfinder', 'bayut'];
                    
                        if ($search === 'website') {
                            $expanded = $websitePartials;
                        } elseif ($search === 'portal') {
                            $expanded = $portalPartials;
                        } else {
                            $expanded = [$search];
                        }

                    $q->where(function ($s) use ($search) {
                        $s->where('lead_name', 'like', "%{$search}%")
                          ->orWhere('lead_number', 'like', "%{$search}%")
                          ->orWhere('first_name', 'like', "%{$search}%")
                          ->orWhere('last_name', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%")
                          ->orWhere('work_phone', 'like', "%{$search}%")
                          ->orWhere('bedrooms', 'like', "%{$search}%")
                          ->orWhere('work_phone_2', 'like', "%{$search}%")
                          ->orWhere('lead_source', 'like', "%{$search}%")
                          ->orWhere('status_lead', 'like', "%{$search}%")
                          // Chip vocabulary searchable as plain text — typing "hot"/"cold"/"warm"
                          // or "answered"/"no_answer" matches the AI priority / call-result columns.
                        //   ->orWhere('priority', 'like', "%{$search}%")
                          ->orWhere('interaction_result', 'like', "%{$search}%")
                          ->orWhere('more_information', 'like', "%{$search}%")
                          ->orWhere(function($q2) use ($search) {
                                $q2->where('lead_type', 'like', "%{$search}%")
                                    ->orWhere('lead_type', 'both');
                            })
                            ->orWhere(function($q2) use ($search) {
                                $q2->where('property_status', 'like', "%{$search}%")
                                    ->orWhere('property_status', 'both');
                            })

                          ->orWhere('budget_from', 'like', "%{$search}%")
                          ->orWhere('budget_to', 'like', "%{$search}%")
                          ->orWhere('source_information', 'like', "%{$search}%")
                           ->orWhere('purpose_buying', 'like', "%{$search}%")
                          ->orWhere('budget', 'like', "%{$search}%")
                          ->orWhereHas('responsiblePerson', function ($r) use ($search) {
                              $r->where('name', 'like', "%{$search}%");
                          })
                           ->orWhereHas('propertyType', function ($pt) use ($search) {
                                  $pt->where('name', 'like', "%{$search}%");
                              })
                          ->orWhereHas('stage', function ($st) use ($search) {
                              $st->where('name', 'like', "%{$search}%");
                          })
                          ->orWhereHas('integration', function ($st) use ($search) {
                              $st->where('track_keyword', 'like', "%{$search}%");
                          });
                          if (!empty($expanded)) {
                                $s->orWhere(function ($exp) use ($expanded) {
                                    foreach ($expanded as $term) {
                                        $exp->orWhere('lead_source', 'like', "%{$term}%")
                                            ->orWhere('more_information', 'like', "%{$term}%");
                                    }
                                });
                            }
                    });
                }
            });

            // ================= analytics counts (chip totals) =================
            // Snapshot the query BEFORE the temperature chip filter is applied so the
            // Cold/Warm/Hot/Answered/No-answer chip counts reflect what's available
            // across the whole filtered set — not just the currently-loaded page, and
            // not the subset already narrowed by the active chip. Mirrors the OR logic
            // of `normalizeLeadHeat` on the frontend.
            $analyticsBaseQuery = clone $baseLeadsQuery;
            $heatCounts = (clone $analyticsBaseQuery)
            // OR priority = 'cold',OR priority = 'warm', OR priority = 'hot' 
                ->selectRaw(
                    "SUM(CASE WHEN status_lead = 'cold' THEN 1 ELSE 0 END) AS temp_cold,
                     SUM(CASE WHEN status_lead = 'warm'  THEN 1 ELSE 0 END) AS temp_warm,
                     SUM(CASE WHEN status_lead = 'hot'  THEN 1 ELSE 0 END) AS temp_hot,
                     SUM(CASE WHEN interaction_result = 'answered'  THEN 1 ELSE 0 END) AS call_answered,
                     SUM(CASE WHEN interaction_result = 'no_answer' THEN 1 ELSE 0 END) AS call_no_answer"
                )
                ->first();
            $leadAnalytics = [
                'tempCold'     => (int) ($heatCounts->temp_cold ?? 0),
                'tempWarm'     => (int) ($heatCounts->temp_warm ?? 0),
                'tempHot'      => (int) ($heatCounts->temp_hot ?? 0),
                'callAnswered' => (int) ($heatCounts->call_answered ?? 0),
                'callNoAnswer' => (int) ($heatCounts->call_no_answer ?? 0),
            ];

            // Now apply the temperature chip filter — affects stage counts, leads list,
            // and pagination, but NOT the analytics snapshot taken above.
            if ($request->filled('heat')) {
                $heat = $request->heat;
                $baseLeadsQuery->where(function ($qq) use ($heat) {
                    $qq->where('status_lead', $heat);
                    //    ->orWhere('priority', $heat);
                });
            }

            // ================= leads per stage (one count query + limit per stage) =================
            $stageIds = $stages->pluck('id')->all();
            $leadCountsByStage = collect();
            if (! empty($stageIds)) {
                $leadCountsByStage = (clone $baseLeadsQuery)
                    ->select('stage_id', DB::raw('COUNT(*) as aggregate'))
                    ->whereIn('stage_id', $stageIds)
                    ->groupBy('stage_id')
                    ->pluck('aggregate', 'stage_id');
            }

            $stagesWithLeads = [];
            $allLeadsForMeta = collect();

            foreach ($stages as $stage) {
                $stageLeadsQuery = (clone $baseLeadsQuery)
                    ->with($kanbanEagerLoads)
                    ->where('stage_id', $stage->id);
                if($stage->order==1){
                $stageLeadsQuery
                    ->orderBy('created_at', 'desc')
                    ->orderBy('id', 'desc');
                }else{
                     $stageLeadsQuery
                    ->orderBy('updated_at', 'desc')
                    ->orderBy('id', 'desc');
                }

                $total = (int) ($leadCountsByStage[$stage->id] ?? 0);
                $leads = $stageLeadsQuery->limit($perPage)->get();
                $allLeadsForMeta = $allLeadsForMeta->merge($leads);

                $stagesWithLeads[] = [
                    'id' => $stage->id,
                    'name' => $stage->name,
                    'order' => $stage->order,
                    'color' => $stage->color,
                    'lead_count' => $total,
                    'leads' => $leads,
                    'pagination' => [
                        'current_page' => 1,
                        'last_page' => max(1, (int) ceil($total / max(1, $perPage))),
                        'per_page' => $perPage,
                        'total' => $total,
                        'has_more_pages' => $total > $perPage,
                    ],
                    'created_at' => $stage->created_at?->toISOString(),
                    'updated_at' => $stage->updated_at?->toISOString(),
                ];
            }

            $duplicateCounts = $this->kanbanDuplicateCountsByWorkPhone($allLeadsForMeta);
            $serviceDupFlags = $this->kanbanServiceDuplicateFlags($allLeadsForMeta);
            KanbanLeadCardResource::setKanbanMeta($duplicateCounts, $serviceDupFlags);
            KanbanLeadCardResource::setKanbanActivityUsersByBitrixId(
                $this->kanbanActivityUsersForLeads($allLeadsForMeta)
            );

            foreach ($stagesWithLeads as &$stageRow) {
                $stageRow['leads'] = KanbanLeadCardResource::collection($stageRow['leads'])->resolve();
            }
            unset($stageRow);

            KanbanLeadCardResource::clearKanbanMeta();
            KanbanLeadCardResource::clearKanbanActivityUsers();

            return ApiResponse::success([
                'stages' => $stagesWithLeads,
                'analytics' => $leadAnalytics,
                'pagination' => [
                    'current_page' => $request->get('page', 1),
                    'per_page' => $perPage,
                    'total_stages' => count($stages)
                ]
            ], 'Stages with paginated leads retrieved successfully');

        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage());
        }
    }

    /**
     * Load more leads for a specific stage (infinite scroll)
     */
    public function getMoreStageLeads(Request $request, Stage $stage): JsonResponse
    {
        try {
            $user = auth()->user();
            $perPage = $request->get('per_page', 20);
            $page = $request->get('page', 1);

            $leadsQuery = $stage->leads()->with([
                'addedBy:id,name,display_name,avatar',
                'responsiblePerson:id,name,display_name,avatar',
                'propertyType:id,name',
                'area:id,name',
            ]);

            if ($user->hasRole('super_admin')  || auth()->user()->id ==30 || auth()->user()->id ==33) {
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

            // ================= نفس الفلاتر =================
            if ($request->filled('changed_by')) {
                $leadsQuery->whereHas('histories', function($query) use($request) {
                    $query->where('changes->action', 'stage_changed')
                          ->where('user_id', $request->changed_by);
                });
            }
            if ($request->filled('responsible_person_id')) {
                $leadsQuery->where('responsible_person_id', $request->responsible_person_id);
            }
            if ($request->filled('email')) {
                $leadsQuery->where('email', $request->email);
            }
            if ($request->filled('work_phone')) {
                $leadsQuery->where(function ($query) use ($request) {
                    $query->where('work_phone', $request->work_phone)
                          ->orWhere('work_phone_2', $request->work_phone);
                });
            }
            if ($request->filled('added_by')) {
                $leadsQuery->where('added_by', $request->added_by);
            }
            if ($request->filled('created_from')) {
                $leadsQuery->whereDate('created_at', '>=', $request->created_from);
            }
            if ($request->filled('created_to')) {
                $leadsQuery->whereDate('created_at', '<=', $request->created_to);
            }
            if ($request->filled('created_at')) {
                $leadsQuery->whereDate('created_at', '=', $request->created_at);
            }
            $this->applyLeadSourceFilter($leadsQuery, $request);
            if ($request->filled('bedrooms')) {
                $leadsQuery->where('bedrooms', $request->bedrooms);
            }
            if ($request->filled('source_information')) {
                $leadsQuery->where('source_information', 'like', "%{$request->source_information}%");
            }
            if ($request->filled('lead_name')) {
                $leadsQuery->where('lead_name', 'like', "%{$request->lead_name}%");
            }
            if ($request->filled('first_name')) {
                $leadsQuery->where('first_name', 'like', "%{$request->first_name}%");
            }
            if ($request->filled('lead_branch_source')) {
                   
                    $leadsQuery->where('lead_branch_source', $request->lead_branch_source);
                }
            if ($request->filled('office_branch')) {
                    $officeBranches = $request->office_branch;
                    
                    // Convert to array if it's a string (coming as comma-separated)
                    if (is_string($officeBranches)) {
                        $officeBranches = explode(',', $officeBranches);
                    }
                    
                    // If it's an array and not empty
                    if (is_array($officeBranches) && count($officeBranches) > 0) {
                        $allTeamMembers = [];
                        
                        foreach ($officeBranches as $officeBranchId) {
                            // تأكد من أن $officeBranchId هو رقم صحيح
                            if (is_numeric($officeBranchId)) {
                                $branchTeam = User::find($officeBranchId);
                                if ($branchTeam) {
                                    // استدعاء الدالة على الـ Model وليس Collection
                                    $teamMembers = $branchTeam->getAllSubordinatesIds();
                                    if (is_array($teamMembers)) {
                                        $allTeamMembers = array_merge($allTeamMembers, $teamMembers);
                                    }
                                    // Also add the branch lead themselves
                                    $allTeamMembers[] = $branchTeam->id;
                                }
                            }
                        }
                        
                        // Remove duplicates
                        $allTeamMembers = array_unique($allTeamMembers);
                        
                        if (!empty($allTeamMembers)) {
                            $leadsQuery->whereIn('responsible_person_id', $allTeamMembers);
                        }
                    }
                    // Handle single office_branch for backward compatibility
                    elseif (is_numeric($officeBranches)) {
                        $branchTeam = User::find($officeBranches);
                        if ($branchTeam) {
                            $teamMembers = $branchTeam->getAllSubordinatesIds();
                            if (is_array($teamMembers)) {
                                $teamMembers[] = $branchTeam->id;
                                $leadsQuery->whereIn('responsible_person_id', $teamMembers);
                            }
                        }
                    }
                }

            if ($request->filled('team_id')) {
                $teamLead = User::find($request->team_id);
                if ($teamLead) {
                    $teamMemberIds = $teamLead->getAllSubordinatesIds();
                    $teamMemberIds[] = $teamLead->id;
                    $leadsQuery->whereIn('responsible_person_id', $teamMemberIds);
                }
            }
               if ($request->filled('status_lead')) {
                    $leadsQuery->where('status_lead', $request->status_lead);
                }
               if ($request->filled('why_lost_lead')) {
                    $leadsQuery->where('why_lost_lead', $request->why_lost_lead);
                }

                // Temperature shortcut chip — match status_lead OR priority (see note above).
                if ($request->filled('heat')) {
                    $heat = $request->heat;
                    $leadsQuery->where(function ($qq) use ($heat) {
                        $qq->where('status_lead', $heat);
                        //    ->orWhere('priority', $heat);
                    });
                }
                
               
                // Lead Type
                if ($request->filled('lead_type') && $request->lead_type !='both') {
                    $leadsQuery->where(function($q) use ($request) {
                        $q->where('lead_type', $request->lead_type)
                        ->orWhere('lead_type', 'both'); 
                    });
                }

                // Property Status
                if ($request->filled('property_status') && $request->property_status !='both') {
                    $leadsQuery->where(function($q) use ($request) {
                        $q->where('property_status', $request->property_status)
                        ->orWhere('property_status', 'both');
                    });
                }
                
                $this->applyBudgetRangeFilter($leadsQuery, $request);
                
                // Filter by Property Type ID
                if ($request->filled('property_type_id')) {
                    $leadsQuery->where('property_type_id', $request->property_type_id);
                }
                 if ($request->has('area_id')) {
                    $areaId = $request->area_id;
                    $area = Area::find($areaId);
                
                    if ($area) {
                        $childAreaIds = $area->getChildIdsAttribute();
                        $allAreaIds = array_merge([$areaId], $childAreaIds);
                
                        $leadsQuery->where(function ($q) use ($allAreaIds) {
                            $q->whereIn('area_id', $allAreaIds)
                             ;
                        });
                    }
                }
                if ($request->filled('interaction_result')) {
                    $leadsQuery->where('interaction_result', $request->interaction_result);
                }
                if ($request->filled('purpose_buying')) {
                    $leadsQuery->where('purpose_buying', $request->purpose_buying);
                }
                if ($request->filled('assigned_from') || $request->filled('assigned_to') || $request->filled('assigned_at')) {
                    $assignedFrom = $request->assigned_from;
                    $assignedTo = $request->assigned_to;
                    $assignedDate = $request->assigned_at;
                    $leadsQuery->whereHas('histories', function($query) use ($assignedFrom, $assignedTo, $assignedDate) {
                        $query->where('changes->action', 'assigned');
                        if ($assignedDate) {
                            $query->whereDate('created_at', $assignedDate);
                        } else {
                            if ($assignedFrom) {
                                $query->whereDate('created_at', '>=', $assignedFrom);
                            }
                            if ($assignedTo) {
                                $query->whereDate('created_at', '<=', $assignedTo);
                            }
                        }
                    });
                }
                if ($request->filled('search')) {
                    $search = $request->search;
                    
                        $websitePartials = ['website', 'Allproperties.ae', 'Oiaproperties.com'];
                        $portalPartials  = ['portal', 'propertyfinder', 'bayut'];
                    
                        if ($search === 'website') {
                            $expanded = $websitePartials;
                        } elseif ($search === 'portal') {
                            $expanded = $portalPartials;
                        } else {
                            $expanded = [$search];
                        }

                    $leadsQuery->where(function ($s) use ($search, $expanded) {
                        $s->where('lead_name', 'like', "%{$search}%")
                          ->orWhere('lead_number', 'like', "%{$search}%")
                          ->orWhere('first_name', 'like', "%{$search}%")
                          ->orWhere('last_name', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%")
                          ->orWhere('work_phone', 'like', "%{$search}%")
                          ->orWhere('bedrooms', 'like', "%{$search}%")
                          ->orWhere('work_phone_2', 'like', "%{$search}%")
                          ->orWhere('lead_source', 'like', "%{$search}%")
                          ->orWhere('status_lead', 'like', "%{$search}%")
                          // Chip vocabulary searchable as plain text — typing "hot"/"cold"/"warm"
                          // or "answered"/"no_answer" matches the AI priority / call-result columns.
                        //   ->orWhere('priority', 'like', "%{$search}%")
                          ->orWhere('interaction_result', 'like', "%{$search}%")
                          ->orWhere('more_information', 'like', "%{$search}%")
                           ->orWhere(function($q2) use ($search) {
                                $q2->where('lead_type', 'like', "%{$search}%")
                                    ->orWhere('lead_type', 'both');
                            })
                            ->orWhere(function($q2) use ($search) {
                                $q2->where('property_status', 'like', "%{$search}%")
                                    ->orWhere('property_status', 'both');
                            })

                          ->orWhere('budget_from', 'like', "%{$search}%")
                          ->orWhere('budget_to', 'like', "%{$search}%")
                          ->orWhere('source_information', 'like', "%{$search}%")
                          ->orWhere('budget', 'like', "%{$search}%")
                          ->orWhere('purpose_buying', 'like', "%{$search}%")
                          ->orWhereHas('responsiblePerson', function ($r) use ($search) {
                              $r->where('name', 'like', "%{$search}%");
                          })
                           ->orWhereHas('propertyType', function ($pt) use ($search) {
                                  $pt->where('name', 'like', "%{$search}%");
                              })
                          ->orWhereHas('stage', function ($st) use ($search) {
                              $st->where('name', 'like', "%{$search}%");
                          })
                          ->orWhereHas('integration', function ($st) use ($search) {
                              $st->where('track_keyword', 'like', "%{$search}%");
                          });
                          if (!empty($expanded)) {
                                $s->orWhere(function ($exp) use ($expanded) {
                                    foreach ($expanded as $term) {
                                        $exp->orWhere('lead_source', 'like', "%{$term}%")
                                            ->orWhere('more_information', 'like', "%{$term}%");
                                    }
                                });
                            }
                    });
                }
             // ================= pagination =================
                if($stage->order==1){
                $leadsQuery
                    ->orderBy('created_at', 'desc')
                    ->orderBy('id', 'desc');
                }else{
                     $leadsQuery
                    ->orderBy('updated_at', 'desc')
                    ->orderBy('id', 'desc');
                }
                    
                    
                        $paginatedLeads = $leadsQuery->paginate($perPage, ['*'], 'page', $page);

            // ================= pagination =================
            // $paginatedLeads = $leadsQuery
            //     // ->when(
            //     //     Schema::hasColumn('leads', 'score'),
            //     //     fn ($q) => $q->orderByDesc('score')->orderBy('created_at', 'desc'),
            //     //     fn ($q) => $q->orderBy('created_at', 'desc')
            //     // )
            //     ->orderBy('updated_at', 'desc')
            //     ->paginate($perPage, ['*'], 'page', $page);

            $leadsCollection = $paginatedLeads->getCollection();
            $duplicateCounts = $this->kanbanDuplicateCountsByWorkPhone($leadsCollection);
            $serviceDupFlags = $this->kanbanServiceDuplicateFlags($leadsCollection);
            KanbanLeadCardResource::setKanbanMeta($duplicateCounts, $serviceDupFlags);
            KanbanLeadCardResource::setKanbanActivityUsersByBitrixId(
                $this->kanbanActivityUsersForLeads($leadsCollection)
            );

            $leadsPayload = KanbanLeadCardResource::collection($leadsCollection)->resolve();

            KanbanLeadCardResource::clearKanbanMeta();
            KanbanLeadCardResource::clearKanbanActivityUsers();

            return ApiResponse::success([
                'stage_id' => $stage->id,
                'leads' => $leadsPayload,
                'pagination' => [
                    'current_page' => $paginatedLeads->currentPage(),
                    'last_page' => $paginatedLeads->lastPage(),
                    'per_page' => $paginatedLeads->perPage(),
                    'total' => $paginatedLeads->total(),
                    'has_more_pages' => $paginatedLeads->hasMorePages()
                ]
            ], 'More leads retrieved successfully');

        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage());
        }
    }
// public function getTeamsWithLeads(Request $request): JsonResponse
// {
//     try {
//         $user = auth()->user();
        
//         if (!$user->hasAnyRole(['super_admin', 'admin', 'manager'])) {
//             return ApiResponse::error('Unauthorized', 403);
//         }

       
//         $teams = User::whereHas('children', function($query) {
//             // users who have at least one child/subordinate
//         })
//         ->whereIn('id',$user->getAllSubordinatesIds())
//         ->where('id','!=',auth()->user()->id)
//         ->withCount('children') 
//         ->get()
//         ->map(function($user) {
//             $allSubordinates = $user->getAllSubordinatesIds();
//             $teamSize = count($allSubordinates) - 1; 
            
//             return [
//                 'id' => $user->id,
//                 'name' => $user->name,
//                 'email' => $user->email,
//                 'team_size' => $teamSize,
//                 'role' => $user->roles->pluck('name')->first()
//             ];
//         });

//         return ApiResponse::success($teams, 'Teams fetched successfully');

//     } catch (\Exception $e) {
//         return ApiResponse::error($e->getMessage());
//     }
// }
public function getTeamsWithLeads(Request $request): JsonResponse
{
    try {
        $user = auth()->user();
        
        // Handle multiple office IDs
        $officeIds = $request->input('office_ids');
        $officeAndDescendants = [];
        
        if ($officeIds) {
            // Convert to array if it's a string (coming as comma-separated)
            if (is_string($officeIds)) {
                $officeIds = explode(',', $officeIds);
            }
            
            // If it's an array of IDs
            if (is_array($officeIds) && count($officeIds) > 0) {
                foreach ($officeIds as $officeId) {
                    $selectedOffice = User::find($officeId);
                    if ($selectedOffice) {
                        // Get all users under this office (including descendants)
                        $officeDescendants = $selectedOffice->getAllSubordinatesIds();
                        $officeAndDescendants = array_merge($officeAndDescendants, $officeDescendants);
                    }
                }
                // Remove duplicates
                $officeAndDescendants = array_unique($officeAndDescendants);
            } 
            // Handle single office_id for backward compatibility
            elseif (is_numeric($officeIds)) {
                $selectedOffice = User::find($officeIds);
                if ($selectedOffice) {
                    $officeAndDescendants = $selectedOffice->getAllSubordinatesIds();
                }
            }
        }
        
        // Also check for single office_id for backward compatibility
        $singleOfficeId = $request->input('office_id');
        if ($singleOfficeId && empty($officeAndDescendants)) {
            $selectedOffice = User::find($singleOfficeId);
            if ($selectedOffice) {
                $officeAndDescendants = $selectedOffice->getAllSubordinatesIds();
            }
        }
        
        if (!$user->hasAnyRole(['super_admin', 'admin', 'manager'])) {
            return ApiResponse::error('Unauthorized', 403);
        }

        $query = User::whereHas('children', function($query) {
            // users who have at least one child/subordinate
        })
        ->whereIn('id', $user->getAllSubordinatesIds())
        ->where('id', '!=', auth()->user()->id)->whereHas('roles');
        
        // Filter by offices if provided (now supports multiple)
        if (!empty($officeAndDescendants)) {
            $query->whereIn('id', $officeAndDescendants);
        }
        
        $teams = $query->withCount('children')
            ->get(['id', 'name', 'display_name', 'email', 'parent_id'])
            ->map(function($user) {
                $allSubordinates = $user->getAllSubordinatesIds();
                $teamSize = count($allSubordinates) - 1;
                
                // Get the admin parent (branch/office) for this team
                $adminParent = $user->getAdminParentAttribute();
                
                return [
                    'id' => $user->id,
                   'name' =>User::resolveDisplayName($user),
                    'email' => $user->email,
                    'team_size' => $teamSize,
                    'role' => $user->roles->pluck('name')->first(),
                    'parent_id' => $user->parent_id,
                    'admin_parent_id' => $adminParent ? $adminParent->id : null,
                    'admin_parent_name' => $adminParent ? User::resolveDisplayName($adminParent) : null,
                    'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                    'role_name' => $user->roles()->first()?->name,
                       'team_id'=>$user->parent?->id,
                       'parent_name' => User::resolveDisplayName($user->parent),
                        'branch_id' => $user->office?->id,

                        'branch_name' => User::resolveDisplayName($user->office)
                ];
            });

        return ApiResponse::success($teams, 'Teams fetched successfully');

    } catch (\Exception $e) {
        return ApiResponse::error($e->getMessage());
    }
}


private function getBranchIds()
{
    $query = User::role('admin')
        ->whereHas('parent', function ($q) {
            $q->whereNull('parent_id');
        });

    if (!auth()->user()->hasRole('super_admin')) {
        $query->where('id', auth()->user()->admin_parent?->id);
    }

    return $query->pluck('id')->toArray();
}
public function getLeadBranchSource()
{
    $query = User::role('admin')
        ->whereHas('parent', function ($q) {
            $q->whereNull('parent_id');
        });

    if (!auth()->user()->hasRole('super_admin')) {
        $query->where('id', auth()->user()->admin_parent?->id);
    }

    $branches = $query->select('id', 'name')->get();

    return ApiResponse::success($branches, 'Lead Branches');
}
public function getOffices()
{
    $branches = $this->getBranchIds();

    $offices = User::role('admin')
        ->whereNotNull('parent_id')
        ->whereIn('parent_id', $branches)
        ->with('parent')
        ->select('id', 'name', 'parent_id')
        ->get()
        ->map(function ($office) {
            return [
                'id' => $office->id,
                'name' => $office->name,
                'parent_id' => $office->parent_id,
                'admin_parent_name' => $office->parent?->name ?? $office->name,
                'parent' => $office->parent ? [
                    'id' => $office->parent->id,
                    'name' => $office->parent->name
                ] : null
            ];
        });

    return ApiResponse::success($offices, 'Offices retrieved successfully');
}



    /**
     * Get leads by stage for Kanban board
     */
    public function getLeadsByStage(Stage $stage): JsonResponse
    {
        try {
            $user = auth()->user();
            
            $leadsQuery = $stage->leads()->with(['responsiblePerson:id,name,display_name,email', 'addedBy:id,name,display_name']);
            
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
            
            $leads = $leadsQuery
                ->orderBy('updated_at', 'desc')
                ->orderBy('id', 'desc')
                ->get();

            return ApiResponse::success(
                LeadResource::collection($leads),
                'Leads for stage retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve leads for stage: ' . $e->getMessage());
        }
    }
    
    public function getStageVisibilitySettings()
    {
        try {
            $settings = DB::table('stage_visibility')->get();
             $allStages = Stage::where('stage_type', 'lead')
            ->orderBy('order')
            ->get(['id', 'name', 'color', 'order','auto_revert','revert_after_hours','notify_before_minutes']);
    

            return ApiResponse::success([
                'settings' => $settings,
                'all_stages' => $allStages,
                'roles' => ['super_admin', 'admin', 'manager', 'team_lead', 'sales', 'marketing']
            ], 'Stage visibility settings retrieved');
            
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage());
        }
    }
    
  
    public function updateStageVisibility(Request $request)
    {
        try {
            $request->validate([
                'role_name' => 'required|string',
                'visible_stages' => 'required|array',
                'visible_stages.*' => 'exists:stages,id'
            ]);
            
            DB::table('stage_visibility')->updateOrInsert(
                ['role_name' => $request->role_name],
                [
                    'visible_stages' => json_encode($request->visible_stages),
                    'updated_at' => now()
                ]
            );
            
            return ApiResponse::success(null, 'Stage visibility updated successfully');
            
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage());
        }
    }

    /**
     * Filter by lead_source: single string, or array for multiple (OR / whereIn).
     */


    /**
     * Budget filter with range-overlap support for lead cards.
     */
    private function applyBudgetRangeFilter($query, Request $request): void
    {
        $min = $request->filled('budget_from') ? (float) $request->budget_from : null;
        $max = $request->filled('budget_to') ? (float) $request->budget_to : null;

        if ($min === null && $max === null) {
            return;
        }

        if ($min !== null && $max !== null && $min > $max) {
            [$min, $max] = [$max, $min];
        }

        if ($min !== null && $max !== null) {
            $query->where(function ($q) use ($min, $max) {
                $q->where(function ($w) use ($min, $max) {
                    $w->whereNotNull('budget_from')
                      ->whereNotNull('budget_to')
                      ->where('budget_from', '<=', $max)
                      ->where('budget_to', '>=', $min);
                })
                ->orWhereBetween('budget', [$min, $max])
                ->orWhere(function ($w) use ($min, $max) {
                    $w->whereNotNull('budget_from')
                      ->whereNull('budget_to')
                      ->whereBetween('budget_from', [$min, $max]);
                })
                ->orWhere(function ($w) use ($min, $max) {
                    $w->whereNotNull('budget_to')
                      ->whereNull('budget_from')
                      ->whereBetween('budget_to', [$min, $max]);
                });
            });

            return;
        }

        if ($min !== null) {
            $query->where(function ($q) use ($min) {
                $q->where('budget', '>=', $min)
                  ->orWhere('budget_from', '>=', $min)
                  ->orWhere('budget_to', '>=', $min);
            });

            return;
        }

        $query->where(function ($q) use ($max) {
            $q->where('budget', '<=', $max)
              ->orWhere('budget_from', '<=', $max)
              ->orWhere('budget_to', '<=', $max);
        });
    }
    private function applyLeadSourceFilter($query, Request $request): void
{
    if (! $request->filled('source') ) {
        return;
    }

    $websitePartials = ['website', 'Allproperties.ae', 'Oiaproperties.com'];
    $portalPartials  = ['portal', 'propertyfinder', 'bayut'];

    $expand = function ($value) use ($websitePartials, $portalPartials) {
        if ($value === 'website') return $websitePartials;
        if ($value === 'portal')  return $portalPartials;
        return [$value];
    };

    $src = $request->source;

    if (is_array($src)) {
        $src = array_values(array_filter($src, fn ($v) => $v !== null && $v !== ''));

        if (count($src) === 0) {
            return;
        }

        $expanded = [];
        foreach ($src as $v) {
            foreach ($expand($v) as $entry) {
                $expanded[] = $entry;
            }
        }

        $expanded = array_values(array_unique($expanded));

        $query->where(function ($q) use ($expanded) {
            $q->whereIn('lead_source', $expanded)
              ->orWhere(function ($q2) use ($expanded) {
                  foreach ($expanded as $term) {
                      $q2->orWhere('more_information', 'LIKE', "%{$term}%");
                  }
              });
        });

        return;
    }

    $expanded = $expand($src);

    $query->where(function ($q) use ($expanded) {
        $q->whereIn('lead_source', $expanded)
          ->orWhere(function ($q2) use ($expanded) {
              foreach ($expanded as $term) {
                  $q2->orWhere('more_information', 'LIKE', "%{$term}%");
              }
          });
    });
}

    /**
     * Preload local users referenced in Bitrix24 LAST_ACTIVITY_BY metadata (one query).
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Lead>  $leads
     * @return \Illuminate\Support\Collection<int, User>
     */
    /**
     * Batch-resolve the "Activity" person + time for Kanban cards from the local
     * database (lead_histories), so each card doesn't run its own history query.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Lead>  $leads
     * @return array<int, array{user: \App\Models\User|null, at: mixed}>
     */
    /**
     * Batch-resolve the "Activity" person for Kanban cards: the local users
     * provisioned from Bitrix24 (users.bitrix24_id), keyed by Bitrix24 user id.
     * Every referenced Bitrix24 id is present as a key (null when not yet
     * provisioned) so the resource never falls back to a per-card query.
     *
     * @param  iterable<int, \App\Models\Lead>  $leads
     * @return array<int, \App\Models\User|null>
     */
    private function kanbanActivityUsersForLeads($leads): array
    {
        $b24Ids = collect($leads)
            ->pluck('bitrix24_last_activity_by_id')
            ->filter()
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($b24Ids === []) {
            return [];
        }

        $map = array_fill_keys($b24Ids, null);

        $users = User::query()
            ->whereIn('bitrix24_id', $b24Ids)
            ->with([
                'parent:id,name,display_name,avatar',
                'roles:id,name',
                'employeeProfile.companyBranch:id,name',
                'employeeProfile.designation:id,name',
            ])
            ->get(['id', 'bitrix24_id', 'name', 'display_name', 'avatar', 'email', 'parent_id']);

        foreach ($users as $user) {
            $map[(int) $user->bitrix24_id] = $user;
        }

        return $map;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Lead>  $leads
     * @return array<string, int>
     */
    private function kanbanDuplicateCountsByWorkPhone($leads): array
    {
        $phones = $leads->pluck('work_phone')->filter()->unique()->values()->all();
        if ($phones === []) {
            return [];
        }

        $rows = Lead::query()
            ->select('work_phone', DB::raw('COUNT(*) as cnt'))
            ->whereIn('work_phone', $phones)
            ->whereNotNull('work_phone')
            ->groupBy('work_phone')
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row->work_phone] = max(0, (int) $row->cnt - 1);
        }

        return $counts;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Lead>  $leads
     * @return array<int, bool>
     */
    private function kanbanServiceDuplicateFlags($leads): array
    {
        $phones = $leads->pluck('work_phone')->filter()->unique()->values()->all();
        $emails = $leads->pluck('email')->filter()->unique()->values()->all();

        if ($phones === [] && $emails === []) {
            return [];
        }

        $blacklistQuery = Lead::query()
            ->where('status_lead', 'blacklist')
            ->where(function ($q) use ($phones, $emails) {
                if ($phones !== []) {
                    $q->whereIn('work_phone', $phones);
                }
                if ($emails !== []) {
                    $q->orWhereIn('email', $emails);
                }
            });

        $blacklistPhones = [];
        $blacklistEmails = [];
        foreach ($blacklistQuery->get(['id', 'work_phone', 'email']) as $row) {
            if ($row->work_phone) {
                $blacklistPhones[$row->work_phone] = true;
            }
            if ($row->email) {
                $blacklistEmails[$row->email] = true;
            }
        }

        $flags = [];
        foreach ($leads as $lead) {
            $flags[$lead->id] =
                ($lead->work_phone && isset($blacklistPhones[$lead->work_phone]))
                || ($lead->email && isset($blacklistEmails[$lead->email]));
        }

        return $flags;
    }
}