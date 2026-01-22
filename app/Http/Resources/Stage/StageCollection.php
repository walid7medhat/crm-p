<?php

namespace App\Http\Resources\Stage;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Http\Resources\Stage\StageResource;
class StageCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => StageResource::collection($this->collection),
            'meta' => [
                'total' => $this->count(),
                'total_leads' => $this->collection->sum('leads_count'),
            ],
        ];
    }
}