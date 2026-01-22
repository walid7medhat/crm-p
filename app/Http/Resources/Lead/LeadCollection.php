<?php

namespace App\Http\Resources\Lead;

use Illuminate\Http\Resources\Json\ResourceCollection;

class LeadCollection extends ResourceCollection
{
    public function toArray($request): array
    {
        return [
            'data' => LeadResource::collection($this->collection),
            'meta' => [
                'count' => $this->count(),
                'total' => $this->total(),
                'per_page' => $this->perPage(),
                'current_page' => $this->currentPage(),
                'last_page' => $this->lastPage(),
            ]
        ];
    }
}