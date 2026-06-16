<?php

namespace App\Http\Resources\Listing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeveloperResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->avatar_path ? asset('storage/' . $this->avatar_path) : null,
            'added_by' => $this->addedBy?->name,
            // إضافة الحقول الجديدة
            'noc_fees_ready' => $this->noc_fees_ready,
            'noc_fees_off_plan' => $this->noc_fees_off_plan,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}