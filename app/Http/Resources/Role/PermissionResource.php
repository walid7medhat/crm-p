<?php

namespace App\Http\Resources\Role;

use Illuminate\Http\Resources\Json\JsonResource;

class PermissionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'guard_name' => $this->guard_name,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    protected function getModule(): string
    {
        return explode('-', $this->name)[0] ?? 'other';
    }

    protected function getAction(): string
    {
        return explode('-', $this->name)[1] ?? $this->name;
    }
}