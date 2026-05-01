<?php

namespace App\Http\Resources\Lead;

use App\Models\Integration;
use App\Models\Lead;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    public function toArray($request): array
    {
         $rawMetaData = is_string($this->raw_meta_data) 
            ? json_decode($this->raw_meta_data, true) 
            : $this->raw_meta_data;
$facebookFields = [];

if (!empty($rawMetaData['field_data']) && is_array($rawMetaData['field_data'])) {
    foreach ($rawMetaData['field_data'] as $field) {
        if (isset($field['name']) && isset($field['values'][0])) {
            $facebookFields[$field['name']] = $field['values'][0];
        }
    }
}
        $assignmentHistory = $this->histories()
            ->where('changes->action', 'assigned')
            ->orderBy('created_at', 'desc') 
            ->first();
        
        if ($assignmentHistory && $assignmentHistory->user) {
            $assignedBy = $assignmentHistory->user;
        } else {
            $assignedBy = $this->addedBy;
        }
        return [
            'id' => $this->id,
            'added_by' => $this->added_by,
            
            // Basic Information
            'lead_name' => $this->lead_name,
            'deal_name' => $this->deal_name,
            'lead_number' => $this->lead_number,
            'stage_id' => $this->stage_id,
            
            // Contact Information
            'salutation' => $this->salutation,
            'first_name' => $this->first_name,
            'second_name' => $this->second_name,
            'last_name' => $this->last_name,
            'date_of_birth' => $this->date_of_birth,
            
            // Contact Details
            'whatsapp_number' => $this->whatsapp_number,
            'work_phone' => $this->work_phone,
            'work_phone_2' => $this->work_phone_2,
            'email' => $this->email,
            'secondary_email' => $this->secondary_email,
            'website' => $this->website,
            'messenger' => $this->messenger,
            'facebook' => $this->facebook,
            
             'source_client_name' => $this->source_client_name,
            'source_client_phone' => $this->source_client_phone,
            'source_client_email' => $this->source_client_email,
            'source_relation' => $this->source_relation,
            // Company Information
            'company_name' => $this->company_name,
            'position' => $this->position,
            
            // Property Information
            'interested_in' => $this->interested_in,
            'bedrooms' => $this->bedrooms===0 || $this->bedrooms==='0'?'studio':$this->bedrooms,
            'purpose_buying' => $this->purpose_buying,
            'extra_client_requirements' => $this->extra_client_requirements ?? [],
            'nationality' => $this->nationality,
            'citizenship_program' => $this->citizenship_program,
            
            // Lead Source
            'lead_source' => $this->lead_source,
            'integration_id' => $this->integration_id,
            /** Listing scope: project linked on the lead (e.g. Meta / integration flow). */
            'project_id' => $this->project_id,
            /** Project for matching listings: integration project, then DB lookup, then lead.project_id. */
            'integration_project_id' => $this->resolveIntegrationProjectId(),
            'source_information' => $this->source_information,
                'office_branch' =>
            $this->responsiblePerson?->admin_parent?->name,
             'lead_branch_source' => $this->lead_branch_source ,
            'ad_id' => $this->ad_id,
            'available_to_everyone' => $this->available_to_everyone,
            
            // Status
            'status_lead' => $this->status_lead,
            'interaction_result' => $this->interaction_result,
            'available_date'=>$this->available_date,
            'branch'=>$this->branch,
            'status_unit' => $this->status_unit,
            'status_project' => $this->status_project,
            'lists' => $this->lists,
            'unqualified_reason' => $this->unqualified_reason,
            'why_lost_lead' => $this->why_lost_lead,
            
            // Additional
            'address' => $this->address,
            'comment' => $this->comment,
            'additional_services' => $this->additional_services,
            
            'responsible_person_id' => $this->responsible_person_id,
            'last_stage_change_at' => $this->last_stage_change_at,
            
            // Relationships
            'stage' => new \App\Http\Resources\Stage\MainStageResource($this->whenLoaded('stage')),
            'added_by_user' => new \App\Http\Resources\User\UserResource($this->whenLoaded('addedBy')),
            'responsible_person' => new \App\Http\Resources\User\UserResource($this->responsiblePerson),
            'participants' => LeadParticipantResource::collection($this->whenLoaded('participants')),
            'observers' => LeadObserverResource::collection($this->whenLoaded('observers')),
            
            'budget' =>  (int) $this->budget,
            'budget_from' => (int)$this->budget_from,
            'budget_to' => (int) $this->budget_to,
            'property_status' => $this->property_status,
            'lead_type' => $this->lead_type,
            'currency' => $this->currency,
            
            'created_at' => $this->created_at->setTimezone(config('app.timezone')),
            'updated_at' => $this->updated_at,
            'duplicate_no'=>$this->duplicate_leads->count(),
            'duplicate_ids'=>$this->duplicate_leads->pluck('id')->toArray(),
            'is_reverted'=>!is_null($this->revert),
            'can_edit'=>auth()->check() && (auth()->user()->hasRole('super_admin') || auth()->user()->hasRole('admin') || $this->responsible_person_id==auth()->user()->id),
            'can_edit_phone_email'=>auth()->check() && (auth()->user()->hasRole('super_admin') || auth()->user()->hasRole('admin')),
            'can_delete'=>auth()->check() && (auth()->user()->hasRole('super_admin') || auth()->user()->hasRole('admin') ),
             'raw_meta_data' => $rawMetaData, 
                        'facebook_questions_answers' =>$facebookFields,
            'parent'=>new \App\Http\Resources\User\UserResource($assignedBy),
            'assigned_at'=>$assignmentHistory?$assignmentHistory->created_at:$this->created_at,
            'property_type'=>$this->propertyType?->name,
            'area'=>$this->area?->title,
            'property_type_id'=>$this->propertyType?->id,
            'area_id'=>$this->area?->id,
            'score' => $this->score,
            'priority' => $this->priority,
            'intent' => $this->intent,
            'next_action' => $this->next_action,
            'risk' => ($this->priority === 'hot' && $this->updated_at && $this->updated_at->lt(now()->subDays(2)))
                ? 'cooling down'
                : '',
            'original_name' => data_get($this->createdHistory, 'changes.name', $this->name),

            'original_branch' => data_get($this->createdHistory, 'changes.lead_branch_source'),
           'api_first_question' => $this->getFirstApiQuestion(),
            'has_service_duplicate' => $this->hasServiceDuplicate(),

        ];
    }

    /**
     * Resolves the project id used to scope property listings (integration config or lead column).
     */
    protected function resolveIntegrationProjectId(): ?int
    {
        /** @var \App\Models\Lead $lead */
        $lead = $this->resource;

        if ($lead->relationLoaded('integration')) {
            $pid = $lead->integration?->project_id;
            if ($pid !== null && $pid !== '') {
                return (int) $pid;
            }
        }

        if ($lead->integration_id) {
            $pid = Integration::query()->whereKey($lead->integration_id)->value('project_id');
            if ($pid !== null && $pid !== '') {
                return (int) $pid;
            }
        }

        if ($lead->project_id !== null && $lead->project_id !== '') {
            return (int) $lead->project_id;
        }

        return null;
    }

    protected function getFirstApiQuestion()
{
    $rawMetaData = is_string($this->raw_meta_data) 
        ? json_decode($this->raw_meta_data, true) 
        : $this->raw_meta_data;
    
    $basicFields = ['email', 'phone', 'full_name', 'name', 'work_phone', 'work_phone_number', 'phone_number', 'full name', 'first_name', 'last_name','Date','Time','inbox_url', 'Page_Name', 'form_name', 'form_id', 'No_Label_name', 'No_Label_email', 'No_Label_phone'];
    
    $facebookFields = [];
    
    if (!empty($rawMetaData['field_data']) && is_array($rawMetaData['field_data'])) {
        foreach ($rawMetaData['field_data'] as $field) {
            if (isset($field['name']) && isset($field['values'][0])) {
                $fieldName = $field['name'];
                $fieldValue = $field['values'][0];
                
                if (!in_array($fieldName, $basicFields)) {
                    $facebookFields[$fieldName] = $fieldValue;
                }
            }
        }
    }
    
    if (!empty($facebookFields)) {
        $firstQuestionKey = array_key_first($facebookFields);
        return $firstQuestionKey ." : " . $facebookFields[$firstQuestionKey];
          
    }
    
    return null;
}
 protected function hasServiceDuplicate(): bool
{
    return Lead::query()
        ->where('id', '!=', $this->id)
        ->where('status_lead', 'service_provide')
        ->where(function ($q) {
            if ($this->work_phone) {
                $q->orWhere('work_phone', $this->work_phone);
            }
            if ($this->email) {
                $q->orWhere('email', $this->email);
            }
        })
        ->exists();
}
}