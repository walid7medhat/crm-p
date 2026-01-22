<?php

namespace App\Http\Resources\Role;

use Illuminate\Http\Resources\Json\ResourceCollection;

class RoleCollection extends ResourceCollection
{
    public function toArray($request): array
    {
        return [
            'data' => RoleResource::collection($this->collection),
            'meta' => [
                'count' => $this->count(),
            ]
        ];
    }
}