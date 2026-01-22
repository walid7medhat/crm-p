<?php

namespace App\Http\Resources\Lead;

use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    public function toArray($request): array
    {
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
            'website' => $this->website,
            'messenger' => $this->messenger,
            'facebook' => $this->facebook,
            
            // Company Information
            'company_name' => $this->company_name,
            'position' => $this->position,
            
            // Property Information
            'interested_in' => $this->interested_in,
            'bedrooms' => $this->bedrooms,
            'purpose_buying' => $this->purpose_buying,
            'nationality' => $this->nationality,
            'citizenship_program' => $this->citizenship_program,
            
            // Lead Source
            'lead_source' => $this->lead_source,
            'source_information' => $this->source_information,
            'lead_branch_source' => $this->lead_branch_source,
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
            
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}