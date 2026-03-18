<?php

namespace App\Http\Resources\Lead;

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
            // إذا لم يتم العثور على هيستوري إسناد، استخدم addedBy
            $assignedBy = $this->addedBy;
        }
        return [
            'id' => $this->id,
            'added_by' => $this->added_by,
            
            // Basic Information
            'lead_name' => $this->lead_name,
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
            
            // Company Information
            'company_name' => $this->company_name,
            'position' => $this->position,
            
            // Property Information
            'interested_in' => $this->interested_in,
            'bedrooms' => $this->bedrooms==0?'studio':$this->bedrooms,
            'purpose_buying' => $this->purpose_buying,
            'nationality' => $this->nationality,
            'citizenship_program' => $this->citizenship_program,
            
            // Lead Source
            'lead_source' => $this->lead_source,
            'source_information' => $this->source_information,
                'lead_branch_source' =>
            // $this->lead_branch_source ??
            $this->responsiblePerson?->admin_parent?->name,
            'ad_id' => $this->ad_id,
            'available_to_everyone' => $this->available_to_everyone,
            
            // Status
            'status_lead' => $this->status_lead,
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
            
            'budget'=>$this->budget,
            'currency'=>$this->currency,
            
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

        ];
    }
}