<?php

namespace App\Http\Resources\Listing;

use Illuminate\Http\Resources\Json\JsonResource;

class AreaResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'parent_name'=>$this->parent?->name,
            'name' => $this->name,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'type' => $this->type,
            'added_by' => $this->added_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'area_parents_title'=>$this->area_title,
            // 'parent' => new AreaResource($this->whenLoaded('parent')),
            'children' => AreaResource::collection($this->whenLoaded('child')),
            'children_count' => $this->whenCounted('child'),
            'added_by_user' => $this->whenLoaded('addedBy'),
        ];
    }
}