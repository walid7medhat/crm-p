<?php

namespace App\Http\Resources\Listing;

use Illuminate\Http\Resources\Json\JsonResource;

class FloorPlanImageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name'=>$this->name,
            'image_path' => $this->image_path,
            'image_url' => $this->image_url,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
            'area_id'=>$this->area_id,
            ];
    }
}