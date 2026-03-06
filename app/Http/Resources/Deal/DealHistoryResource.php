<?php

namespace App\Http\Resources\Deal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DealHistoryResource extends JsonResource
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
                ];
            }),
            'date'      => $this->created_at->format('Y-m-d H:i'),
            'time_ago'  => $this->created_at->diffForHumans(),
            'deal_name'=>$this->deal?->deal_name,
            'response_person'=>$this->deal?->responsiblePerson?->name,
            'response_person_avatar'=>$this->deal?->responsiblePerson?->avatar,
            'source' =>$this->deal?->source,
            'createdBy'=>$this?->deal?->addedBy?->name
        ];
    }
}
