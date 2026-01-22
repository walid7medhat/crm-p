<?php

namespace App\Http\Resources\Listing;

use Illuminate\Http\Resources\Json\JsonResource;

class PropertyTypeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'added_by' => $this->added_by,
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
            
            // Relationships
            'children' => PropertyTypeResource::collection($this->whenLoaded('children')),
            'added_by_user' => $this->whenLoaded('addedBy'),
            'parent'=>new PropertyTypeResource($this->parent),
            // Counts
            'children_count' => $this->whenCounted('children'),
        ];
    }
}