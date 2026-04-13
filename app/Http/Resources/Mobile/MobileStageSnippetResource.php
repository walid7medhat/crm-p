<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobileStageSnippetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'order' => $this->order,
            'color' => $this->color,
            'stage_type' => $this->stage_type,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
