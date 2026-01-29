<?php

namespace App\Http\Resources\Lead;

use Illuminate\Http\Resources\Json\JsonResource;

class DuplicateLeadResource extends JsonResource
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

            // Contact Details
            'work_phone' => $this->work_phone,
            'work_phone_2' => $this->work_phone_2,
  
            
         
            // Lead Source
            'lead_source' => $this->lead_source,
            'source_information' => $this->source_information,
                'lead_branch_source' =>
            // $this->lead_branch_source ??
            $this->responsiblePerson?->admin_parent?->name,
           
            'responsible_person_id' => $this->responsible_person_id,
            'last_stage_change_at' => $this->last_stage_change_at,
            
            // Relationships
            'stage' => new \App\Http\Resources\Stage\MainStageResource($this->whenLoaded('stage')),
            'responsible_person' => new \App\Http\Resources\User\UserResource($this->responsiblePerson),
           
            
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'duplicate_no'=>$this->duplicate_leads->count(),
            
            
        ];
    }
}