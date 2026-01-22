<?php

namespace App\Http\Resources\Lead;

use Illuminate\Http\Resources\Json\JsonResource;

class LeadParticipantResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'lead_id' => $this->lead_id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'type' => $this->type,
            'added_by' => $this->added_by,
            'added_by_user' => new \App\Http\Resources\User\UserResource($this->whenLoaded('addedBy')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}