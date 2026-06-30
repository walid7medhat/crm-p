<?php

namespace App\Http\Resources\Stage;

use App\Http\Resources\Lead\LeadResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'order' => $this->order,
            'color' => $this->color,

            // ✅ Auto Revert Settings
            'auto_revert' => (bool) $this->auto_revert,
            'revert_after_hours' => $this->revert_after_hours,
            'notify_before_minutes' => $this->notify_before_minutes,

            'leads_count' => $this->whenCounted('leads'),

            'leads'=> $this->whenLoaded('leads', function () {
                return LeadResource::collection($this->leads);
            }),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
