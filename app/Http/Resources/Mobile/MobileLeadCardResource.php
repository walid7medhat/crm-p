<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Flat DTO for Kanban cards — no deep nesting; safe for Pusher + REST.
 */
class MobileLeadCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->responsiblePerson;

        return [
            'id' => $this->id,
            'title' => $this->lead_name,
            'lead_number' => $this->lead_number,
            'stage_id' => $this->stage_id,
            'updated_at' => $this->updated_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'last_stage_change_at' => $this->last_stage_change_at?->toIso8601String(),
            'assigned_user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar ? asset('storage/'.$user->avatar) : null,
            ] : null,
            'work_phone' => $this->work_phone,
            'email' => $this->email,
            'status_lead' => $this->status_lead,
            'lead_type' => $this->lead_type,
            'property_status' => $this->property_status,
            'budget' => $this->budget,
            'lead_source' => $this->lead_source,
            'score' => $this->score,
            'priority' => $this->priority,
        ];
    }
}
