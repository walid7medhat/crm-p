<?php

namespace App\Http\Resources\Role;

use Illuminate\Http\Resources\Json\ResourceCollection;

class PermissionCollection extends ResourceCollection
{
    public function toArray($request): array
    {
        return [
            'data' => PermissionResource::collection($this->collection),
            'meta' => [
                'count' => $this->count(),
                'modules' => $this->getModules(),
            ]
        ];
    }

    protected function getModules(): array
    {
        return $this->collection->groupBy(function ($permission) {
            return explode('-', $permission->name)[0] ?? 'other';
        })->keys()->toArray();
    }
}