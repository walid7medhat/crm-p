<?php

namespace App\Http\Controllers\Api;

use App\Events\LeadUpdated;
use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Http\Requests\Lead\LeadRequest;
use App\Http\Requests\Lead\LeadIntegrationRequest;
use App\Http\Requests\Lead\AssignResponsiblePersonRequest;
use App\Http\Resources\Lead\LeadResource;
use App\Http\Resources\Lead\LeadCollection;
use App\Models\Lead;
use App\Models\LeadParticipant;
use App\Models\LeadObserver;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Http\Resources\Lead\DuplicateLeadResource;
use App\Helpers\LeadHistoryHelper;
use App\Http\Resources\Lead\LeadHistoryResource;
use App\Models\LeadHistory;

use Illuminate\Support\Str;
class LeadController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:leads-list', ['only' => ['index', 'show']]);
        $this->middleware('permission:leads-create', ['only' => ['store']]);
        $this->middleware('permission:leads-edit', ['only' => ['update', 'changeStage', 'assignResponsiblePerson']]);
        $this->middleware('permission:leads-delete', ['only' => ['destroy']]);
    }

    /**
     * Get all leads with auto-revert check and hierarchy support
             */
        public function index(Request $request): JsonResponse
        {
            try {
                $this->checkAndRevertLeads();
        
                $user = auth()->user();
                $perPage = $request->get('per_page', 20);
        
                $leadsQuery = Lead::with([
                    'stage', 
                    'addedBy', 
                    'responsiblePerson', 
                    'participants',
                    'observers.user'
                ]);
                if ($request->filled('responsible_person_id')) {
                    $leadsQuery->where('responsible_person_id', $request->responsible_person_id);
                }
                
                if ($request->filled('stage_id')) {
                    $leadsQuery->where('stage_id', $request->stage_id);
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
                    $leadsQuery->whereDate('created_at', '=', $request->created_to);
                }
                
                if ($request->filled('search')) {
                    $search = $request->search;
                    $leadsQuery->where(function($q) use ($search) {
                        $q->where('lead_name', 'like', "%{$search}%")
                          ->orWhere('lead_number', 'like', "%{$search}%");
                    });
                }
                
                // =================   source =================
                if ($request->filled('source')) {
                    $leadsQuery->where('lead_source', $request->source);
                }
                
                if ($request->filled('source_information')) {
                    $leadsQuery->where('source_information', 'like', "%{$request->source_information}%");
                }
                  if ($request->filled('changed_by') ) {
                      $leadsQuery->whereHas('histories',function($query) use($request){
                                $query->where('changes->action', 'stage_changed')->where('user_id',$request->changed_by);
                      });
                  
                    }
        
                if ($user->hasRole('super_admin') ) {
                    $leads = $leadsQuery->latest()->get();
                } elseif ($user->hasAnyRole(['manager', 'team_lead','admin'])) {
                    $subordinatesIds = $user->getAllSubordinatesIds();
                    $leads = $leadsQuery->where(function($query) use ($subordinatesIds, $user) {
                                $query->whereIn('responsible_person_id', array_merge($subordinatesIds, [$user->id]))
                                      ->orWhereIn('added_by', $subordinatesIds);
                            })->latest()->get();
                } else {
                    $leads = $leadsQuery->where(function($query) use ($user) {
                                $query->where('responsible_person_id', $user->id)
                                      ->orWhere('added_by', $user->id);
                            })->latest()->get();
                }
        
                $stagesWithLeads = $leads->groupBy('stage_id')->map(function($leadsGroup, $stageId) {
                    $stage = $leadsGroup->first()->stage; 
                    return [
                        'stage_name' => $stage?->name ?? 'No Stage',
                        'stage_id' => $stage?->id,
                        'leads' => LeadResource::collection($leadsGroup),
                    ];
                })->values(); 
                return ApiResponse::success(
                    $stagesWithLeads,
                    'Leads grouped by stage retrieved successfully'
                );
        
            } catch (\Exception $e) {
                return ApiResponse::error('Failed to retrieve leads: ' . $e->getMessage());
            }
        }


    /**
     * Create a new lead - 
     */
    public function store(LeadRequest $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $leadData = $request->validated();
            
            $participants = $request->input('participants', []);
            $observers = $request->input('observers', []);
            
            unset($leadData['participants'], $leadData['observers']);
            $leadData['added_by'] = $user->id;
            $leadData['last_stage_change_at'] = now();

            // if (!empty($leadData['responsible_person_id']) && $user->hasRole(['super_admin','admin', 'manager', 'team_lead'])) {
            //     $leadData['responsible_person_id'] = $user->id;
            // } else {
            //     if (empty($leadData['responsible_person_id'])) {
            //         return ApiResponse::error('Responsible person is required', 422);
            //     }
                
            //     $responsiblePerson = User::find($leadData['responsible_person_id']);
            //     if (!$responsiblePerson->hasRole(['admin', 'manager', 'team_lead'])) {
            //         return ApiResponse::error('Responsible person must be an admin, manager, or team lead', 422);
            //     }
            // }
          if(!$request->stage_id){
              $leadData['stage_id']=Stage::orderBy('order','asc')->first()->id;
          }
            $lead = Lead::create($leadData);

            if (!empty($participants)) {
                foreach ($participants as $participantData) {
                    $participantData['added_by'] = $user->id;
                    $lead->participants()->create($participantData);
                }
            }

            if (!empty($observers)) {
                foreach ($observers as $observerId) {
                    $lead->observers()->create([
                        'user_id' => $observerId,
                        'added_by' => $user->id
                    ]);
                }
            }
            LeadHistoryHelper::log(
                $lead->id,
                ['action' => 'created']
            );
        broadcast(new LeadUpdated($lead, 'created'));

            return ApiResponse::success(
                new LeadResource($lead->load([
                    'stage', 
                    'addedBy', 
                    'responsiblePerson',
                    'participants',
                    'observers.user'
                ])),
                'Lead created successfully',
                201
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create lead: ' . $e->getMessage());
        }
    }

    /**
     * Get a single lead with hierarchy check
     */
    public function show(Lead $lead): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user->canViewLead($lead)) {
                return ApiResponse::error('You are not authorized to view this lead', 403);
            }

            if ($lead->shouldRevertToStageOne()) {
                $lead->revertToStageOne();
                $lead->refresh();
            }
            // LeadHistoryHelper::log(
            //     $lead->id,
            //     ['action' => 'view']
            // );

            return ApiResponse::success(
                new LeadResource($lead->load([
                    'stage', 
                    'addedBy', 
                    'responsiblePerson', 
                    'participants',
                    'observers.user'
                ])),
                'Lead retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve lead: ' . $e->getMessage());
        }
    }

    /**
     * Update a lead
     */
   public function update(LeadRequest $request, Lead $lead): JsonResponse
        {
            try {
                $user = auth()->user();
                
                if (!$user->canViewLead($lead)) {
                    return ApiResponse::error('You are not authorized to update this lead', 403);
                }

                $leadData = $request->validated();
                
                $participants = $request->input('participants', []);
                $observers = $request->input('observers', []);
                
                unset($leadData['participants'], $leadData['observers']);

                $changes = [];
                
                if (!empty($leadData['responsible_person_id']) && $leadData['responsible_person_id'] !== $lead->responsible_person_id) {
                    $oldPerson = User::find($lead->responsible_person_id);
                    $newPerson = User::find($leadData['responsible_person_id']);
                    
                    $changes = [
                        'old_person' => $oldPerson?->name,
                        'new_person' => $newPerson?->name,
                        'action' => 'assigned'
                    ];
                }

                if (!empty($leadData['responsible_person_id']) && $leadData['responsible_person_id'] !== $lead->responsible_person_id) {
                    $newResponsiblePerson = User::find($leadData['responsible_person_id']);
                    
                    if (!$user->hasRole(['super_admin','admin', 'manager', 'team_lead'])) {
                        return ApiResponse::error('You are not authorized to change responsible person', 403);
                    }
                }

                if ($request->has('stage_id') && $lead->stage_id != $request->stage_id) {
                    $leadData['last_stage_change_at'] = now();
                }
                $old = $lead->getAttributes();

                $lead->update($leadData);

                if ($request->has('participants')) {
                    $lead->participants()->delete();
                    foreach ($request->participants as $participantData) {
                        $participantData['added_by'] = auth()->id();
                        $lead->participants()->create($participantData);
                    }
                }

                if ($request->has('observers')) {
                    $lead->observers()->delete();
                    foreach ($request->observers as $observerId) {
                        $lead->observers()->create([
                            'user_id' => $observerId,
                            'added_by' => auth()->id()
                        ]);
                    }
                }

                if (!empty($changes) && isset($changes['action']) && $changes['action'] === 'assigned') {
                    broadcast(new LeadUpdated($lead, 'assigned', null, $changes));
                } else {
                    broadcast(new LeadUpdated($lead, 'updated'));
                }
                // ===================history==============
                $new = $lead->getAttributes();
                
                $fields = [];
                
                foreach ($new as $key => $value) {
                    if (!array_key_exists($key, $old)) continue;
                
                    if ($old[$key] != $value) {
                        $fields[$key] = [
                            'old' => $old[$key],
                            'new' => $value
                        ];
                    }
                }
                
                LeadHistoryHelper::log(
                    $lead->id,
                    [
                        'action' => 'updated',
                        'fields' => $fields
                    ]
                );
                return ApiResponse::success(
                    new LeadResource($lead->load(['responsiblePerson', 'participants', 'observers.user'])),
                    'Lead updated successfully'
                );
            } catch (\Exception $e) {
                return ApiResponse::error('Failed to update lead: ' . $e->getMessage());
            }
        }

    /**
     * Assign Responsible Person to Lead
     */
   public function assignResponsiblePerson(AssignResponsiblePersonRequest $request, Lead $lead): JsonResponse
{
    try {
        $user = auth()->user();

        if (!$user->hasRole(['super_admin','admin', 'manager', 'team_lead'])) {
            return ApiResponse::error('You are not authorized to assign responsible person', 403);
        }

        $responsiblePerson = User::find($request->responsible_person_id);

        // if (!$responsiblePerson->hasRole(['admin', 'manager', 'team_lead'])) {
        //     return ApiResponse::error('The assigned user must be an admin, manager, or team lead', 422);
        // }

        if (!($user->hasRole('admin') || $user->hasRole('super_admin'))) {
            $subordinatesIds = $user->getAllSubordinatesIds();
            if (!in_array($request->responsible_person_id, $subordinatesIds)) {
                return ApiResponse::error('You can only assign responsible person from your team', 403);
            }
        }
        $oldPerson = User::find($lead->responsible_person_id);

        $lead->update([
            'responsible_person_id' => $request->responsible_person_id,
            'last_stage_change_at' => now()
        ]);
                $changes = [
            'old_person' => $oldPerson?->name,
            'new_person' => $responsiblePerson?->name
        ];
        broadcast(new LeadUpdated($lead, 'assigned', null, $changes));
        //  ==================================hiatory====================
        LeadHistoryHelper::log(
            $lead->id,
            [
                'action' => 'assigned',
                'old_person' => $oldPerson?->name,
                'new_person' => $responsiblePerson?->name
            ]
        );


        return ApiResponse::success(
            new LeadResource($lead->load(['responsiblePerson', 'stage'])),
            'Responsible person assigned successfully'
        );
    } catch (\Exception $e) {
        return ApiResponse::error('Failed to assign responsible person: ' . $e->getMessage());
    }
}

    /**
     * Get Available Responsible Persons for Assignment
     */
    public function getAvailableResponsiblePersons(): JsonResponse
    {
        try {
            $user = auth()->user();

            if (($user->hasRole('admin') || $user->hasRole('super_admin'))) {
               $responsiblePersons = User::role(['team_lead', 'sales', 'manager'])
                ->get(['id', 'name', 'email', 'avatar'])
                ->map(function($user) {
                    return [
                        'id'     => $user->id,
                        'name'   => $user->name,
                        'email'  => $user->email,
                        'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                    ];
                });

            } 
            elseif ($user->hasRole(['manager', 'team_lead'])) {
                $subordinatesIds = $user->getAllSubordinatesIds();
                $responsiblePersons = User::role(['team_lead','sales'])
                    ->whereIn('id', $subordinatesIds)
                    ->get(['id', 'name', 'email','avatar'])
                     ->map(function($user) {
                    return [
                                'id'     => $user->id,
                                'name'   => $user->name,
                                'email'  => $user->email,
                                'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                            ];
                        });
            }
            else {
                return ApiResponse::error('You are not authorized to view responsible persons list', 403);
            }

            return ApiResponse::success(
                $responsiblePersons,
                'Available responsible persons retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve available responsible persons: ' . $e->getMessage());
        }
    }

    /**
     * Delete a lead
     */
    public function destroy(Lead $lead): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!($user->hasRole('admin') || $user->hasRole('super_admin')) && $lead->added_by !== $user->id) {
                return ApiResponse::error('You are not authorized to delete this lead', 403);
            }
        broadcast(new LeadUpdated($lead, 'deleted'));

            $lead->delete();

            return ApiResponse::success(null, 'Lead deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete lead: ' . $e->getMessage());
        }
    }

    /**
     * Change lead stage
     */
    public function changeStage(Request $request, Lead $lead): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user->canViewLead($lead)) {
                return ApiResponse::error('You are not authorized to change stage of this lead', 403);
            }

            $request->validate([
                'stage_id' => 'required|exists:stages,id'
            ]);
        $oldStage = $lead->stage;

            $newStage = Stage::find($request->stage_id);

            if ($newStage->order == 3 && $lead->stage->order == 2) {
                if ($lead->shouldRevertToStageOne()) {
                    $lead->revertToStageOne();
                    
                    return ApiResponse::error(
                        'Cannot move to stage 3. Lead has been reverted to stage 1 due to inactivity.',
                        422
                    );
                }
            }
           
            $lead->update([
                'stage_id' => $request->stage_id,
                'last_stage_change_at' => now()
            ]);
         $changes = [
            'old_stage' => $oldStage->name,
            'new_stage' => $newStage->name
        ];
        broadcast(new LeadUpdated($lead, 'stage_changed', null, $changes));
        // =================history=================
        LeadHistoryHelper::log(
            $lead->id,
            [
                'action' => 'stage_changed',
                'old_stage' => $oldStage->name,
                'new_stage' => $newStage->name
            ]
        );

            return ApiResponse::success(
                new LeadResource($lead->load('stage')),
                'Lead stage updated successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to change lead stage: ' . $e->getMessage());
        }
    }

    /**
     * Check and revert leads that need to be reverted
     */
    private function checkAndRevertLeads(): void
    {
        $leadsToRevert = Lead::needsRevert()->get();

        foreach ($leadsToRevert as $lead) {
            $lead->revertToStageOne();
        }
    }

    /**
     * Manual revert check endpoint
     */
    public function checkRevert(): JsonResponse
    {
        try {
            $revertedCount = 0;
            $leadsToRevert = Lead::needsRevert()->get();

            foreach ($leadsToRevert as $lead) {
                $lead->revertToStageOne();
                $revertedCount++;
            }

            return ApiResponse::success(
                ['reverted_leads_count' => $revertedCount],
                "Successfully reverted {$revertedCount} leads to stage 1"
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to check and revert leads: ' . $e->getMessage());
        }
    }
    public function storeIntegration(LeadIntegrationRequest $request): JsonResponse
{

    try {
        $leadData = $request->validated();



        $leadData['last_stage_change_at'] = now();
        $leadData['lead_number'] = null;

        // responsible person
        $leadData['responsible_person_id'] = $leadData['responsible_person_id'] 
            ?? config('leads.default_manager');

        // default stage
        $firstStage = Stage::orderBy('order','asc')->first();
        $leadData['stage_id'] = $leadData['stage_id'] 
            ?? ($firstStage ? $firstStage->id : null);

        $lead = Lead::create($leadData);

       

        broadcast(new LeadUpdated($lead, 'created'));

        return  ApiResponse::success('success' );

    } catch (\Throwable $e) {

    return response()->json([
        'success' => false,
        'error' => [
            'message' => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
            'code'    => $e->getCode(),
            'trace'   => collect($e->getTrace())->take(10), 
        ]
    ], 500);
}}
    public function getDuplicate($lead_id): JsonResponse
        {
            try {
              $lead=Lead::find($lead_id);
              $leads=DuplicateLeadResource::collection($lead->duplicate_leads);
                return ApiResponse::success(
                    $leads,
                    'Duplicated Leads retrieved successfully'
                );
        
            } catch (\Exception $e) {
                return ApiResponse::error('Failed to retrieve leads: ' . $e->getMessage());
            }
        }
        // ======================history =======================
        public function history(Request $request, $leadId)
        {
            $query = LeadHistory::where('lead_id', $leadId)
                ->with('user:id,name');
        
            if ($request->filled('action')) {
                $query->where('changes->action', $request->action);
            }
        
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }
        
            if ($request->filled('from_date')) {
                $query->whereDate('created_at', '>=', $request->from_date);
            }
        
            if ($request->filled('to_date')) {
                $query->whereDate('created_at', '<=', $request->to_date);
            }
        
            $histories = $query->latest()->paginate(10);
        
            $data= LeadHistoryResource::collection($histories);
             return ApiResponse::success([
                'items' => LeadHistoryResource::collection($histories),
                'pagination' => [
                    'current_page' => $histories->currentPage(),
                    'last_page'    => $histories->lastPage(),
                    'per_page'     => $histories->perPage(),
                    'total'        => $histories->total(),
                    'next_page'    => $histories->nextPageUrl(),
                    'prev_page'    => $histories->previousPageUrl(),
                ]
            ], 'Lead History retrieved successfully');
        
        }
          public function view_history($id): JsonResponse
    {
        try {
            $lead=Lead::find($id);
            LeadHistoryHelper::log(
                $lead->id,
                ['action' => 'view']
            );

            return ApiResponse::success(
                new LeadResource($lead),
                'Lead history saved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve lead: ' . $e->getMessage());
        }
    }
}