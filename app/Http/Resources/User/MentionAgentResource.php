<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MentionAgentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->email,
            'phone'      => $this->phone ?? null,
            'avatar' => $this->avatar ?  asset('storage/'. $this->avatar) : null,
             'parent_id' => $this->parent_id,
            'parent_name' => $this->parent?->name,
            'parent_role'=>$this->parent?->roles->first()?->name,
            'admin_parent_name'=>$this->admin_parent?->name,

            'created_at' => $this->created_at?->format('Y-m-d H:i'),
   
        ];
    }
}
