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
use App\Models\Integration;
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
use App\Models\LeadComment;
use Illuminate\Support\Str;
use Auth;
class LeadController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:leads-list', ['only' => ['index', 'show']]);
        $this->middleware('permission:leads-create', ['only' => ['store']]);
        $this->middleware('permission:leads-edit', ['only' => ['update', 'changeStage', 'assignResponsiblePerson', 'updateExtraClientRequirements']]);
        $this->middleware('permission:leads-delete', ['only' => ['destroy']]);
    }

    /**
     * Get all leads with auto-revert check and hierarchy support
             */
        public function index(Request $request): JsonResponse
        {
            try {
                // $this->checkAndRevertLeads();
        
                $user = auth()->user();
                $perPage = $request->get('per_page', 20);
        
                $leadsQuery = Lead::with([
                    'stage',
                    'addedBy',
                    'responsiblePerson',
                    'participants',
                    'observers.user',
                    'integration:id,project_id',
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
             $initialComment = $request->input('comment');
            unset($leadData['participants'], $leadData['observers'],$leadData['comment']);
            $leadData['added_by'] = $user->id;
            $leadData['last_stage_change_at'] = now();

            if ($request->has('budget_from') || $request->has('budget_to')) {
                $bt = $request->input('budget_to');
                $bf = $request->input('budget_from');
                $leadData['budget'] = ($bt !== null && $bt !== '') ? $bt : (($bf !== null && $bf !== '') ? $bf : null);
            }

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
           if ($request->input('lead_source') === 'referral') {
            $leadData['source_client_name'] = $request->input('source_client_name');
            $leadData['source_client_phone'] = $request->input('source_client_phone');
            $leadData['source_client_email'] = $request->input('source_client_email');
            $leadData['source_relation'] = $request->input('source_relation');
        }
            $lead = Lead::create($leadData);
       if (!empty($initialComment)) {
                $comment = LeadComment::create([
                    'lead_id' => $lead->id,
                    'user_id' => $user->id,
                    'comment' => $initialComment,
                ]);
                
                // تسجيل في الهيستوري
                LeadHistoryHelper::log(
                    $lead->id,
                    [
                        'action' => 'comment_added',
                        'comment_id' => $comment->id,
                        'comment' => Str::limit($comment->comment, 50)
                    ]
                );
            }
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
          $lead->lead_branch_source=$lead->responsiblePerson?->admin_parent?->name;
          $lead->save();
            LeadHistoryHelper::log(
                $lead->id,
                ['action' => 'created','name'=>$lead->lead_name,'lead_branch_source'=>$lead->responsiblePerson?->admin_parent?->name]
            );
        broadcast(new LeadUpdated($lead, 'created'));

            return ApiResponse::success(
                new LeadResource($lead->load([
                    'stage',
                    'addedBy',
                    'responsiblePerson',
                    'participants',
                    'observers.user',
                    'integration:id,project_id',
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
                    'observers.user',
                    'integration:id,project_id',
                ])),
                'Lead retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve lead: ' . $e->getMessage());
        }
    }

    /**
     * Resolve project_id for matching listings (integration project or lead.project_id).
     * Authorized like lead show — works when integration.owner !== auth (unlike GET /integrations/{id}).
     */
    public function leadIntegrationProject(Lead $lead): JsonResponse
    {
        try {
            $user = auth()->user();
            if (!$user->canViewLead($lead)) {
                return ApiResponse::error('You are not authorized to view this lead', 403);
            }

            $lead->loadMissing('integration:id,project_id');

            $projectId = $lead->integration?->project_id;
            if ($projectId === null && $lead->integration_id) {
                $projectId = Integration::query()->whereKey($lead->integration_id)->value('project_id');
            }
            if ($projectId === null) {
                $projectId = $lead->project_id;
            }

            return ApiResponse::success([
                'project_id' => $projectId !== null ? (int) $projectId : null,
                'integration_id' => $lead->integration_id,
            ], 'Project scope resolved');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to resolve project: ' . $e->getMessage(), 500);
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
                if (!auth()->user()->hasRole('admin') && !auth()->user()->hasRole('super_admin')) {
                        unset($leadData['email']);
                        unset($leadData['secondary_email']);
                        unset($leadData['work_phone']);
                        unset($leadData['work_phone_2']);
                    }
                
                $participants = $request->input('participants', []);
                $observers = $request->input('observers', []);
                
                unset($leadData['participants'], $leadData['observers']);

                $changes = [];
                
                if (!empty($leadData['responsible_person_id']) && $leadData['responsible_person_id'] != $lead->responsible_person_id) {
                    $oldPerson = User::find($lead->responsible_person_id);
                    $newPerson = User::find($leadData['responsible_person_id']);
                    
                    $changes = [
                        'old_person_id'=>$oldPerson?->id,
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

                if ($request->has('budget_from') || $request->has('budget_to')) {
                    $bt = $request->input('budget_to');
                    $bf = $request->input('budget_from');
                    $leadData['budget'] = ($bt !== null && $bt !== '') ? $bt : (($bf !== null && $bf !== '') ? $bf : null);
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
                $ignoreKeys = ['stage_id', 'last_stage_change_at', 'responsible_person_id', 'updated_at'];

                $fields = [];
                
                foreach ($new as $key => $value) {
                    if (in_array($key, $ignoreKeys)) continue; 
                
                    if (!array_key_exists($key, $old)) continue;
                
                    if ($old[$key] != $value) {
                        $fields[$key] = [
                            'old' => $old[$key],
                            'new' => $value
                        ];
                    }
                }
                if (isset($leadData['responsible_person_id']) && $leadData['responsible_person_id'] != $old['responsible_person_id']) {
                    $fields['responsible_person_id'] = [
                        'old' => $old['responsible_person_id'],
                        'new' => $leadData['responsible_person_id']
                    ];
                    
                    // يمكنك أيضاً إضافة أسماء المستخدمين للوضوح
                    $oldPerson = User::find($old['responsible_person_id']);
                    $newPerson = User::find($leadData['responsible_person_id']);
                    
                       $changes = [
                                    'old_person_id'=>$oldPerson?->id,
                            'old_person' => $oldPerson?->name,
                            'new_person' => $newPerson?->name
                        ];
                          LeadHistoryHelper::log(
                            $lead->id,
                            [
                                'action' => 'assigned',
                                                    'old_person_id'=>$oldPerson?->id,
                                'old_person' => $oldPerson?->name,
                                'new_person' => $newPerson?->name
                            ]
                        );
                }

                           
                LeadHistoryHelper::log(
                    $lead->id,
                    [
                        'action' => 'updated',
                        'fields' => $fields
                    ]
                );
                return ApiResponse::success(
                    new LeadResource($lead->load([
                        'responsiblePerson',
                        'participants',
                        'observers.user',
                        'integration:id,project_id',
                    ])),
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
            'last_stage_change_at' => now(),
            'revert'=>null,
        ]);
                $changes = [
                    'old_person_id'=>$oldPerson?->id,
            'old_person' => $oldPerson?->name,
            'new_person' => $responsiblePerson?->name
        ];
        broadcast(new LeadUpdated($lead, 'assigned', null, $changes));
        //  ==================================hiatory====================
        LeadHistoryHelper::log(
            $lead->id,
            [
                'action' => 'assigned',
                                    'old_person_id'=>$oldPerson?->id,
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
     * Persist additional client requirement blocks (separate from primary lead columns).
     */
    public function updateExtraClientRequirements(Request $request, Lead $lead): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user->canViewLead($lead)) {
                return ApiResponse::error('You are not authorized to update this lead', 403);
            }

            $request->validate([
                'extra_client_requirements' => 'nullable|array',
                'extra_client_requirements.*' => 'array',
            ]);

            $lead->update([
                'extra_client_requirements' => $request->input('extra_client_requirements'),
            ]);

            $fresh = $lead->fresh()->load([
                'responsiblePerson',
                'propertyType',
                'area',
                'integration:id,project_id',
            ]);

            broadcast(new LeadUpdated($fresh, 'updated'));

            LeadHistoryHelper::log($fresh->id, [
                'action' => 'updated',
                'fields' => ['extra_client_requirements' => ['saved' => true]],
            ]);

            return ApiResponse::success(
                new LeadResource($fresh),
                'Client requirements updated'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update client requirements: ' . $e->getMessage());
        }
    }

    /**
     * Get Available Responsible Persons for Assignment
     */
  public function getAvailableResponsiblePersons(): JsonResponse
{
    try {
        $user = auth()->user();
        
        // Handle multiple office IDs
        $officeIds = request()->office_ids;
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
                        $officeAndDescendants = array_merge(
                            $officeAndDescendants,
                            $selectedOffice->getAllSubordinatesIds()
                        );
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
        
        if (($user->hasRole('admin') || $user->hasRole('super_admin'))) {
            $responsiblePersons = User::role(['team_lead', 'sales', 'manager','admin'])
                ->whereNotNull('parent_id')
                ->when(!empty($officeAndDescendants), function($qq) use($officeAndDescendants){
                    // Filter by multiple office IDs
                    $qq->whereIn('id', $officeAndDescendants); 
                })
                ->get(['id', 'name', 'email', 'avatar','parent_id'])
                ->map(function($user) {
                    return [
                        'id'     => $user->id,
                        'name'   => $user->name,
                        'email'  => $user->email,
                        'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                        'role_name' => $user->roles()->first()->name,
                        'team_id'=>$user->parent?->id,
                        'parent_name' => $user->parent?->name,
                        'branch_id' => $user->office?->id,

                        'branch_name' => $user->office?->name
                    ];
                });
        } else {
            $subordinatesIds = $user->getAllSubordinatesIds();
            $responsiblePersons = User::role(['team_lead','sales'])
                ->whereIn('id', $subordinatesIds)
                ->when(!empty($officeAndDescendants), function($qq) use($officeAndDescendants){
                    // Filter by multiple office IDs
                    $qq->whereIn('id', $officeAndDescendants); 
                })
                ->get(['id', 'name', 'email','avatar','parent_id'])
                ->map(function($user) {
                    return [
                        'id'     => $user->id,
                        'name'   => $user->name,
                        'email'  => $user->email,
                        'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                        'team_id'=>$user->parent?->id,
                        'parent_name' => $user->parent?->name,
                        'branch_id' => $user->office?->id,

                        'branch_name' => $user->office?->name
                    ];
                });
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

        // Validation rules using existing columns
        $request->validate([
            'stage_id' => 'required|exists:stages,id',
            'reason' => 'nullable|string|max:255',
            
            // Stage 3: Follow Up
            'salutation' => 'nullable|string|max:50',
            
            // Stage 4: Qualified - استخدام status_lead
            'area_id' => 'nullable|exists:areas,id',
            'property_type_id' => 'nullable|exists:property_types,id',
            'budget' => 'nullable|numeric|min:0|max:999999999.99',
            'budget_from' => 'nullable|numeric|min:0|max:999999999.99',
            'budget_to' => 'nullable|numeric|min:0|max:999999999.99',
            'lead_source' => 'nullable|string|max:100',
            'purpose_buying' => 'nullable|string|max:100',
            'bedrooms' => 'nullable',
            'status_lead' => 'nullable|string|max:50', // استخدام العمود الموجود
            'property_status'=>'nullable',
            'lead_type'=>'nullable',
            'deal_name'=>'nullable',
            
            // Stage 5: Future - عمود جديد
            'available_date' => 'nullable|date',
            
            // Stage 7: Shared - عمود جديد
            'branch' => 'nullable|string|max:100|in:Abu Dhabi,Dubai,Sharjah',
            
            // Stage 8: Lost - استخدام why_lost_lead
            'why_lost_lead' => 'nullable|string|max:255|in:lost_by_other_company,lost_by_our_company',
            
            // Stage 9: Lead Pool - استخدام status_lead
            'status_lead_pool' => 'nullable|string|max:100|in:no_answer,canceled,wrong_person,contacted',
            'interaction_result' => 'nullable|string|max:20|in:answered,no_answer',
            
            // Stage 10: Unqualified - استخدام status_lead
            'unqualified_status' => 'nullable',
            
            'responsible_person_id' => 'nullable|exists:users,id',
        ]);

        $oldStage = $lead->stage;
        $newStage = Stage::find($request->stage_id);
        
        $old = $lead->getAttributes();

        // Check revert condition for stage 3
        if ($newStage->order == 3 && $lead->stage->order == 2) {
            if ($lead->shouldRevertToStageOne()) {
                $lead->revertToStageOne();
                
                return ApiResponse::error(
                    'Cannot move to stage 3. Lead has been reverted to stage 1 due to inactivity.',
                    422
                );
            }
        }
        
        $changes = [];
        $fields = [];
        
        // التحقق من تغيير المسؤول
        if (!empty($request->responsible_person_id) && $request->responsible_person_id != $lead->responsible_person_id) {
            $oldPerson = User::find($lead->responsible_person_id);
            $newPerson = User::find($request->responsible_person_id);
            
            $changes = [
                'old_person_id' => $oldPerson?->id,
                'old_person' => $oldPerson?->name,
                'new_person' => $newPerson?->name,
                'action' => 'assigned'
            ];
        }

        if($newStage->order == 2 && !is_null($lead->revert)){
            $lead->update([
                'revert' => null,
            ]); 
            
            $responsiblePerson = User::find($lead->responsible_person_id);
            $revertChanges = [
                'old_person_id' => $responsiblePerson?->id,
                'old_person' => $responsiblePerson?->name,
                'new_person' => $responsiblePerson?->name,
                'action' => 'revert'
            ];
            broadcast(new LeadUpdated($lead, 'assigned', null, $revertChanges));
            
            LeadHistoryHelper::log(
                $lead->id,
                [
                    'action' => 'revert',
                    'old_person_id' => $responsiblePerson?->id,
                    'old_person' => $responsiblePerson?->name,
                    'new_person' => $responsiblePerson?->name
                ]
            );
        }
        
        // تجهيز بيانات التحديث
        $updateData = [
            'stage_id' => $request->stage_id,
            'last_stage_change_at' => now(),
        ];

        // إضافة جميع الحقول حسب المرحلة
        $additionalFields = [
            // الحقول الموجودة
            'area_id', 'property_type_id', 'budget', 'budget_from', 'budget_to', 'lead_source', 'deal_name',
            'purpose_buying', 'bedrooms', 'responsible_person_id','property_status','lead_type',
            'salutation',
            'status_lead',      // يستخدم للمراحل 4, 9, 10
           'status_lead_pool',     // ✅ أضيفي ده
            'unqualified_status',
            'interaction_result',
            'why_lost_lead',    // يستخدم للمرحلة 8
            'available_date',   // للمرحلة 5
            'branch'            // للمرحلة 7
        ];
        
        foreach ($additionalFields as $field) {
                if ($request->has($field) && $request->$field !== null) {

                    $targetStageOrder = $newStage->order;

                    if ($field === 'status_lead' && $targetStageOrder == 4) {
                        $updateData['status_lead'] = $request->status_lead;

                    } elseif ($field === 'status_lead_pool' && $targetStageOrder == 9) {
                        $updateData['status_lead'] = $request->status_lead_pool;

                    } elseif ($field === 'unqualified_status' && $targetStageOrder == 10) {
                        $updateData['status_lead'] = $request->unqualified_status;

                    } elseif ($field === 'why_lost_lead') {
                        $updateData['why_lost_lead'] = $request->why_lost_lead;

                    } else {
                        $updateData[$field] = $request->$field;
                    }
                }
            }

        if ($request->has('budget_from') || $request->has('budget_to')) {
            $bt = $request->has('budget_to') ? $request->input('budget_to') : $lead->budget_to;
            $bf = $request->has('budget_from') ? $request->input('budget_from') : $lead->budget_from;
            $updateData['budget_from'] = $bf;
            $updateData['budget_to'] = $bt;
            $updateData['budget'] = ($bt !== null && $bt !== '') ? $bt : (($bf !== null && $bf !== '') ? $bf : null);
        }
        if ($newStage->order == 4) {
            if ($lead->interaction_result === 'no_answer' || !$lead->interaction_result) {
                $updateData['interaction_result'] = 'answered';
            }
        }
        // dd($updateData);
        // تحديث الـ Lead
        $lead->update($updateData);

        // إضافة التعليق إذا وجد
        if($request->reason && $newStage->order != 3) {
            LeadComment::create([
                'lead_id' => $lead->id,
                'comment' => $request->reason,
                'user_id' => auth()->user()->id
            ]);
        }

        // =================== تسجيل الهيستوري ===================
        $new = $lead->getAttributes();
        $ignoreKeys = [
            'stage_id', 'last_stage_change_at', 'responsible_person_id', 
            'updated_at', 'created_at', 'revert', 'score', 'priority', 
            'intent', 'next_action', 'last_scored_at', 'score_breakdown',
            'converted_to_deal_id', 'converted_at'
        ];

        // تجميع الحقول المتغيرة
        foreach ($new as $key => $value) {
            if (in_array($key, $ignoreKeys)) continue; 
        
            if (!array_key_exists($key, $old)) continue;
        
            if ($old[$key] != $value) {
                $fields[$key] = [
                    'old' => $old[$key],
                    'new' => $value
                ];
            }
        }

        // تسجيل تغيير المسؤول
        if (isset($updateData['responsible_person_id']) && $updateData['responsible_person_id'] != $old['responsible_person_id']) {
            $fields['responsible_person_id'] = [
                'old' => $old['responsible_person_id'],
                'new' => $updateData['responsible_person_id']
            ];
            
            $oldPerson = User::find($old['responsible_person_id']);
            $newPerson = User::find($updateData['responsible_person_id']);
            
            LeadHistoryHelper::log(
                $lead->id,
                [
                    'action' => 'assigned',
                    'old_person_id' => $oldPerson?->id,
                    'old_person' => $oldPerson?->name,
                    'new_person' => $newPerson?->name
                ]
            );
        }

        // تسجيل تغيير المرحلة
        LeadHistoryHelper::log(
            $lead->id,
            [
                'action' => 'stage_changed',
                'old_stage' => $oldStage->name,
                'new_stage' => $newStage->name,
                'old_stage_id' => $oldStage->id,
                'new_stage_id' => $newStage->id
            ]
        );

        // تسجيل تغييرات الحقول الأخرى
        if (!empty($fields)) {
            LeadHistoryHelper::log(
                $lead->id,
                [
                    'action' => 'updated',
                    'fields' => $fields
                ]
            );
            broadcast(new LeadUpdated($lead, 'updated'));
        }

        // =================== Broadcast ===================
        $broadcastChanges = [
            'old_stage' => $oldStage->name,
            'new_stage' => $newStage->name,
            'old_stage_id' => $oldStage->id,
            'new_stage_id' => $newStage->id
        ];

        if (!empty($fields)) {
            $broadcastChanges['updated_fields'] = $fields;
        }

        if (!empty($changes) && isset($changes['action']) && $changes['action'] === 'assigned') {
            broadcast(new LeadUpdated($lead, 'assigned', null, array_merge($changes, $broadcastChanges)));
        } else {
            broadcast(new LeadUpdated($lead, 'stage_changed', null, $broadcastChanges));
        }
        
        return ApiResponse::success(
            new LeadResource($lead->load([
                'stage',
                'responsiblePerson',
                'participants',
                'observers.user',
                'integration:id,project_id',
            ])),
            'Lead stage and data updated successfully'
        );

    } catch (\Illuminate\Validation\ValidationException $e) {
        return ApiResponse::error($e->validator->errors()->first(), 422);
    } catch (\Exception $e) {
        \Log::error('Error in changeStage: ' . $e->getMessage());
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
        $leadData['responsible_person_id'] = (int)$leadData['responsible_person_id'] 
            ?? config('leads.default_manager');

        // default stage
        $firstStage = Stage::where('stage_type','lead')->orderBy('order','asc')->first();
        $leadData['stage_id'] = $leadData['stage_id'] 
            ?? ($firstStage ? $firstStage->id : null);

        $lead = Lead::create($leadData);

       
 LeadHistoryHelper::log(
                $lead->id,
                ['action' => 'created','name'=>$lead->lead_name,'lead_branch_source'=>$lead->responsiblePerson?->admin_parent?->name]
            );
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
    $query = LeadHistory::where('lead_id', $leadId)->whereNull('deal_id')
        ->with('user:id,name,avatar');
    
    // Search functionality - search in multiple fields
    if ($request->filled('search')) {
        $searchTerm = $request->search;
        $query->where(function($q) use ($searchTerm) {
            // Search in action field inside changes JSON
            $q->where('changes->action', 'LIKE', "%{$searchTerm}%")
              // Search in user name
              ->orWhereHas('user', function($userQuery) use ($searchTerm) {
                  $userQuery->where('name', 'LIKE', "%{$searchTerm}%");
              })
              // Search in old_stage
              ->orWhereRaw("JSON_EXTRACT(changes, '$.old_stage') LIKE ?", ["%{$searchTerm}%"])
              // Search in new_stage
              ->orWhereRaw("JSON_EXTRACT(changes, '$.new_stage') LIKE ?", ["%{$searchTerm}%"])
              // Search in old_person
              ->orWhereRaw("JSON_EXTRACT(changes, '$.old_person') LIKE ?", ["%{$searchTerm}%"])
              // Search in new_person
              ->orWhereRaw("JSON_EXTRACT(changes, '$.new_person') LIKE ?", ["%{$searchTerm}%"]);
        });
    }

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

    // Get per_page from request or default to 10
    $perPage = $request->input('per_page', 10);
    
    $histories = $query->latest()->paginate($perPage);

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
    
      public function getMyClients(Request $request)
    {
        try {
            $user = Auth::user();
            
            // جلب جميع الـ Leads التي أضافها المستخدم أو المسؤول عنها
            $clients = Lead::where('added_by', $user->id)
                ->orWhere('responsible_person_id', $user->id)
                ->select(
                    'id',
                    'lead_name as name',
                    'first_name',
                    'last_name',
                    'email',
                    'work_phone as phone',
                    'work_phone_2',
                    'secondary_email',
                    'salutation',
                    'area_id',
                    'property_type_id',
                    'lead_source',
                    'stage_id',
                    'created_at'
                )
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($lead) {
                    // تنسيق الاسم الكامل
                    if (!$lead->name && $lead->first_name) {
                        $lead->name = trim($lead->first_name . ' ' . ($lead->last_name ?? ''));
                    }
                    if (!$lead->name && $lead->lead_name) {
                        $lead->name = $lead->lead_name;
                    }
                    return $lead;
                });
            
            return ApiResponse::success($clients, 'Clients retrieved successfully');
            
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve clients: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * جلب قائمة العملاء مع إمكانية البحث والتصفية
     */
    public function getClientsList(Request $request)
    {
        try {
            $user = Auth::user();
            
            $query = Lead::where(function ($q) use ($user) {
                $q->where('added_by', $user->id)
                  ->orWhere('responsible_person_id', $user->id);
            });
            
            // بحث باسم العميل
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('lead_name', 'like', "%{$search}%")
                      ->orWhere('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('work_phone', 'like', "%{$search}%");
                });
            }
            
            // تصفية حسب المرحلة
            if ($request->has('stage_id') && $request->stage_id) {
                $query->where('stage_id', $request->stage_id);
            }
            
            $clients = $query->select(
                'id',
                'lead_name as name',
                'first_name',
                'last_name',
                'email',
                'work_phone as phone',
                'work_phone_2',
                'secondary_email',
                'salutation',
                'area_id',
                'property_type_id',
                'lead_source',
                'stage_id',
                'created_at'
            )
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));
            
            // تنسيق الاسم لكل عميل
            $clients->getCollection()->transform(function ($lead) {
                if (!$lead->name && $lead->first_name) {
                    $lead->name = trim($lead->first_name . ' ' . ($lead->last_name ?? ''));
                }
                if (!$lead->name && $lead->lead_name) {
                    $lead->name = $lead->lead_name;
                }
                return $lead;
            });
            
            return ApiResponse::success($clients, 'Clients retrieved successfully');
            
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve clients: ' . $e->getMessage(), 500);
        }
    }
}