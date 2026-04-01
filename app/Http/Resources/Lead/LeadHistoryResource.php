<?php

namespace App\Http\Resources\Lead;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadHistoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
     public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'action'    => $this->changes['action'] ?? null,
            'changes'   => $this->changes,
            'user'      => $this->whenLoaded('user', function () {
                return [
                    'id'   => $this->user?->id,
                    'name' => $this->user?->name,
                    'avatar'=>$this->user?->avatar_url,
                     'user_role_name' => $this->user?->roles?->first()?->name,
                    'user_parent_name' => $this->user?->parent?->name,
                    'user_branch_name' => $this->user?->office?->name,
                ];
            }),
            'date'      => $this->created_at->format('Y-m-d H:i'),
            'time_ago'  => $this->created_at->diffForHumans(),
            'lead_name'=>$this->lead?->lead_name,
            'response_person'=>$this->lead?->initialResponsiblePerson?->name,
            'response_person_avatar'=>$this->lead?->initialResponsiblePerson?->avatar,
            'source' =>$this->lead?->lead_source,
            'createdBy'=>$this?->lead?->addedBy?->name
        ];
    }
}
